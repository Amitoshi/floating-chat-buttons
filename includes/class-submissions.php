<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

final class Submissions {
    public static function boot() {
        foreach (['wp_ajax_','wp_ajax_nopriv_'] as $prefix) {
            add_action($prefix . 'fcb_nonce', [self::class,'nonce']);
            add_action($prefix . 'fcb_submit', [self::class,'submit']);
        }
    }
    public static function nonce() {
        nocache_headers();
        $c=Settings::live();$forms=$c['callback']&&($c['enabled']||(!empty($c['recovery']['enabled'])&&!empty($c['recovery']['prompt'])));
        if(!$forms&&!$c['analytics']){wp_send_json_error(['message'=>__('Contact form is unavailable.','floating-chat-buttons-whatsapp-telegram-call')],403);}
        wp_send_json_success(['nonce'=>$forms?wp_create_nonce('fcb_submit'):'','event_nonce'=>$c['analytics']?wp_create_nonce('fcb_event'):'']);
    }
    public static function submit() {
        nocache_headers();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_send_json_error(['message'=>__('Use the contact form to submit a request.', 'floating-chat-buttons-whatsapp-telegram-call')],405); }
        if (!isset($_POST['nonce']) || !is_string($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fcb_submit')) {
            wp_send_json_error(['message'=>__('Your form expired. Please try again.', 'floating-chat-buttons-whatsapp-telegram-call')],403);
        }
        $raw=wp_unslash($_POST);
        $result = self::process($raw, $_SERVER['REMOTE_ADDR'] ?? '');
        if (is_wp_error($result)) {
            if(in_array($result->get_error_code(),['invalid','contact','consent','handling'],true)){self::event($raw,'form_failure');}
            $status = $result->get_error_code() === 'rate' ? 429 : ($result->get_error_code() === 'handling' ? 503 : 400);
            wp_send_json_error(['message'=>$result->get_error_message(),'field'=>in_array($result->get_error_code(),['contact','consent'],true)?$result->get_error_code():''], $status);
        }
        wp_send_json_success($result);
    }
    private static function error($code, $message) { return new \WP_Error($code, $message); }
    public static function process($raw, $ip) {
        $c = Settings::live();
        if ((!$c['enabled'] && (empty($c['recovery']['enabled']) || empty($c['recovery']['prompt']))) || !$c['callback']) { return self::error('disabled', __('This contact form is unavailable.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $callback=Schedule::state(['enabled'=>$c['callback'],'schedule'=>$c['callback_schedule']]);if(!$callback['open']){return self::error('closed',$callback['message']?:__('Callback requests are currently unavailable.','floating-chat-buttons-whatsapp-telegram-call'));}
        foreach (['contact'=>254,'name'=>120,'message'=>2000,'source'=>2000,'website'=>200,'consent'=>5,'kind'=>20,'origin'=>20,'contact_source'=>20,'event_id'=>36,'analytics_consent'=>5] as $key=>$max) {
            if (isset($raw[$key]) && (!is_string($raw[$key]) || mb_strlen($raw[$key])>$max)) { return self::error('invalid', __('Please check the form fields and their lengths.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        }
        if (!empty($raw['website'])) { return self::error('spam', __('Unable to accept this request.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $contact = Settings::contact($raw['contact'] ?? '');
        if (!$contact) { return self::error('contact', __('Enter a valid phone number or email address.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        if ($c['consent'] && empty($raw['consent'])) { return self::error('consent', __('Please agree to using your details to respond.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $name = $c['name_field'] ? sanitize_text_field($raw['name'] ?? '') : '';
        $message = $c['message_field'] ? sanitize_textarea_field($raw['message'] ?? '') : '';
        $kind = ($raw['kind'] ?? '') === 'enquiry' ? 'enquiry' : 'callback';
        $source = self::source($raw['source'] ?? '');
        if($source&&class_exists(Rules::class)){$context=Rules::context_for_url($raw['source']);if(!is_wp_error($context)){ $allowed=($raw['origin']??'')==='recovery'?Configuration::recovery_allowed($c,$context):($c['enabled']&&Rules::evaluate($c,$context)['visible']);if(!$allowed){return self::error('disabled',__('This form is unavailable on this page.','floating-chat-buttons-whatsapp-telegram-call'));}}}
        // No raw IP, contact or message in guard keys. Guards expire in one day or less.
        $duplicate = 'duplicate:' . strtolower($contact) . ':' . $message . ':' . $kind;
        if (!Store::claim($duplicate, 10*MINUTE_IN_SECONDS)) { return self::error('duplicate', __('This request was already submitted recently. Please wait before sending again.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $contact_key = 'contact:' . strtolower($contact);
        $ip_key = 'ip:' . gmdate('Y-m-d') . ':' . $ip;
        if (!Store::claim($contact_key, 5*MINUTE_IN_SECONDS) || !Store::claim($ip_key, MINUTE_IN_SECONDS)) {
            Store::release($duplicate);
            return self::error('rate', __('Please wait a few minutes before sending another request.', 'floating-chat-buttons-whatsapp-telegram-call'));
        }
        $slot = false;
        // Hard site cap: 30 accepted attempts per minute, shared atomically across requests.
        for ($i=0; $i<30; $i++) {
            if (Store::claim('site:' . gmdate('YmdHi') . ':' . $i, 2*MINUTE_IN_SECONDS)) { $slot=true; break; }
        }
        if (!$slot) { Store::release($duplicate); return self::error('rate', __('The form is busy. Please try again shortly.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $data = ['submission_id'=>wp_generate_uuid4(),'contact'=>$contact,'name'=>$name,'message'=>$message,'created_at'=>gmdate('Y-m-d H:i:s'),'source_url'=>$source,'channel'=>$kind,'contact_source'=>in_array($raw['contact_source']??'',array_keys($c['channels']),true)?$raw['contact_source']:'','status'=>'new','notification'=>'pending'];
        $id = $c['inbox'] ? Store::insert($data) : 0;
        if ($c['inbox'] && !$id) {
            Store::release($duplicate);
            return self::error('handling', __('We could not save your request. Please try again later or use another contact option.', 'floating-chat-buttons-whatsapp-telegram-call'));
        }
        $body = sprintf(__("New contact request\n\nReference: %1\$s\nContact: %2\$s\nName: %3\$s\nMessage: %4\$s\nSubmitted (UTC): %5\$s\nSource: %6\$s\nForm: %7\$s", 'floating-chat-buttons-whatsapp-telegram-call'), $data['submission_id'],$contact,$name,$message,$data['created_at'],$source,$kind);
        if ($id) { $body .= "\n\n" . admin_url('admin.php?page=fcb-inbox'); }
        try { $mail = (bool) wp_mail($c['notification_email'], __('New website contact request', 'floating-chat-buttons-whatsapp-telegram-call'), $body, ['Content-Type: text/plain; charset=UTF-8']); }
        catch (\Throwable $e) { $mail = false; }
        if ($id) { Store::notification($id, $mail ? 'accepted' : 'failed'); }
        if (!$mail && !$id) {
            Store::release($duplicate);
            return self::error('handling', __('Your request could not be accepted for email notification. Please use another contact option or try again later.', 'floating-chat-buttons-whatsapp-telegram-call'));
        }
        self::event($raw,$kind==='enquiry'?'enquiry_received':'callback_received',$data['submission_id']);
        if(!$mail){self::event($raw,'notification_failure',$data['submission_id']);}
        return ['stored'=>(bool)$id,'notified'=>$mail,'message'=>$id ? __('Your request has been saved.', 'floating-chat-buttons-whatsapp-telegram-call') : __('Your request was accepted for email notification. Inbox delivery cannot be confirmed.', 'floating-chat-buttons-whatsapp-telegram-call')];
    }
    private static function event($raw,$event,$fallback='') {
        $c=Settings::live();if(empty($c['analytics'])||!class_exists(Insights::class)){return;}
        $uuid=is_string($raw['event_id']??null)&&wp_is_uuid($raw['event_id'],4)?$raw['event_id']:($fallback?:wp_generate_uuid4());
        $origin=($raw['origin']??'')==='recovery'?'recovery':'widget';
        $kind=($raw['kind']??'')==='enquiry'?'enquiry':'callback';
        Insights::record($event,$kind,$origin,$uuid,null,($raw['analytics_consent']??'')==='1');
    }
    public static function source($url) {
        $source = wp_parse_url($url); $home = wp_parse_url(home_url('/'));
        if (!is_array($source) || strtolower($source['host'] ?? '') !== strtolower($home['host'] ?? '') || ($source['port'] ?? null) !== ($home['port'] ?? null) || !in_array($source['scheme'] ?? '', ['http','https'], true)) { return ''; }
        return esc_url_raw(($home['scheme'] ?? 'https') . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '') . ($source['path'] ?? '/'), ['http','https']);
    }
}
