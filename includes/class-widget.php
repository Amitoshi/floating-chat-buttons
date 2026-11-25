<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

final class Widget {
    public static function boot() {
        add_action('wp_enqueue_scripts',function(){
            if(is_admin() || ($GLOBALS['pagenow']??'')==='wp-login.php' || is_feed() || is_embed()){return;}
            $c=Settings::live(); $floating=self::eligible(); $recovery=Configuration::recovery_allowed($c);
            if(!$floating&&!$recovery){return;}
            $forms=$c['callback']&&($floating||($recovery&&(!empty($c['recovery']['prompt'])||!empty($c['recovery']['followup']['enabled']))));
            self::assets($forms,$c,$recovery);
            if($floating||$forms){add_action('wp_footer',function()use($c,$floating){self::render($c,false,!$floating);},5);}
        });
    }
    public static function eligible() {
        $c=Settings::live();
        if(!$c['enabled'] || is_admin() || ($GLOBALS['pagenow']??'')==='wp-login.php' || is_feed() || is_embed() || (!$c['desktop']&&!$c['mobile'])){return false;}
        if(class_exists(Rules::class)&&!Rules::evaluate($c)['visible']){return false;}
        foreach($c['channels'] as $key=>$ch){if($ch['enabled']&&Settings::destination($key,$ch['value'])){return true;}}
        return false;
    }
    public static function assets($forms=true,$c=null,$recovery=false) {
        $c=$c??Settings::live();
        wp_enqueue_style('fcb-widget',FCB_URL.'assets/widget.css',[],FCB_VERSION);
        wp_enqueue_style('fcb-layouts',FCB_URL.'assets/layouts.css',['fcb-widget'],FCB_VERSION);
        wp_enqueue_script('fcb-layouts',FCB_URL.'assets/layouts.js',[],FCB_VERSION,true);
        wp_enqueue_style('fcb-button-effects',FCB_URL.'assets/button-effects.css',['fcb-widget'],FCB_VERSION);
        wp_enqueue_script('fcb-button-effects',FCB_URL.'assets/button-effects.js',[],FCB_VERSION,true);
        wp_enqueue_script('fcb-widget',FCB_URL.'assets/widget.js',['fcb-layouts','fcb-button-effects'],FCB_VERSION,true);
        $icons=[];foreach(['whatsapp','call','sms','telegram','messenger','email','custom','chat','contact','close'] as $key){$icons[$key]=self::icon($key);}
        wp_localize_script('fcb-widget','FCBAssets',['icons'=>$icons,'timezone'=>wp_timezone_string()?:'UTC']);
        $tracking=!is_admin()&&(!empty($c['analytics'])||!empty($c['ga4'])||!empty($c['recovery']['enabled'])||!empty($c['recovery']['followup']['enabled']));
        if($tracking){
            wp_enqueue_script('fcb-interactions',FCB_URL.'assets/interactions.js',['fcb-widget'],FCB_VERSION,true);
            wp_localize_script('fcb-interactions','FCBPublic',[
                'url'=>admin_url('admin-ajax.php'),'analytics'=>$c['analytics'],'analytics_consent'=>$c['analytics_consent'],
                'ga4'=>$c['ga4'],'ga4_mode'=>$c['ga4_mode'],'ga4_debug'=>$c['ga4_debug'],
                'recovery'=>$c['recovery'],'recoveryAllowed'=>(bool)$recovery,
            ]);
        }
        if(!is_admin()&&!empty($c['recovery']['followup']['enabled'])){
            wp_enqueue_style('fcb-followup',FCB_URL.'assets/followup.css',['fcb-widget','fcb-layouts'],FCB_VERSION);
            wp_enqueue_script('fcb-followup',FCB_URL.'assets/followup.js',['fcb-widget','fcb-interactions'],FCB_VERSION,true);
            wp_localize_script('fcb-followup','FCBFollowup',['config'=>self::public_config($c),'pageAllowed'=>(bool)$recovery]);
        }
        if($forms){
            wp_enqueue_script('fcb-forms',FCB_URL.'assets/forms.js',$tracking?['fcb-widget','fcb-interactions']:['fcb-widget'],FCB_VERSION,true);
            wp_localize_script('fcb-forms','FCBForm',[
                'url'=>admin_url('admin-ajax.php'),
                'invalid'=>__('Enter a valid phone number or email address.','floating-chat-buttons-whatsapp-telegram-call'),
                'consent'=>__('Please agree to using your details to respond.','floating-chat-buttons-whatsapp-telegram-call'),
                'sending'=>__('Sending…','floating-chat-buttons-whatsapp-telegram-call'),
                'network'=>__('We could not confirm submission. Please check your connection and try again.','floating-chat-buttons-whatsapp-telegram-call'),
                'preview'=>__('Preview only — nothing was submitted.','floating-chat-buttons-whatsapp-telegram-call'),
            ]);
        }
    }
    public static function css_vars($c) {
        $v=[];
        foreach(['desktop_view'=>'d','mobile_view'=>'m'] as $key=>$prefix){
            foreach(['x','y','size','icon_size','gap'] as $field){$v['--fcb-'.$prefix.'-'.str_replace('_','-',$field)]=(int)$c[$key][$field].'px';}
        }
        foreach(['callback_style'=>'cb','launcher_style'=>'launcher','form_style'=>'form'] as $key=>$prefix){
            foreach($c[$key] as $field=>$value){
                if(in_array($field,['background','text','icon_color','border_color','hover_color','focus_color','button_background','button_text'],true)){$v['--fcb-'.$prefix.'-'.str_replace('_','-',$field)]=$value;}
                elseif(in_array($field,['radius','border_width','padding_x','padding_y','gap','margin','width','height'],true)){$v['--fcb-'.$prefix.'-'.str_replace('_','-',$field)]=(int)$value.'px';}
            }
        }
        $v['--fcb-size']=(int)$c['size'].'px';$v['--fcb-offset']=(int)$c['offset'].'px';$v['--fcb-gap']=(int)$c['gap'].'px';
        $pairs=[];foreach($v as $k=>$value){$pairs[]=$k.':'.$value;}return implode(';',$pairs);
    }
    public static function icon($name) {
        if($name==='contact'){$name='call';}
        static $cache = [];
        if (in_array($name,['whatsapp','telegram','messenger'],true)) {
            if (!isset($cache[$name])) { $cache[$name] = file_get_contents(FCB_DIR . 'assets/icons/' . $name . '.svg'); }
            return $cache[$name]; // Bundled, immutable SVG assets. No user-supplied SVG.
        }
        $paths = [
            'call'=>'<path d="m7 3 3 4-2 3a15 15 0 0 0 6 6l3-2 4 3c0 3-2 4-4 4C9 21 3 15 3 7c0-2 1-4 4-4Z"/>',
            'sms'=>'<path d="M21 11a8 8 0 0 1-8 8H7l-4 3V5a2 2 0 0 1 2-2h8a8 8 0 0 1 8 8Z"/><path d="M7 8h9M7 12h7"/>',
            'email'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
            'custom'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a20 20 0 0 1 0 18 20 20 0 0 1 0-18Z"/>',
            'chat'=>'<path d="M21 11a8 8 0 0 1-8 8H7l-4 3V5a2 2 0 0 1 2-2h8a8 8 0 0 1 8 8Z"/><path d="M7 9h10M7 13h6"/>',
            'close'=>'<path d="m6 6 12 12M18 6 6 18"/>',
        ];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['chat']) . '</svg>';
    }
    public static function public_config($c,$preview=false) {
        $public=array_intersect_key($c,array_flip(['intro_style','quick_animation','enabled','mobile_bar','layout','style','position','offset','gap','size','desktop','mobile','callback','form_kind','fallback','intro','launcher','fallback_title','fallback_text','submit_label','name_field','message_field','privacy_url','privacy_text','consent','preset','desktop_view','mobile_view','callback_style','launcher_style','form_style','tooltips','intro_enabled','recovery','callback_schedule','panel_style','animations','intro_behavior']));
        $public['channels']=[];
        foreach($c['channels'] as $key=>$ch){
            $public['channels'][$key]=array_intersect_key($ch,array_flip(['enabled','value','label','color','icon_color','appearance','schedule']));
            if(!$preview&&!$ch['enabled']){$public['channels'][$key]['value']='';}
        }
        return $public;
    }
    public static function render($c, $preview = false, $recovery_only = false) {
        $id = $preview ? 'fcb-preview' : 'fcb-live';
        $c=class_exists(Schedule::class)?Schedule::apply($c):$c;
        $public=self::public_config($c,$preview);
        $style=self::css_vars($c);
        ?>
        <aside id="<?php echo esc_attr($id); ?>" class="fcb-widget fcb-<?php echo esc_attr($c['position']); ?> fcb-style-<?php echo esc_attr($c['style']); ?> fcb-layout-<?php echo esc_attr($c['layout']); ?><?php echo !$c['desktop'] ? ' fcb-no-desktop' : ''; ?><?php echo !$c['mobile'] ? ' fcb-no-mobile' : ''; ?>"
            style="<?php echo esc_attr($style); ?>" data-preview="<?php echo $preview ? '1' : '0'; ?>" data-recovery-only="<?php echo $recovery_only ? '1' : '0'; ?>" data-desktop-position="<?php echo esc_attr($c['desktop_view']['position']); ?>" data-mobile-position="<?php echo esc_attr($c['mobile_view']['position']); ?>" data-config="<?php echo esc_attr(wp_json_encode($public)); ?>" aria-label="<?php esc_attr_e('Contact options', 'floating-chat-buttons-whatsapp-telegram-call'); ?>">
            <p class="fcb-intro"<?php echo !$c['intro'] ? ' hidden' : ''; ?>><?php echo esc_html($c['intro']); ?></p>
            <div class="fcb-choices" id="<?php echo esc_attr($id); ?>-choices">
                <div class="fcb-choice-links">
                <?php foreach ($c['channels'] as $key=>$ch) :
                    $state=$ch['schedule_state']??['action'=>'direct','message'=>'','alternative'=>''];
                    $action=$state['action'];$target=$action==='alternative'?($state['alternative']??$key):$key;
                    $destination=$c['channels'][$target]['value']??$ch['value'];
                    $url=in_array($action,['callback','unavailable'],true)?'#':Settings::destination($target,$destination);
                    $icon=$ch['appearance']['icon']??'default';$icon=$icon==='default'?$key:$icon; ?>
                    <a class="fcb-channel" data-channel="<?php echo esc_attr($key); ?>" data-action="<?php echo esc_attr($action); ?>" data-actual-channel="<?php echo esc_attr($target); ?>" data-message="<?php echo esc_attr($state['message']??''); ?>" href="<?php echo esc_url($url ?: '#', ['https','mailto','tel','sms']); ?>" <?php echo !$ch['enabled'] || !$url || $action==='hide' ? 'hidden' : ''; ?>
                        style="<?php echo esc_attr('--fcb-color:' . $ch['color'] . ';--fcb-icon:' . $ch['icon_color']); ?>"<?php echo in_array($key,['whatsapp','telegram','messenger','custom'],true) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php echo self::icon($icon); ?><span><?php echo esc_html($ch['label']); ?></span>
                    </a>
                <?php endforeach; ?>
                </div>
                <button type="button" class="fcb-callback-link" data-fcb="callback"<?php echo !$c['callback'] || ($c['callback_state']['action']??'direct')==='hide' ? ' hidden' : ''; ?>><span class="fcb-callback-icon"><?php echo $c['callback_style']['icon']==='none'?'':self::icon($c['callback_style']['icon']); ?></span><span class="fcb-callback-label"><?php echo esc_html($c['submit_label']); ?></span></button>
            </div>
            <p class="fcb-channel-notice" role="status" aria-live="polite" hidden></p>
            <button type="button" class="fcb-launcher" data-fcb="toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>-choices"><span class="fcb-launcher-icon"><?php echo $c['launcher_style']['icon']==='none'?'':self::icon($c['launcher_style']['icon']); ?></span><span><?php echo esc_html($c['launcher']); ?></span></button>
            <?php if ($c['callback'] || $preview) : ?>
            <section class="fcb-card" role="dialog" aria-labelledby="<?php echo esc_attr($id); ?>-title" hidden>
                <button class="fcb-close" type="button" data-fcb="close" aria-label="<?php esc_attr_e('Close contact form', 'floating-chat-buttons-whatsapp-telegram-call'); ?>"><?php echo self::icon('close'); ?></button>
                <span class="fcb-card-eyebrow"><?php esc_html_e('LET’S KEEP IN TOUCH', 'floating-chat-buttons-whatsapp-telegram-call'); ?></span>
                <h2 id="<?php echo esc_attr($id); ?>-title"><?php echo esc_html($c['fallback_title']); ?></h2>
                <p class="fcb-card-copy"><?php echo esc_html($c['fallback_text']); ?></p>
                <form class="fcb-form" novalidate>
                    <label for="<?php echo esc_attr($id); ?>-contact"><?php esc_html_e('Phone number or email', 'floating-chat-buttons-whatsapp-telegram-call'); ?></label>
                    <input id="<?php echo esc_attr($id); ?>-contact" name="contact" type="text" autocomplete="email" required maxlength="254" aria-describedby="<?php echo esc_attr($id); ?>-feedback">
                    <div class="fcb-optional-name"<?php echo !$c['name_field'] ? ' hidden' : ''; ?>><label for="<?php echo esc_attr($id); ?>-name"><?php esc_html_e('Name (optional)', 'floating-chat-buttons-whatsapp-telegram-call'); ?></label><input id="<?php echo esc_attr($id); ?>-name" name="name" autocomplete="name" maxlength="120"></div>
                    <div class="fcb-optional-message"<?php echo !$c['message_field'] ? ' hidden' : ''; ?>><label for="<?php echo esc_attr($id); ?>-message"><?php esc_html_e('Message (optional)', 'floating-chat-buttons-whatsapp-telegram-call'); ?></label><textarea id="<?php echo esc_attr($id); ?>-message" name="message" maxlength="2000" rows="3"></textarea></div>
                    <div class="fcb-honeypot" aria-hidden="true"><label><?php esc_html_e('Leave this empty', 'floating-chat-buttons-whatsapp-telegram-call'); ?><input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <p class="fcb-privacy"><?php echo esc_html($c['privacy_text']); ?> <a href="<?php echo esc_url($c['privacy_url']); ?>" target="_blank" rel="noopener noreferrer"<?php echo !$c['privacy_url'] ? ' hidden' : ''; ?>><?php esc_html_e('Privacy policy', 'floating-chat-buttons-whatsapp-telegram-call'); ?></a></p>
                    <label class="fcb-consent"<?php echo !$c['consent'] ? ' hidden' : ''; ?>><input type="checkbox" name="consent" value="1"> <span><?php esc_html_e('I agree to using my details to respond to this enquiry.', 'floating-chat-buttons-whatsapp-telegram-call'); ?></span></label>
                    <button type="submit" class="fcb-submit"><?php echo esc_html($c['submit_label']); ?></button>
                    <p id="<?php echo esc_attr($id); ?>-feedback" class="fcb-feedback" role="status" aria-live="polite" tabindex="-1"></p>
                </form>
                <div class="fcb-alternatives"></div>
            </section>
            <?php endif; ?>
        </aside>
        <?php
    }
}
