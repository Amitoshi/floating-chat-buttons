<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

final class Admin {
    const DOMAIN = 'floating-chat-buttons-whatsapp-telegram-call';
    public static function boot() {
        require_once FCB_DIR.'includes/class-admin-m2.php'; M2Admin::boot();
        require_once FCB_DIR.'includes/class-help.php'; Help::boot();
        require_once FCB_DIR.'includes/class-simple.php'; Simple::boot();
        add_filter('admin_body_class',function($classes){ return $classes.' fcb-interface-'.Simple::mode(); });
        add_action('admin_menu', [self::class,'menu']);
        add_action('admin_enqueue_scripts', [self::class,'assets']);
        add_action('wp_ajax_fcb_save', [self::class,'save']);
        add_action('admin_post_fcb_inbox', [self::class,'inbox_action']);
        add_action('admin_post_fcb_toggle', [self::class,'toolbar_toggle']);
        add_filter('plugin_action_links_' . plugin_basename(FCB_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=fcb-setup')) . '">' . esc_html__('Easy Setup',self::DOMAIN) . '</a>');
            return $links;
        });
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options') || get_option('fcb_setup_dismissed') || (get_current_screen() && strpos(get_current_screen()->id, 'fcb') !== false)) { return; }
            echo '<div class="notice notice-info"><p>' . esc_html__('Floating Chat Buttons is ready to set up.',self::DOMAIN) . ' <a href="' . esc_url(admin_url('admin.php?page=fcb-setup')) . '">' . esc_html__('Open Easy Setup',self::DOMAIN) . '</a></p></div>';
        });
    }
    public static function allowed() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You cannot manage these settings.',self::DOMAIN), '', ['response'=>403]); }
    }
    private static function input($key, $source) { return isset($source[$key]) && is_string($source[$key]) ? sanitize_text_field(wp_unslash($source[$key])) : ''; }
    public static function menu() {
        add_menu_page(__('Floating Chat Buttons',self::DOMAIN),__('Contact Buttons',self::DOMAIN),'manage_options','fcb',[self::class,'page'],'dashicons-format-chat',58);
        foreach (['fcb'=>'Overview','fcb-buttons'=>'Contact Buttons','fcb-design'=>'Design Studio','fcb-inbox'=>'Customer Enquiries','fcb-hours'=>'Business Hours','fcb-recovery'=>'Call Follow-up','fcb-insights'=>'Contact Analytics','fcb-integrations'=>'Tracking Integrations','fcb-display'=>'Display & Visibility','fcb-advanced'=>'Privacy & Mail','fcb-setup'=>'Easy Setup','fcb-help'=>'Help & About'] as $slug=>$label) {
            add_submenu_page('fcb',__($label,self::DOMAIN),__($label,self::DOMAIN),'manage_options',$slug,[self::class,'page']);
        }
    }
    public static function assets($hook) {
        if (strpos($hook,'fcb') === false || !current_user_can('manage_options')) { return; }
        Widget::assets(true);
        wp_enqueue_style('fcb-admin',FCB_URL.'assets/admin.css',[],FCB_VERSION);
        wp_enqueue_script('fcb-preview-movement',FCB_URL.'assets/preview-movement.js',['fcb-admin'],FCB_VERSION,true);
        wp_enqueue_script('fcb-focused-editor',FCB_URL.'assets/focused-editor.js',['fcb-quick-editor'],FCB_VERSION,true);
        wp_enqueue_script('fcb-quick-editor',FCB_URL.'assets/quick-editor.js',['fcb-admin'],FCB_VERSION,true);
        wp_enqueue_script('fcb-admin',FCB_URL.'assets/admin.js',['fcb-widget','fcb-forms'],FCB_VERSION,true);
        wp_enqueue_style('fcb-admin-m2',FCB_URL.'assets/admin-m2.css',['fcb-admin'],FCB_VERSION);
        wp_enqueue_script('fcb-admin-m2',FCB_URL.'assets/admin-m2.js',['fcb-admin'],FCB_VERSION,true);
        wp_localize_script('fcb-admin','FCBAdmin',[
            'nonce'=>wp_create_nonce('fcb_admin'),'url'=>admin_url('admin-ajax.php'),
            'draft'=>Settings::draft(),'live'=>Settings::live(),'defaults'=>Settings::defaults(),'presets'=>Settings::presets(),
            'page'=>self::input('page',$_GET),
            'interfaceMode'=>Simple::mode(),'setupUrl'=>admin_url('admin.php?page=fcb-setup'),
            'strings'=>[
                'newer'=>__('Saved the earlier draft. You have newer unsaved changes.',self::DOMAIN),
                'saving'=>__('Saving…',self::DOMAIN),'draft'=>__('Draft saved. Your published widget is unchanged.',self::DOMAIN),
                'published'=>__('Published. Your widget is live.',self::DOMAIN),'error'=>__('Unable to save. Please retry.',self::DOMAIN),
                'preview'=>__('Live preview',self::DOMAIN),'destinations'=>__('Enter valid destinations for selected methods.',self::DOMAIN),
                'methods'=>__('Choose at least one contact method.',self::DOMAIN),
                'reset'=>__('Reset the saved draft to the published configuration?',self::DOMAIN),
            ],
        ]);
    }
    public static function save() {
        self::allowed(); check_ajax_referer('fcb_admin','nonce');
        $mode=self::input('mode',$_POST);
        if ($mode==='dismiss') { update_option('fcb_setup_dismissed',1,false); wp_send_json_success(['message'=>__('Setup invitation dismissed.',self::DOMAIN)]); }
        if ($mode==='toggle') {
            $live=Settings::live(); $live['enabled']=!$live['enabled'];
            $clean=Settings::sanitize($live);
            if (is_wp_error($clean)) { wp_send_json_error(['message'=>$clean->get_error_message(),'field'=>$clean->get_error_data()['field']??''],400); }
            update_option('fcb_live',$clean,false);$draft=Settings::draft();$draft['enabled']=$clean['enabled'];update_option('fcb_draft',$draft,false);
            wp_send_json_success(['message'=>$clean['enabled'] ? __('Widget enabled.',self::DOMAIN) : __('Widget disabled.',self::DOMAIN),'config'=>$clean]);
        }
        if ($mode==='reset') { $clean=Settings::live(); update_option('fcb_draft',$clean,false); wp_send_json_success(['message'=>__('Draft restored from published settings.',self::DOMAIN),'config'=>$clean]); }
        $raw=isset($_POST['config']) && is_string($_POST['config']) && strlen($_POST['config'])<50000 ? json_decode(wp_unslash($_POST['config']),true) : null;
        $clean=Settings::sanitize($raw,$mode==='publish');
        if (is_wp_error($clean)) { wp_send_json_error(['message'=>$clean->get_error_message(),'field'=>$clean->get_error_data()['field']??''],400); }
        if ($mode==='testmail') {
            if (!is_email($clean['notification_email'])) { wp_send_json_error(['message'=>__('Enter a valid notification email.',self::DOMAIN)],400); }
            if (!Store::claim('admin-mail:' . get_current_user_id(),5*MINUTE_IN_SECONDS)) { wp_send_json_error(['message'=>__('Wait five minutes before sending another test email.',self::DOMAIN)],429); }
            try { $ok=wp_mail($clean['notification_email'],__('Contact Buttons test notification',self::DOMAIN),__('This is a test notification from Floating Chat Buttons. Transport acceptance does not confirm inbox delivery.',self::DOMAIN)); }
            catch (\Throwable $e) { $ok=false; }
            if (!$ok) { wp_send_json_error(['message'=>__('The mail transport did not accept the test. Check your WordPress mail configuration.',self::DOMAIN)],503); }
            wp_send_json_success(['message'=>__('Test accepted by the mail transport. Check the recipient inbox and spam folder; delivery is not confirmed.',self::DOMAIN)]);
        }
        if (!in_array($mode,['draft','publish'],true)) { wp_send_json_error(['message'=>__('Unknown action.',self::DOMAIN)],400); }
        update_option('fcb_draft',$clean,false);
        if ($mode==='publish') { update_option('fcb_live',$clean,false); update_option('fcb_setup_dismissed',1,false); }
        wp_send_json_success(['message'=>$mode==='publish' ? __('Published. Your widget is live.',self::DOMAIN) : __('Draft saved. Your published widget is unchanged.',self::DOMAIN),'config'=>$clean]);
    }
    public static function toolbar($bar) {
        if (!current_user_can('manage_options')) { return; }
        $enabled=Settings::live()['enabled'];
        $bar->add_node(['id'=>'fcb-toolbar','title'=>$enabled ? __('Contact Buttons: On',self::DOMAIN) : __('Contact Buttons: Off',self::DOMAIN),'href'=>admin_url('admin.php?page=fcb')]);
        $bar->add_node(['id'=>'fcb-toolbar-toggle','parent'=>'fcb-toolbar','title'=>$enabled ? __('Disable widget',self::DOMAIN) : __('Enable widget',self::DOMAIN),'href'=>wp_nonce_url(admin_url('admin-post.php?action=fcb_toggle'),'fcb_toggle')]);
        $bar->add_node(['id'=>'fcb-toolbar-setup','parent'=>'fcb-toolbar','title'=>__('Easy Setup',self::DOMAIN),'href'=>admin_url('admin.php?page=fcb-setup')]);
    }
    public static function toolbar_toggle() {
        self::allowed(); check_admin_referer('fcb_toggle');
        $c=Settings::live(); $c['enabled']=!$c['enabled']; $clean=Settings::sanitize($c);
        if (is_wp_error($clean)) { wp_die(esc_html($clean->get_error_message()) . ' <a href="' . esc_url(admin_url('admin.php?page=fcb-setup')) . '">' . esc_html__('Open Easy Setup',self::DOMAIN) . '</a>'); }
        update_option('fcb_live',$clean,false);$draft=Settings::draft();$draft['enabled']=$clean['enabled'];update_option('fcb_draft',$draft,false);
        wp_safe_redirect(admin_url('admin.php?page=fcb')); exit;
    }
    public static function inbox_action() {
        self::allowed(); check_admin_referer('fcb_inbox');
        $mode=self::input('mode',$_POST); $id=absint($_POST['id'] ?? 0);
        if ($mode==='status') { Store::status($id,self::input('status',$_POST)); }
        elseif ($mode==='delete') { Store::delete($id); }
        elseif ($mode==='delete_all' && self::input('confirm_delete',$_POST)==='yes') {
            global $wpdb; $wpdb->query('DELETE FROM ' . Store::table());
        }
        elseif ($mode==='export') {
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=contact-enquiries-' . gmdate('Y-m-d') . '.csv');
            header('X-Content-Type-Options: nosniff');
            $out=fopen('php://output','w');
            fputcsv($out,['Submission ID','Contact','Name','Message','Submitted UTC','Source URL','Form','Contact channel','Status','Notification transport']);
            $page=1;
            do {
                $result=Store::query(self::input('search',$_POST),self::input('filter_status',$_POST),$page,500);
                foreach ($result['rows'] as $r) { $fields=['submission_id','contact','name','message','created_at','source_url','channel','contact_source','status','notification'];$values=[];foreach($fields as $field){$values[]=Store::csv_cell($r[$field]??'');}fputcsv($out,$values); }
                $page++;
            } while (count($result['rows'])===500);
            fclose($out); exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=fcb-inbox&updated=1')); exit;
    }
    public static function page() {
        self::allowed();
        $page=self::input('page',$_GET); $c=Settings::draft(); $live=Settings::live(); $interface_mode=Simple::mode();
        if ($page==='fcb' && $interface_mode==='simple') { $page='fcb-setup'; }
        $tabs=[
            'fcb-setup'=>['Easy Setup','Publish your contact buttons in about two minutes.','getting-started',false],
            'fcb'=>['Overview','Check widget status and your recent contact activity.','getting-started',true],
            'fcb-buttons'=>['Contact Buttons','Choose contact methods, destinations and their order.','contact-channels',true],
            'fcb-design'=>['Design Studio','Style buttons, launchers and responsive contact layouts.','design-presets',true],
            'fcb-inbox'=>['Customer Enquiries','View callback requests, customer details and follow-up status.','enquiry-inbox',false],
            'fcb-insights'=>['Contact Analytics','See how often visitors use contact buttons and submit requests.','contact-insights',true],
            'fcb-hours'=>['Business Hours','Set each contact method’s hours and closed-hours options.','business-hours',true],
            'fcb-recovery'=>['Call Follow-up','Offer another contact option after someone taps a phone number.','sitewide-call-recovery',true],
            'fcb-integrations'=>['Tracking Integrations','Connect contact events to GA4, consent and settings transfer.','ga4-setup',true],
            'fcb-display'=>['Display & Visibility','Choose where, when and on which devices contact buttons appear.','display-rules',true],
            'fcb-advanced'=>['Privacy & Mail','Manage callback forms, notifications and enquiry retention.','enquiry-retention',true],
            'fcb-help'=>['Help & About','Read the local guide and find answers to common questions.','troubleshooting',false],
        ];
        ?>
        <div class="wrap fcb-admin" data-interface-mode="<?php echo esc_attr($interface_mode); ?>">
            <header class="fcb-topbar"><a href="<?php echo esc_url(admin_url('admin.php?page=fcb')); ?>" class="fcb-brand"><span class="fcb-brand-icon"><?php echo Widget::icon('chat'); ?></span><span>Contact Buttons<small>BY AMIT JYOTI SHAW</small></span></a><div class="fcb-topbar-right"><span class="fcb-free"><?php esc_html_e('Every feature. Always free.',self::DOMAIN); ?></span><a class="fcb-button fcb-button-light" href="<?php echo esc_url(admin_url('admin.php?page=fcb-setup')); ?>"><?php esc_html_e('Easy Setup',self::DOMAIN); ?> ↗</a></div></header>
            <div class="fcb-interface-toolbar"><span><?php esc_html_e('Choose the settings view that suits you. Your features stay active in either view.',self::DOMAIN); ?></span><button type="button" class="fcb-button fcb-button-light" data-interface-mode="<?php echo $interface_mode==='simple'?'advanced':'simple'; ?>" data-interface-toggle aria-pressed="<?php echo $interface_mode==='advanced'?'true':'false'; ?>"><?php echo $interface_mode==='simple'?esc_html__('Show Advanced Settings',self::DOMAIN):esc_html__('Use Simple Settings',self::DOMAIN); ?></button></div>
            <nav class="fcb-nav" aria-label="<?php esc_attr_e('Plugin navigation',self::DOMAIN); ?>"><?php foreach ($tabs as $slug=>$tab) { echo '<div class="fcb-nav-item" data-nav-topic="'.esc_attr($tab[2]).'"'.($tab[3]?' data-advanced-nav="1"':'').'><a' . ($page===$slug ? ' aria-current="page"' : '') . ' href="' . esc_url(admin_url('admin.php?page='.$slug)) . '"><strong>' . esc_html__($tab[0],self::DOMAIN) . '</strong><small>'.esc_html__($tab[1],self::DOMAIN).'</small></a></div>'; } ?></nav>
            <div id="fcb-save-state" role="status" aria-live="polite"></div>
            <?php
            if ($page==='fcb') { require FCB_DIR.'includes/view-dashboard.php'; }
            elseif ($page==='fcb-inbox') { require FCB_DIR.'includes/view-inbox.php'; }
            elseif ($page==='fcb-insights') { M2Admin::insights($c,$live); }
            elseif ($page==='fcb-help') { require FCB_DIR.'includes/view-help.php'; }
            else { require FCB_DIR.'includes/view-editor.php'; }
            ?>
            <footer class="fcb-admin-footer"><span>Amit Jyoti Shaw · <?php esc_html_e('Built for real conversations.',self::DOMAIN); ?></span><span><?php esc_html_e('Local assets. No account. Optional analytics.',self::DOMAIN); ?> · v<?php echo esc_html(FCB_VERSION); ?></span></footer>
        </div>
        <?php
    }
    public static function preview($c,$simple_only=false) { ?>
<aside class="fcb-preview-column<?php echo $simple_only?' fcb-simple-only':''; ?>" aria-label="<?php esc_attr_e('Live preview',self::DOMAIN); ?>"><div class="fcb-preview-toolbar"><span><i></i><?php esc_html_e('LIVE PREVIEW',self::DOMAIN); ?></span><div><button type="button" aria-label="<?php esc_attr_e('Desktop preview',self::DOMAIN); ?>" aria-pressed="true" data-viewport="desktop">▱</button><button type="button" aria-label="<?php esc_attr_e('Mobile preview',self::DOMAIN); ?>" aria-pressed="false" data-viewport="mobile">▯</button></div></div><div class="fcb-preview-stage"><div class="fcb-site-preview"><div class="fcb-faux-browser"><i></i><i></i><i></i><span><?php esc_html_e('EXAMPLE WEBSITE · LOCAL PREVIEW',self::DOMAIN); ?></span></div><div class="fcb-faux-nav"><b>Amit Jyoti Shaw</b><span aria-hidden="true">☰</span></div><div class="fcb-faux-hero"><span><?php esc_html_e('A BETTER WAY TO CONNECT',self::DOMAIN); ?></span><h2><?php esc_html_e('Make every customer connection easier.',self::DOMAIN); ?></h2><p><?php esc_html_e('Beautiful contact buttons, convenient callback requests and useful contact insights—all in one place.',self::DOMAIN); ?></p></div><div class="fcb-example-sections"><div><strong><?php esc_html_e('Easy to reach',self::DOMAIN); ?></strong><p><?php esc_html_e('Give customers a clear next step.',self::DOMAIN); ?></p></div><div><strong><?php esc_html_e('Ready to respond',self::DOMAIN); ?></strong><p><?php esc_html_e('Keep every enquiry in view.',self::DOMAIN); ?></p></div></div></div><?php Widget::render($c,true); ?></div><p class="fcb-preview-caption"><?php esc_html_e('Go ahead, click it. Preview links stay here and forms send nothing.',self::DOMAIN); ?></p><div class="fcb-preview-footnote"><span>↳</span><?php esc_html_e('Your site’s font is used automatically.',self::DOMAIN); ?></div></aside>
<?php }
    public static function field($label,$key,$type,$value,$extra='') {
        $id='fcb-setting-'.$key;
        echo '<label class="fcb-field" for="'.esc_attr($id).'"><span>'.esc_html($label).'</span><input id="'.esc_attr($id).'" data-field="'.esc_attr($key).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'" '.$extra.'></label>';
    }
    public static function check($label,$key,$value,$help='') {
        echo '<label class="fcb-switch-row"><input type="checkbox" data-field="'.esc_attr($key).'" '.checked($value,true,false).'><span><strong>'.esc_html($label).'</strong>'.($help ? '<small>'.esc_html($help).'</small>' : '').'</span></label>';
    }
}
