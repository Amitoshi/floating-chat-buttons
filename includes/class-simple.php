<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** An administrator's interface preference is separate from the widget draft. */
final class Simple {
    const META_KEY='fcb_interface_mode';
    public static function boot() { add_action('wp_ajax_fcb_interface',[self::class,'ajax']); }
    public static function mode() { return get_user_meta(get_current_user_id(),self::META_KEY,true)==='advanced' ? 'advanced' : 'simple'; }
    public static function update($mode) {
        if (!current_user_can('manage_options')) { return new \WP_Error('forbidden','Administrator access is required.'); }
        if (!in_array($mode,['simple','advanced'],true)) { return new \WP_Error('interface_mode','Choose Simple or Advanced settings.'); }
        update_user_meta(get_current_user_id(),self::META_KEY,$mode);
        return $mode;
    }
    public static function ajax() {
        if (!current_user_can('manage_options') || !check_ajax_referer('fcb_admin','nonce',false)) { wp_send_json_error(['message'=>'Administrator access and a valid nonce are required.'],403); }
        $mode=isset($_POST['mode']) && is_string($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : '';
        $result=self::update($mode);
        if (is_wp_error($result)) { wp_send_json_error(['message'=>$result->get_error_message()],400); }
        wp_send_json_success(['mode'=>$result,'message'=>$result==='advanced'?'Advanced settings shown. Your contact settings are unchanged.':'Simple settings shown. Your advanced features remain active.']);
    }
    public static function order_controls($key,$label) {
        echo '<div class="fcb-button-order"><span data-order-number="'.esc_attr($key).'"></span><div><button type="button" class="fcb-reorder" data-move="-1" data-key="'.esc_attr($key).'" aria-label="'.esc_attr('Move '.$label.' up').'">↑</button><button type="button" class="fcb-reorder" data-move="1" data-key="'.esc_attr($key).'" aria-label="'.esc_attr('Move '.$label.' down').'">↓</button></div></div>';
    }
    public static function controls($c) {
        ?>
        <section class="fcb-card-admin fcb-simple-controls" aria-label="Quick Setup">
            <p class="fcb-eyebrow">CONTACT DETAILS → ARRANGE BUTTONS → PREVIEW → PUBLISH</p>
            <h2>Contact details</h2>
            <?php foreach (['call'=>'Call','whatsapp'=>'WhatsApp'] as $key=>$name) : ?>
            <div class="fcb-quick-contact"><label><input type="checkbox" data-channel="<?php echo esc_attr($key); ?>" data-property="enabled"<?php checked($c['channels'][$key]['enabled']); ?>> Enable <?php echo esc_html($name); ?></label><label class="fcb-field"><span><?php echo esc_html($name==='Call'?'Phone number':'WhatsApp number'); ?></span><input type="tel" inputmode="tel" data-channel="<?php echo esc_attr($key); ?>" data-property="value" value="<?php echo esc_attr($c['channels'][$key]['value']); ?>" placeholder="+1 212 555 0100" maxlength="30"></label></div>
            <?php endforeach; ?>
            <p class="fcb-hint">Include the country code. Edit labels and appearance with the pencil below.</p>
            <?php Admin::check('Enable Request a Callback','callback',$c['callback']); ?>
            <div data-simple-callback-fields><?php Admin::field('Callback notification email','notification_email','email',$c['notification_email'],'maxlength="254"'); ?><p class="fcb-hint" data-privacy-summary></p><a href="admin.php?page=fcb-advanced" data-focused-route>Notification and privacy settings</a></div>
            <aside class="fcb-simple-extra" data-simple-extra-summary hidden><p></p><a href="admin.php?page=fcb-buttons" data-focused-route>Edit additional channels in Advanced Mode</a></aside>
            <hr><h2>Arrange buttons</h2>
            <div data-quick-editor></div>
            <details class="fcb-quick-options"><summary>Layout and position</summary><p class="fcb-hint">Applies to the device selected in the preview. Existing custom settings remain until you change them.</p><label class="fcb-field"><span>Button layout</span><select data-quick-layout><option value="expandable">Contact menu</option><option value="separate">Direct buttons</option><option value="bar">Mobile contact bar</option><option value="custom" disabled>Custom grouped layout</option></select></label><label class="fcb-field"><span>Widget position</span><select data-quick-position><option value="right">Bottom right</option><option value="left">Bottom left</option><option value="center">Bottom center</option></select></label><p class="fcb-hint">Use the Move widget handle for precise positioning. Mobile bars stay docked.</p></details>
            <div class="fcb-component-actions" aria-label="Edit widget components"><button type="button" class="fcb-button fcb-button-light" data-component-edit="launcher">Edit Launcher</button><button type="button" class="fcb-button fcb-button-light" data-component-edit="bubble">Edit Introductory Bubble</button><button type="button" class="fcb-button fcb-button-light" data-component-edit="callback">Edit Callback</button></div>
            <p class="fcb-hint">Preview your changes, then Publish. Save draft keeps the public website unchanged.</p>
        </section>
        <?php
    }
}
