<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Anonymous daily counters. No individual event or customer record is retained. */
final class Insights {
    const EVENTS = ['contact_click','phone_click','callback_open','callback_received','enquiry_received','form_failure','notification_failure'];
    const CHANNELS = ['whatsapp','call','sms','telegram','messenger','email','custom','callback','enquiry'];
    const SOURCES = ['widget','sitewide','form','recovery'];
    public static function defaults() { return ['analytics'=>false,'analytics_retention'=>90,'analytics_consent'=>'required']; }
    public static function table() { global $wpdb; return $wpdb->prefix . 'fcb_insights'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table=self::table(); $collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            day date NOT NULL,
            event varchar(24) NOT NULL,
            channel varchar(20) NOT NULL,
            source varchar(20) NOT NULL,
            total bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (day,event,channel,source)
        ) $collate;");
    }
    public static function boot() {
        add_action('wp_ajax_fcb_event',[self::class,'ajax']);
        add_action('wp_ajax_nopriv_fcb_event',[self::class,'ajax']);
        add_action('fcb_insights_cleanup',[self::class,'cleanup']);
    }
    private static function valid($event,$channel,$source) {
        return is_string($event) && is_string($channel) && is_string($source)
            && in_array($event,self::EVENTS,true) && in_array($channel,self::CHANNELS,true) && in_array($source,self::SOURCES,true)
            && ($event!=='phone_click' || ($source==='sitewide' && $channel==='call'))
            && ($event!=='contact_click' || ($source==='widget' && !in_array($channel,['callback','enquiry'],true)));
    }
    public static function record($event,$channel,$source='widget',$event_id='',$at=null,$consented=false) {
        $cfg=array_replace(self::defaults(),Settings::live());
        if (empty($cfg['analytics']) || ($cfg['analytics_consent']!=='not_required' && !$consented) || !self::valid($event,$channel,$source)) { return false; }
        if (!is_string($event_id) || ($event_id!=='' && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$event_id))) { return false; }
        if ($at!==null && !is_int($at)) { return false; }
        $timestamp=$at===null ? time() : $at;
        if ($timestamp<0 || $timestamp>time()+MINUTE_IN_SECONDS) { return false; }
        // The guard is short-lived and stores only a salted digest of the UUID.
        $key='insight:'.$event.':'.strtolower($event_id);
        if ($event_id!=='' && !Store::claim($key,DAY_IN_SECONDS)) { return false; }
        global $wpdb; $table=self::table();
        $day=wp_date('Y-m-d',$timestamp,wp_timezone());
        $ok=$wpdb->query($wpdb->prepare("INSERT INTO $table (day,event,channel,source,total) VALUES (%s,%s,%s,%s,1) ON DUPLICATE KEY UPDATE total=total+1",$day,$event,$channel,$source));
        if ($ok===false && $event_id!=='') { Store::release($key); }
        return $ok!==false;
    }
    private static function day($value) {
        if (is_int($value)) { return wp_date('Y-m-d',$value,wp_timezone()); }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { return ''; }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,wp_timezone());
        return $date && $date->format('Y-m-d')===$value ? $value : '';
    }
    private static function empty_metrics() { return ['clicks'=>0,'callback_requests'=>0,'enquiries'=>0,'failures'=>0,'form_opens'=>0]; }
    /** Inclusive site-local day bounds; max 366 days. No customer table reads. */
    public static function summary($start,$end) {
        $out=self::empty_metrics()+['by_channel'=>[],'days'=>[],'events'=>[]];
        $start=self::day($start); $end=self::day($end);
        if (!$start || !$end || $start>$end || (strtotime($end)-strtotime($start))>365*DAY_IN_SECONDS) { return $out; }
        $cursor=new \DateTimeImmutable($start,wp_timezone()); $last=new \DateTimeImmutable($end,wp_timezone());
        while ($cursor<=$last) { $out['days'][$cursor->format('Y-m-d')]=self::empty_metrics(); $cursor=$cursor->modify('+1 day'); }
        global $wpdb; $table=self::table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,event,channel,SUM(total) AS total FROM $table WHERE day >= %s AND day <= %s GROUP BY day,event,channel",$start,$end),ARRAY_A);
        foreach ($rows as $row) {
            $n=(int)$row['total']; $event=$row['event'];
            $out['events'][$event]=($out['events'][$event]??0)+$n;
            $metric=['contact_click'=>'clicks','phone_click'=>'clicks','callback_received'=>'callback_requests','enquiry_received'=>'enquiries','callback_open'=>'form_opens','form_failure'=>'failures','notification_failure'=>'failures'][$event]??'';
            if (!$metric) { continue; }
            $out[$metric]+=$n; $out['days'][$row['day']][$metric]+=$n;
            if ($metric==='clicks') { $out['by_channel'][$row['channel']]=($out['by_channel'][$row['channel']]??0)+$n; }
        }
        return $out;
    }
    public static function cleanup() {
        $cfg=array_replace(self::defaults(),Settings::live());
        $days=max(1,min(365,(int)$cfg['analytics_retention']));
        $cutoff=current_datetime()->setTime(0,0)->modify('-'.($days-1).' days')->format('Y-m-d');
        global $wpdb; $table=self::table();
        // Maximum dimension count per day is small; batches also bound upgrades from old data.
        $deleted=$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE day < %s LIMIT 1000",$cutoff));
        if ($deleted===1000 && !wp_next_scheduled('fcb_insights_cleanup')) { wp_schedule_single_event(time()+300,'fcb_insights_cleanup'); }
    }
    public static function ajax() {
        if (!check_ajax_referer('fcb_event','nonce',false)) { wp_send_json_error(['message'=>'Invalid event nonce.'],403); }
        $allowed=['action','nonce','event','channel','source','event_id','consent'];
        if (array_diff(array_keys($_POST),$allowed)) { wp_send_json_error(['message'=>'Invalid event fields.'],400); }
        foreach (['event','channel','source','event_id'] as $field) { if (!isset($_POST[$field]) || !is_string($_POST[$field])) { wp_send_json_error(['message'=>'Invalid event.'],400); } }
        $event=wp_unslash($_POST['event']); $channel=wp_unslash($_POST['channel']); $source=wp_unslash($_POST['source']); $uuid=wp_unslash($_POST['event_id']);
        if (!in_array($event,['contact_click','phone_click','callback_open','form_failure'],true) || !self::valid($event,$channel,$source) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$uuid)) { wp_send_json_error(['message'=>'Invalid event.'],400); }
        $cfg=array_replace(self::defaults(),Settings::live());
        $consented=isset($_POST['consent']) && in_array($_POST['consent'],[true,1,'1'],true);
        if (($event==='phone_click' && empty($cfg['recovery']['enabled'])) || ($event==='callback_open' && empty($cfg['callback']))) { wp_send_json_success(['recorded'=>false]); }
        if (!$cfg['analytics'] || ($cfg['analytics_consent']!=='not_required' && !$consented)) { wp_send_json_success(['recorded'=>false]); }
        // A short-lived salted network digest is used only for abuse prevention, never in counters.
        $network=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),wp_salt('nonce'));
        $rate='fcb_event_'.hash('sha256',$network.':'.(int)floor(time()/MINUTE_IN_SECONDS));
        $count=(int)get_transient($rate);
        if ($count>=60) { wp_send_json_error(['message'=>'Please wait before sending more events.'],429); }
        $slot=false;
        for ($i=0;$i<8;$i++) { if (Store::claim('event-burst:'.$network.':'.time().':'.$i,5)) { $slot=true; break; } }
        if (!$slot) { wp_send_json_error(['message'=>'Please wait before sending more events.'],429); }
        set_transient($rate,$count+1,2*MINUTE_IN_SECONDS);
        wp_send_json_success(['recorded'=>self::record($event,$channel,$source,$uuid,null,$consented)]);
    }
}
