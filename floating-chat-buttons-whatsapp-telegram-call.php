<?php
/**
 * Plugin Name: Floating Chat Buttons – WhatsApp, Call & Callback
 * Description: Lightweight contact buttons, honest fallback journeys, and a private callback inbox. All features free.
 * Version: 0.2.3.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Amit Jyoti Shaw
 * Author URI: https://github.com/Amitoshi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: floating-chat-buttons-whatsapp-telegram-call
 */
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;
define('FCB_VERSION', '0.2.3.1');
define('FCB_FILE', __FILE__);
define('FCB_DIR', plugin_dir_path(__FILE__));
define('FCB_URL', plugin_dir_url(__FILE__));
foreach (['configuration', 'settings', 'store', 'rules', 'schedule', 'insights', 'reports', 'submissions', 'widget'] as $module) {
    $path = FCB_DIR . 'includes/class-' . $module . '.php';
    if (file_exists($path)) { require_once $path; }
}
register_activation_hook(__FILE__, [Store::class, 'install']);
register_deactivation_hook(__FILE__, [Store::class, 'deactivate']);
add_action('fcb_retention', [Store::class, 'cleanup']);
add_action('fcb_retention', [Insights::class, 'cleanup']);
add_action('plugins_loaded', function () {
    // Upgrade only when required; the current schema performs no page-view writes.
    if (get_option('fcb_schema') !== Store::SCHEMA) { Store::install(); }
    Insights::boot(); Reports::boot();
    if (class_exists(Submissions::class)) { Submissions::boot(); }
    if (class_exists(Widget::class)) { Widget::boot(); }
    if (is_admin() || current_user_can('manage_options')) {
        require_once FCB_DIR . 'includes/class-admin.php';
        add_action('admin_bar_menu', [Admin::class,'toolbar'], 90);
        if (is_admin()) { Admin::boot(); }
    }
    Store::privacy_hooks();
});
