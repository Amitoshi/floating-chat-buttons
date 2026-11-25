<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Private frozen-window summaries; transport acceptance is never delivery confirmation. */
final class Reports {
    const HOOK='fcb_weekly_report';
    const DETAIL_LIMIT=100;
    public static function defaults() { return ['enabled'=>false,'recipients'=>'','weekday'=>1,'time'=>'09:00','details'=>false]; }
    public static function sanitize($input) {
        if (!is_array($input)) { return new \WP_Error('reports','Invalid report configuration.'); }
        $out=self::defaults();
        $out['enabled']=!empty($input['enabled']); $out['details']=!empty($input['details']);
        if (isset($input['recipients']) && !is_string($input['recipients'])) { return new \WP_Error('reports','Enter valid report email addresses.'); }
        $addresses=preg_split('/[,\r\n]+/',(string)($input['recipients']??'')); $unique=[];
        foreach ($addresses as $email) {
            $email=strtolower(trim($email)); if ($email==='') { continue; }
            if (!is_email($email) || count($unique)>=20 && !isset($unique[$email])) { return new \WP_Error('reports','Enter up to 20 valid report email addresses.'); }
            $unique[$email]=true;
        }
        $out['recipients']=implode(',',array_keys($unique));
        if ($out['enabled'] && !$out['recipients']) { return new \WP_Error('reports','Choose at least one report recipient.'); }
        $day=$input['weekday']??1; $time=$input['time']??'09:00';
        if (!is_scalar($day) || !preg_match('/^[1-7]$/D',(string)$day) || !is_string($time) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)) { return new \WP_Error('reports','Choose a valid local weekday and time.'); }
        $out['weekday']=(int)$day; $out['time']=$time;
        return $out;
    }
    private static function config($value=null) {
        if ($value===null) { $value=Settings::live(); }
        return array_replace(self::defaults(),is_array($value['reports']??null) ? $value['reports'] : []);
    }
    public static function boot() {
        add_action('wp_ajax_fcb_report',[self::class,'ajax']);
        add_action(self::HOOK,[self::class,'scheduled']);
        add_action('update_option_fcb_live',[self::class,'changed'],10,2);
        foreach (['timezone_string','gmt_offset'] as $option) { add_action('update_option_'.$option,[self::class,'timezone_changed'],10,2); }
    }
    public static function changed($old,$new) {
        $before=self::config(is_array($old)?$old:[]); $after=self::config(is_array($new)?$new:[]);
        if ($before===$after) { return; }
        $fields=[]; foreach (self::defaults() as $key=>$unused) { if ($before[$key]!==$after[$key]) { $fields[]=$key; } }
        // Audit the changed field names, never recipients or customer content.
        update_option('fcb_report_config_audit',['at'=>time(),'user_id'=>get_current_user_id(),'fields'=>$fields],false);
        self::reconcile();
    }
    public static function timezone_changed($old,$new) { if ($old!==$new) { self::reconcile(); } }
    /** Next occurrence in the site's IANA/fixed-offset zone; DateTime resolves DST wall clocks. */
    public static function next_run($config=null,$after=null) {
        $config=$config===null ? self::config() : array_replace(self::defaults(),$config);
        $checked=self::sanitize(array_replace($config,['enabled'=>false]));
        if (is_wp_error($checked)) { return 0; }
        $after=$after===null ? time() : (int)$after;
        $local=(new \DateTimeImmutable('@'.$after))->setTimezone(wp_timezone());
        list($hour,$minute)=array_map('intval',explode(':',$checked['time']));
        $days=($checked['weekday']-(int)$local->format('N')+7)%7;
        $next=$local->setTime(0,0)->modify('+'.$days.' days')->setTime($hour,$minute);
        if ($next->getTimestamp()<=$after) { $next=$next->modify('+7 days')->setTime($hour,$minute); }
        return $next->getTimestamp();
    }
    public static function reconcile() {
        // Called only by activation/published settings/timezone updates and cron, never homepage rendering.
        if (!Store::claim('report-schedule-lock',30)) { return; }
        try {
            $config=self::sanitize(self::config());
            wp_clear_scheduled_hook(self::HOOK);
            if (is_wp_error($config) || !$config['enabled']) { delete_option('fcb_report_due'); return; }
            $due=self::next_run($config);
            update_option('fcb_report_due',$due,false);
            wp_schedule_single_event($due,self::HOOK);
        } finally { Store::release('report-schedule-lock'); }
    }
    private static function period($now) {
        $end=(new \DateTimeImmutable('@'.(int)$now))->setTimezone(wp_timezone())->setTime(0,0);
        $start=$end->modify('-7 days');
        return ['start'=>$start->format('Y-m-d'),'end'=>$end->modify('-1 day')->format('Y-m-d'),'utc_start'=>gmdate('Y-m-d H:i:s',$start->getTimestamp()),'utc_end'=>gmdate('Y-m-d H:i:s',$end->getTimestamp()),'timezone'=>wp_timezone_string()];
    }
    private static function window($period,$details) {
        global $wpdb; $table=Store::table();
        $counts=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(channel='callback'),0) AS callbacks, COALESCE(SUM(channel='enquiry'),0) AS enquiries FROM $table WHERE created_at >= %s AND created_at < %s",$period['utc_start'],$period['utc_end']),ARRAY_A);
        $out=['analytics'=>Insights::summary($period['start'],$period['end']),'retained_enquiries'=>(int)($counts['total']??0),'retained_callbacks'=>(int)($counts['callbacks']??0),'retained_quick_enquiries'=>(int)($counts['enquiries']??0)];
        if ($details) {
            $out['rows']=$wpdb->get_results($wpdb->prepare('SELECT '.self::detail_columns()." FROM $table WHERE created_at >= %s AND created_at < %s ORDER BY created_at DESC,id DESC LIMIT %d",$period['utc_start'],$period['utc_end'],self::DETAIL_LIMIT),ARRAY_A);
            $out['truncated']=$out['retained_enquiries']>count($out['rows']);
        }
        return $out;
    }
    private static function detail_columns() {
        global $wpdb;
        $columns='id,submission_id,name,contact,message,created_at,source_url,channel,status';
        // Old restored backups may predate the optional M2 source column.
        if ($wpdb->get_var("SHOW COLUMNS FROM ".Store::table()." LIKE 'contact_source'")) { $columns.=',contact_source'; }
        return $columns;
    }
    public static function snapshot($now=null,$details=false) {
        $now=$now===null ? time() : (int)$now; $period=self::period($now);
        $previous_end=(new \DateTimeImmutable($period['start'],wp_timezone()))->getTimestamp();
        $previous=self::period($previous_end);
        global $wpdb; $table=Store::table();
        $waiting=['total'=>0,'new'=>0,'contacted'=>0];
        $counts=$wpdb->get_results("SELECT status,COUNT(*) AS total FROM $table WHERE status IN ('new','contacted') GROUP BY status",ARRAY_A);
        foreach ($counts as $row) { $waiting[$row['status']]=(int)$row['total']; $waiting['total']+=(int)$row['total']; }
        if ($details) {
            $waiting['rows']=$wpdb->get_results($wpdb->prepare('SELECT '.self::detail_columns()." FROM $table WHERE status IN ('new','contacted') ORDER BY created_at ASC,id ASC LIMIT %d",self::DETAIL_LIMIT),ARRAY_A);
            $waiting['truncated']=$waiting['total']>count($waiting['rows']);
        }
        $cfg=array_replace(Insights::defaults(),Settings::live());
        return ['generated_at'=>gmdate('c',$now),'period'=>$period,'previous_period'=>$previous,'current'=>self::window($period,$details),'previous'=>self::window($previous,false),'waiting'=>$waiting,'details'=>(bool)$details,'analytics_enabled'=>(bool)$cfg['analytics'],'inbox_url'=>admin_url('admin.php?page=fcb-inbox')];
    }
    public static function render($snapshot) {
        $current=$snapshot['current']; $previous=$snapshot['previous']; $analytics=$current['analytics'];
        $html='<h1>Contact Buttons weekly report</h1><p>'.esc_html($snapshot['period']['start'].' to '.$snapshot['period']['end'].' ('.$snapshot['period']['timezone'].')').'</p>';
        $html.='<p>Seven complete local days compared with '.$snapshot['previous_period']['start'].' to '.$snapshot['previous_period']['end'].'. Clicks cannot confirm that a call connected or a message was sent.</p>';
        if (!$snapshot['analytics_enabled']) { $html.='<p>Analytics is currently disabled. Retained enquiry counts remain available. Historical analytics may be incomplete.</p>'; }
        $html.='<table><thead><tr><th scope="col">Metric</th><th scope="col">Report period</th><th scope="col">Previous seven days</th></tr></thead><tbody>';
        foreach (['clicks'=>'Contact link clicks (recorded analytics)','callback_requests'=>'Accepted callback requests (recorded analytics)','enquiries'=>'Accepted enquiries (recorded analytics)','form_opens'=>'Callback form opens (recorded analytics)','failures'=>'Form/notification errors (recorded analytics)'] as $key=>$label) {
            $html.='<tr><th scope="row">'.esc_html($label).'</th><td>'.(int)$analytics[$key].'</td><td>'.(int)$previous['analytics'][$key].'</td></tr>';
        }
        foreach (['retained_enquiries'=>'Retained inbox submissions','retained_callbacks'=>'Retained callback requests','retained_quick_enquiries'=>'Retained quick enquiries'] as $key=>$label) { $html.='<tr><th scope="row">'.esc_html($label).'</th><td>'.(int)$current[$key].'</td><td>'.(int)$previous[$key].'</td></tr>'; }
        $html.='</tbody></table><p>Retained inbox counts come from saved requests, independently of analytics consent. They exclude requests never stored or already removed by retention.</p>';
        $html.='<p>Clicks by channel: '; $channels=[];
        foreach ($analytics['by_channel'] as $channel=>$count) { $channels[]=esc_html($channel).': '.(int)$count; }
        $html.=($channels?implode('; ',$channels):'No recorded clicks').'.</p>';
        $html.='<p>Callback conversion (accepted callback requests / callback form opens): '.($analytics['form_opens'] ? esc_html(round(100*$analytics['callback_requests']/$analytics['form_opens'],1).'%') : 'Unavailable: no recorded form opens').'. Consent and retention can affect these counts.</p>';
        $html.='<h2>Customers Still Waiting for a Response</h2><p>'.(int)$snapshot['waiting']['total'].' retained unresolved requests: '.(int)$snapshot['waiting']['new'].' New; '.(int)$snapshot['waiting']['contacted'].' Contacted. Includes requests older than this report period.</p>';
        if (!empty($snapshot['details'])) {
            $html.='<p>Customer details were explicitly enabled by an administrator. Email copies may be forwarded or retained by mailbox owners and cannot be recalled by deleting local records.</p>';
            $html.=self::detail_rows($snapshot['waiting']['rows'],$snapshot['waiting']['total']);
            $html.='<h2>Submissions during the report period</h2>'.self::detail_rows($current['rows'],$current['retained_enquiries']);
        }
        $html.='<p><a href="'.esc_url($snapshot['inbox_url']).'">Open the private inbox (administrator sign-in required)</a></p><p>WordPress scheduled tasks depend on site traffic or server cron. Email accepted by the configured transport does not confirm inbox delivery.</p>';
        return $html;
    }
    private static function detail_rows($rows,$total) {
        $html='<p>Showing '.count($rows).' of '.(int)$total.' records. Open the private inbox for all records.</p><ul>';
        foreach ($rows as $row) {
            $path=wp_parse_url($row['source_url'],PHP_URL_PATH);
            $text='ID '.$row['id'].' / '.$row['submission_id'].' | '.$row['created_at'].' UTC | '.$row['channel'].' | '.$row['status'];
            if (!empty($row['contact_source'])) { $text.=' | Contact source: '.$row['contact_source']; }
            $html.='<li>'.esc_html($text).'<br>'.esc_html($row['name']).' '.esc_html($row['contact']).'<br>'.nl2br(esc_html($row['message'])).'<br>Source path: '.esc_html((string)$path).'</li>';
        }
        return $html.'</ul>';
    }
    private static function mail($config,$subject,$html) {
        $ok=true;
        // Individual messages avoid disclosing the recipient list to other recipients.
        foreach (explode(',',$config['recipients']) as $recipient) {
            try { $accepted=(bool)wp_mail($recipient,$subject,$html,['Content-Type: text/html; charset=UTF-8']); }
            catch (\Throwable $error) { $accepted=false; }
            $ok=$accepted && $ok;
        }
        return $ok;
    }
    public static function send($now=null) {
        $config=self::sanitize(self::config()); $now=$now===null ? time() : (int)$now;
        if (is_wp_error($config) || !$config['enabled']) { return ['status'=>'disabled']; }
        $period=self::period($now);
        // One run per site-local ISO week even if an administrator changes its weekday.
        $week=(new \DateTimeImmutable('@'.$now))->setTimezone(wp_timezone())->format('o-W');
        $key='weekly-report:'.$week;
        if (!Store::claim($key,15*DAY_IN_SECONDS)) { return ['status'=>'duplicate']; }
        // No automatic retry after failure: a partial recipient send must not flood mailboxes.
        $snapshot=self::snapshot($now,$config['details']);
        $ok=self::mail($config,'Contact Buttons weekly report',self::render($snapshot));
        $state=['status'=>$ok?'accepted':'failed','at'=>time(),'period'=>$period,'detail_level'=>$config['details']?'details':'summary','message'=>$ok?'Accepted by configured email transport; inbox delivery is unconfirmed.':'One or more messages were rejected by the email transport. No automatic resend.'];
        update_option('fcb_last_report',$state,false);
        return $state;
    }
    public static function scheduled() {
        $due=(int)get_option('fcb_report_due',0);
        if ($due && $due>time()+MINUTE_IN_SECONDS) { return; }
        self::send($due?:time());
        self::reconcile();
    }
    public static function test_mail() {
        $config=self::sanitize(array_replace(self::config(),['enabled'=>false]));
        if (is_wp_error($config) || !$config['recipients']) { return ['status'=>'invalid','message'=>'Choose valid report recipients before testing.']; }
        if (!Store::claim('report-test:'.get_current_user_id(),5*MINUTE_IN_SECONDS) || !Store::claim('report-test-site',MINUTE_IN_SECONDS)) { return ['status'=>'rate_limited','message'=>'Wait five minutes before another test email.']; }
        $ok=self::mail($config,'Contact Buttons sample report','<h1>Sample contact report</h1><p>This test contains sample counts only: 12 contact clicks, 3 callback requests and 2 waiting requests. No customer records were read.</p><p>Transport acceptance does not confirm inbox delivery.</p>');
        $state=['status'=>$ok?'accepted':'failed','at'=>time(),'message'=>$ok?'Sample accepted by email transport; delivery is unconfirmed.':'Sample rejected by email transport.'];
        update_option('fcb_last_report_test',$state,false); return $state;
    }
    public static function ajax() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'You cannot generate reports.'],403); }
        if (!check_ajax_referer('fcb_admin','nonce',false)) { wp_send_json_error(['message'=>'Invalid report nonce.'],403); }
        $mode=isset($_POST['mode']) && is_string($_POST['mode']) ? wp_unslash($_POST['mode']) : '';
        if ($mode==='test') { $state=self::test_mail(); if (in_array($state['status'],['invalid','failed','rate_limited'],true)) { wp_send_json_error($state,$state['status']==='rate_limited'?429:400); } wp_send_json_success($state); }
        if ($mode!=='generate') { wp_send_json_error(['message'=>'Choose generate or test.'],400); }
        if (!Store::claim('report-generate:'.get_current_user_id(),30)) { wp_send_json_error(['message'=>'Wait 30 seconds before generating another report.'],429); }
        $config=self::config(); $snapshot=self::snapshot(null,!empty($config['details']));
        wp_send_json_success(['html'=>self::render($snapshot),'snapshot'=>$snapshot,'message'=>'Private report generated.']);
    }
}
