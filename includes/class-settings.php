<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

final class Settings {
    public static function base_defaults() {
        $channels = [];
        foreach ([
            'call'=>['Call now', '#2563eb'], 'whatsapp'=>['WhatsApp', '#15803d'],
            'sms'=>['Send a text', '#7c3aed'], 'telegram'=>['Telegram', '#0284c7'],
            'messenger'=>['Messenger', '#4f46e5'], 'email'=>['Email us', '#334155'],
            'custom'=>['Visit our website', '#0f766e'],
        ] as $key=>$data) {
            $channels[$key] = ['enabled'=>false, 'value'=>'', 'label'=>$data[0], 'color'=>$data[1], 'icon_color'=>'#ffffff'];
        }
        return [
            'enabled'=>false, 'channels'=>$channels, 'preset'=>'call-first', 'layout'=>'expandable',
            'style'=>'circle', 'position'=>'right', 'offset'=>24, 'gap'=>12, 'size'=>56,
            'desktop'=>true, 'mobile'=>true, 'intro'=>'How can we help?', 'launcher'=>'Contact options',
            'callback'=>true, 'form_kind'=>'callback', 'fallback'=>true, 'fallback_title'=>"Couldn't reach us?",
            'fallback_text'=>"Leave your phone number or email and we'll get back to you as soon as possible.",
            'submit_label'=>'Request a Callback', 'name_field'=>false, 'message_field'=>false,
            'inbox'=>true, 'retention'=>30, 'notification_email'=>get_option('admin_email'),
            'privacy_url'=>get_privacy_policy_url(), 'privacy_text'=>'We use the details you submit to respond to your enquiry.',
            'consent'=>true, 'delete_uninstall'=>false,
        ];
    }
    /** Presentation-only patches shared by the wizard and design studio. */
    public static function presets() {
        $designs = [
            'call-first'=>['expandable','circle'],
            'whatsapp-first'=>['expandable','circle'],
            'separate'=>['separate','circle'],
            'multi'=>['expandable','square'],
            'stack'=>['separate','square'],
            // One launcher opens all selected contact methods, regardless of count.
            'minimal'=>['expandable','circle'],
            'pill'=>['separate','pill'],
            'panel'=>['expandable','pill'],
        ];
        $presets = [];
        foreach ($designs as $key=>$design) {
            $view = ['layout'=>$design[0],'direction'=>'vertical','expanded'=>false,'size'=>56,'gap'=>12];
            // An explicit visual allowlist prevents new behavioral settings from
            // accidentally becoming part of a design preset in future releases.
            $presets[$key] = [
                'preset'=>$key,'layout'=>$design[0],'style'=>$design[1],'size'=>56,'gap'=>12,
                'desktop_view'=>$view,'mobile_view'=>array_replace($view,['size'=>50]),
                'panel_style'=>['enabled'=>$key==='panel'],
            ];
        }
        $presets['mobile-bar']=['preset'=>'mobile-bar','mobile_view'=>['layout'=>'bar']];
        return $presets;
    }
    public static function defaults() { return Configuration::defaults(self::base_defaults()); }
    public static function draft() { return self::merge(get_option('fcb_draft', [])); }
    public static function live() { return self::merge(get_option('fcb_live', [])); }
    private static function merge($value) {
        return Configuration::merge(self::defaults(), Configuration::legacy(is_array($value) ? $value : []));
    }
    public static function contact($value) {
        if (!is_string($value) || strlen($value)>254) { return ''; }
        $value = trim($value);
        if (is_email($value)) { return sanitize_email($value); }
        if (!preg_match('/^\+?[0-9() .-]{7,30}$/D', $value)) { return ''; }
        $digits = preg_replace('/[^0-9]/', '', $value);
        if (strlen($digits)<7 || strlen($digits)>15) { return ''; }
        return (strpos($value, '+') === 0 ? '+' : '') . $digits;
    }
    public static function destination($type, $value) {
        if (!is_string($value)) { return ''; }
        $value = trim($value);
        if (in_array($type, ['call','sms','whatsapp'], true)) {
            $phone = self::contact($value);
            if (!$phone || strpos($phone, '@') !== false) { return ''; }
            if ($type === 'whatsapp') { return 'https://wa.me/' . ltrim($phone, '+'); }
            return ($type === 'call' ? 'tel:' : 'sms:') . $phone;
        }
        if ($type === 'email') { return is_email($value) ? 'mailto:' . sanitize_email($value) : ''; }
        if ($type === 'telegram') {
            $user = ltrim($value, '@');
            return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/D', $user) ? 'https://t.me/' . $user : '';
        }
        if ($type === 'messenger') {
            return preg_match('/^[a-zA-Z0-9.]{3,100}$/D', $value) ? 'https://m.me/' . $value : '';
        }
        if ($type === 'custom' && filter_var($value, FILTER_VALIDATE_URL) && strtolower((string) wp_parse_url($value, PHP_URL_SCHEME)) === 'https') {
            if (wp_parse_url($value, PHP_URL_USER) || wp_parse_url($value, PHP_URL_PASS)) { return ''; }
            return esc_url_raw($value, ['https']);
        }
        return '';
    }
    public static function sanitize($input, $publishing = true) {
        if (!is_array($input)) { return new \WP_Error('invalid', __('Invalid configuration.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        $out = self::defaults();
        foreach (['enabled','desktop','mobile','callback','fallback','name_field','message_field','inbox','consent','delete_uninstall'] as $key) { $out[$key] = array_key_exists($key,$input) ? !empty($input[$key]) : $out[$key]; }
        foreach (['form_kind'=>['callback','enquiry'], 'layout'=>['separate','expandable'], 'style'=>['circle','square','pill','outline'], 'position'=>['left','right','center'], 'preset'=>['call-first','whatsapp-first','separate','multi','stack','minimal','pill','panel','mobile-bar']] as $key=>$allowed) {
            if (isset($input[$key]) && in_array($input[$key], $allowed, true)) { $out[$key] = $input[$key]; }
        }
        foreach (['offset'=>[0,200], 'gap'=>[4,32], 'size'=>[40,80], 'retention'=>[1,365]] as $key=>$range) {
            $out[$key] = max($range[0], min($range[1], (int)($input[$key] ?? $out[$key])));
        }
        foreach (['intro'=>100,'launcher'=>60,'fallback_title'=>100,'fallback_text'=>300,'submit_label'=>80,'privacy_text'=>500] as $key=>$max) {
            if (isset($input[$key]) && is_string($input[$key])) { $out[$key] = mb_substr(sanitize_text_field($input[$key]), 0, $max); }
        }
        foreach (['launcher','fallback_title','submit_label','privacy_text'] as $key) {
            if ($out[$key] === '') { $out[$key] = self::defaults()[$key]; }
        }
        $out['notification_email'] = isset($input['notification_email']) && is_string($input['notification_email']) ? sanitize_email($input['notification_email']) : '';
        $out['privacy_url'] = isset($input['privacy_url']) && is_string($input['privacy_url']) ? esc_url_raw($input['privacy_url'], ['https','http']) : '';
        $count = 0;
        $order = [];
        if (isset($input['channels']) && is_array($input['channels'])) {
            foreach ($input['channels'] as $key=>$channel) {
                if (!isset($out['channels'][$key]) || !is_array($channel)) { continue; }
                $c = $out['channels'][$key];
                $c['enabled'] = !empty($channel['enabled']);
                foreach (['value'=>500,'label'=>60] as $field=>$max) {
                    if (isset($channel[$field]) && is_string($channel[$field])) { $c[$field] = mb_substr(sanitize_text_field($channel[$field]), 0, $max); }
                }
                if ($c['label'] === '') { $c['label'] = $out['channels'][$key]['label']; }
                foreach (['color','icon_color'] as $field) { $c[$field] = sanitize_hex_color($channel[$field] ?? '') ?: $c[$field]; }
                if ($c['enabled']) {
                    $count++;
                    if ($publishing && !self::destination($key, $c['value'])) { return new \WP_Error('destination', sprintf(__('Enter valid contact details for %s.', 'floating-chat-buttons-whatsapp-telegram-call'), $c['label']), ['field'=>'channels.'.$key.'.value']); }
                }
                $order[$key] = $c;
            }
        }
        $out['channels'] = $order + $out['channels'];
        if ($publishing && $out['enabled'] && !$count) { return new \WP_Error('channels', __('Choose at least one contact method.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        if ($publishing && $out['enabled'] && !$out['desktop'] && !$out['mobile']) { return new \WP_Error('visibility', __('Choose desktop or mobile visibility.', 'floating-chat-buttons-whatsapp-telegram-call')); }
        if ($publishing && $out['callback'] && !is_email($out['notification_email'])) { return new \WP_Error('recipient', __('Enter a valid notification email.', 'floating-chat-buttons-whatsapp-telegram-call'), ['field'=>'notification_email']); }
        return Configuration::sanitize($input,$out,$publishing);
    }
}
