<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

final class Store {
    const SCHEMA = '2';
    public static function table() { global $wpdb; return $wpdb->prefix . 'fcb_enquiries'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id char(36) NOT NULL,
            contact varchar(254) NOT NULL,
            name varchar(120) NOT NULL DEFAULT '',
            message text NOT NULL,
            created_at datetime NOT NULL,
            source_url text NOT NULL,
            channel varchar(20) NOT NULL DEFAULT 'callback',
            contact_source varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'new',
            notification varchar(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            UNIQUE KEY submission_id (submission_id),
            KEY status_created (status,created_at),
            KEY created_at (created_at),
            KEY contact (contact(191))
        ) $collate;");
        // Small non-customer keys only; atomic unique keys implement rate and duplicate guards.
        $guards = $wpdb->prefix . 'fcb_guards';
        dbDelta("CREATE TABLE $guards (
            guard_key char(64) NOT NULL,
            expires_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (guard_key),
            KEY expires_at (expires_at)
        ) $collate;");
        if(class_exists(Insights::class)){Insights::install();}
        update_option('fcb_schema', self::SCHEMA, false);
        if (!wp_next_scheduled('fcb_retention')) { wp_schedule_event(time()+HOUR_IN_SECONDS, 'daily', 'fcb_retention'); }
        foreach (['fcb_live','fcb_draft'] as $key) { add_option($key, Settings::defaults(), '', false); }
        if(class_exists(Reports::class)){Reports::reconcile();}
    }
    public static function deactivate() { foreach(['fcb_retention','fcb_weekly_report','fcb_insights_cleanup'] as $hook){wp_clear_scheduled_hook($hook);} delete_option('fcb_report_due'); }
    public static function claim($key, $ttl) {
        global $wpdb;
        $table = $wpdb->prefix . 'fcb_guards';
        $hash = hash_hmac('sha256', $key, wp_salt('nonce'));
        // Delete only this expired key, never scan history on a public form request.
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE guard_key = %s AND expires_at < %d", $hash, time()));
        return 1 === $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (guard_key,expires_at) VALUES (%s,%d)", $hash, time()+$ttl));
    }
    public static function release($key) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'fcb_guards', ['guard_key'=>hash_hmac('sha256', $key, wp_salt('nonce'))]);
    }
    public static function insert($data) {
        global $wpdb;
        $ok = $wpdb->insert(self::table(), $data);
        return $ok ? (int)$wpdb->insert_id : 0;
    }
    public static function status($id, $status) {
        global $wpdb;
        if (!in_array($status, ['new','contacted','resolved'], true)) { return false; }
        return false !== $wpdb->update(self::table(), ['status'=>$status], ['id'=>absint($id)]);
    }
    public static function notification($id, $state) {
        global $wpdb;
        $wpdb->update(self::table(), ['notification'=>$state], ['id'=>absint($id)]);
    }
    public static function query($search = '', $status = '', $page = 1, $limit = 25) {
        global $wpdb;
        $where = '1=1'; $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (contact LIKE %s OR name LIKE %s OR message LIKE %s OR submission_id LIKE %s)';
            array_push($args, $like,$like,$like,$like);
        }
        if (in_array($status, ['new','contacted','resolved'], true)) { $where .= ' AND status = %s'; $args[]=$status; }
        $where = $args ? $wpdb->prepare($where, $args) : $where;
        $table = self::table();
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d", min(500,max(1,$limit)), (max(1,$page)-1)*$limit), ARRAY_A);
        return ['total'=>$total,'rows'=>$rows];
    }
    public static function delete($id) { global $wpdb; return $wpdb->delete(self::table(), ['id'=>absint($id)]); }
    public static function cleanup() {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time()-Settings::live()['retention']*DAY_IN_SECONDS);
        $table = self::table();
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE created_at < %s ORDER BY id LIMIT 500", $cutoff));
        if ($ids) { $wpdb->query("DELETE FROM $table WHERE id IN (" . implode(',',array_map('absint',$ids)) . ')'); }
        $guards = $wpdb->prefix . 'fcb_guards';
        $keys = $wpdb->get_col($wpdb->prepare("SELECT guard_key FROM $guards WHERE expires_at < %d LIMIT 500", time()));
        foreach ($keys as $key) { $wpdb->delete($guards, ['guard_key'=>$key]); }
        if (count($ids) === 500 || count($keys) === 500) { wp_schedule_single_event(time()+300, 'fcb_retention'); }
    }
    public static function csv_cell($value) {
        $value = (string)$value;
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'" . $value : $value;
    }
    public static function privacy_hooks() {
        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            $exporters['fcb'] = ['exporter_friendly_name'=>__('Callback enquiries','floating-chat-buttons-whatsapp-telegram-call'),'callback'=>[self::class,'export_personal']];
            return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            $erasers['fcb'] = ['eraser_friendly_name'=>__('Callback enquiries','floating-chat-buttons-whatsapp-telegram-call'),'callback'=>[self::class,'erase_personal']];
            return $erasers;
        });
        add_action('admin_init', function () {
            if (function_exists('wp_add_privacy_policy_content')) {
                wp_add_privacy_policy_content('Floating Chat Buttons', wp_kses_post(__('<p>Contact forms send the information you provide to the site owner by email. If the private inbox is enabled, your contact details, optional name/message, submission time and source page path are stored for the configured retention period. Short-lived keyed anti-spam guards are used; raw IP addresses are not retained. Clicking a contact link opens the chosen service and does not tell us whether communication succeeded. Review the privacy policy of that service. Deleting local records cannot remove email copies already sent.</p>', 'floating-chat-buttons-whatsapp-telegram-call')));
            }
        });
    }
    public static function export_personal($email, $page = 1) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE contact = %s ORDER BY id LIMIT 100 OFFSET %d', $email, (max(1,$page)-1)*100), ARRAY_A);
        $data = [];
        foreach ($rows as $row) {
            $fields = [];
            foreach ($row as $key=>$value) { if ($key !== 'id') { $fields[]=['name'=>$key,'value'=>$value]; } }
            $data[] = ['group_id'=>'fcb-enquiries','group_label'=>__('Callback enquiries','floating-chat-buttons-whatsapp-telegram-call'),'item_id'=>'fcb-' . $row['submission_id'],'data'=>$fields];
        }
        return ['data'=>$data,'done'=>count($rows)<100];
    }
    public static function erase_personal($email, $page = 1) {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE contact = %s LIMIT 100', $email));
        foreach ($ids as $id) { self::delete($id); }
        return ['items_removed'=>!empty($ids),'items_retained'=>false,'messages'=>[],'done'=>count($ids)<100];
    }
}
