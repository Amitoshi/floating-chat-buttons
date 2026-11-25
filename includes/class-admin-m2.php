<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Advanced editor controls. All data endpoints are private administrative actions. */
final class M2Admin {
    const DOMAIN='floating-chat-buttons-whatsapp-telegram-call';
    public static function boot() {
        foreach (['lookup','explain','config','insights'] as $mode) { add_action('wp_ajax_fcb_m2_'.$mode,[self::class,'ajax_'.$mode]); }
    }
    private static function authorize() {
        if (!current_user_can('manage_options') || !check_ajax_referer('fcb_admin','nonce',false)) { wp_send_json_error(['message'=>'Administrator access and a valid nonce are required.'],403); }
    }
    private static function post($key,$max=150000) { return isset($_POST[$key]) && is_string($_POST[$key]) ? mb_substr(wp_unslash($_POST[$key]),0,$max) : ''; }
    public static function ajax_lookup() {
        self::authorize(); $kind=self::post('kind',40); $query=self::post('query',100);
        if ($kind==='content') {
            $items=[]; $posts=get_posts(['post_type'=>array_values(get_post_types(['public'=>true])),'post_status'=>'publish','s'=>$query,'posts_per_page'=>30,'orderby'=>'title','order'=>'ASC','suppress_filters'=>false]);
            foreach ($posts as $post) { $items[]=['id'=>(int)$post->ID,'label'=>get_the_title($post).' ('.get_post_type_object($post->post_type)->labels->singular_name.')']; }
        } else { $items=Rules::lookup($kind,$query); }
        wp_send_json_success(['items'=>$items]);
    }
    public static function ajax_explain() {
        self::authorize(); $config=Settings::sanitize(json_decode(self::post('config'),true),false);
        if (is_wp_error($config)) { wp_send_json_error(['message'=>$config->get_error_message()],400); }
        $context=Rules::context_for_url(self::post('url',2048));
        if (is_wp_error($context)) { wp_send_json_error(['message'=>$context->get_error_message()],400); }
        wp_send_json_success(Rules::evaluate($config,$context));
    }
    public static function ajax_config() {
        self::authorize(); $mode=self::post('mode',20);
        if ($mode==='import') { $config=Configuration::import(self::post('json')); }
        elseif ($mode==='export') {
            $config=Settings::sanitize(json_decode(self::post('config'),true),false);
            if (!is_wp_error($config)) { $config=Configuration::export($config,self::post('destinations',1)==='1'); }
        } else { wp_send_json_error(['message'=>'Choose import or export.'],400); }
        if (is_wp_error($config)) { wp_send_json_error(['message'=>$config->get_error_message()],400); }
        wp_send_json_success(['config'=>$config]);
    }
    public static function ajax_insights() {
        self::authorize(); $start=self::post('start',10); $end=self::post('end',10);
        $a=\DateTimeImmutable::createFromFormat('!Y-m-d',$start,wp_timezone()); $b=\DateTimeImmutable::createFromFormat('!Y-m-d',$end,wp_timezone());
        if (!$a || !$b || $a->format('Y-m-d')!==$start || $b->format('Y-m-d')!==$end || $a>$b || $b->getTimestamp()-$a->getTimestamp()>365*DAY_IN_SECONDS) { wp_send_json_error(['message'=>'Choose a valid date range of at most 366 days.'],400); }
        ob_start(); self::metrics(Insights::summary($start,$end)); $html=ob_get_clean();
        wp_send_json_success(['html'=>$html,'message'=>'Showing '.$start.' to '.$end.' in '.wp_timezone_string().'.']);
    }
    private static function value($c,$path) { foreach (explode('.',$path) as $key) { $c=is_array($c) ? ($c[$key]??'') : ''; } return $c; }
    private static function field($c,$path,$label,$type='text',$extra='') {
        $value=self::value($c,$path); if (is_array($value)) { $value=implode("\n",$value); }
        echo '<label class="fcb-field"><span>'.esc_html($label).'</span>';
        if ($type==='textarea') { echo '<textarea data-path="'.esc_attr($path).'" rows="3" '.$extra.'>'.esc_textarea($value).'</textarea>'; }
        else { echo '<input type="'.esc_attr($type).'" data-path="'.esc_attr($path).'" value="'.esc_attr($value).'" '.$extra.'>'; }
        echo '</label>';
    }
    private static function choice($c,$path,$label,$options) {
        echo '<label class="fcb-field"><span>'.esc_html($label).'</span><select data-path="'.esc_attr($path).'">';
        foreach ($options as $key=>$text) { echo '<option value="'.esc_attr($key).'" '.selected(self::value($c,$path),$key,false).'>'.esc_html($text).'</option>'; } echo '</select></label>';
    }
    private static function check($c,$path,$label,$help='') { echo '<label class="fcb-switch-row"><input type="checkbox" data-path="'.esc_attr($path).'" '.checked((bool)self::value($c,$path),true,false).'><span><strong>'.esc_html($label).'</strong>'.($help?'<small>'.esc_html($help).'</small>':'').'</span></label>'; }
    private static function section($title,$help='') { echo '<section class="fcb-card-admin fcb-m2"><h2>'.esc_html($title).'</h2>'.($help?'<p class="fcb-m2-help">'.esc_html($help).'</p>':''); }
    private static function accordion($title,$help) { echo '<details class="fcb-details"><summary><strong>'.esc_html($title).'</strong><small>'.esc_html($help).'</small></summary><div class="fcb-details-body">'; }
    private static function reset($section,$key='') { echo '<button type="button" class="fcb-text-button fcb-section-reset" data-reset-section="'.esc_attr($section).'"'.($key?' data-key="'.esc_attr($key).'"':'').'>'.esc_html__('Reset this section',self::DOMAIN).'</button>'; }
    private static function labels($kind,$values) {
        $labels=[]; foreach ((array)$values as $value) {
            if ($kind==='content') { $post=get_post((int)$value); $labels[(string)$value]=$post && $post->post_status==='publish' && is_post_type_viewable($post->post_type) ? get_the_title($post).' ('.get_post_type_object($post->post_type)->labels->singular_name.')' : 'Unavailable content'; }
            elseif ($kind==='terms') { $parts=explode(':',(string)$value); $term=count($parts)===2?get_term((int)$parts[1],$parts[0]):null; $labels[(string)$value]=$term && !is_wp_error($term)?$term->name.' ('.$term->taxonomy.')':'Unavailable term'; }
            else { foreach (Rules::lookup($kind,'') as $item) { if ((string)$item['id']===(string)$value) { $labels[(string)$value]=$item['label']; break; } } if (!isset($labels[(string)$value])) { $labels[(string)$value]='Unavailable selection'; } }
        } return $labels;
    }
    private static function picker($c,$path,$label,$kind) {
        echo '<div class="fcb-m2-picker fcb-field" data-selector-path="'.esc_attr($path).'" data-kind="'.esc_attr($kind).'" data-labels="'.esc_attr(wp_json_encode(self::labels($kind,self::value($c,$path)))).'"><span>'.esc_html($label).'</span><div class="fcb-m2-chips"></div><input type="search" role="combobox" aria-label="'.esc_attr('Search '.$label).'" aria-expanded="false" aria-autocomplete="list" autocomplete="off" placeholder="Search by name"><ul class="fcb-m2-results" role="listbox" hidden></ul><p class="fcb-m2-search-status" role="status" aria-live="polite"></p></div>';
    }
    public static function display($c) {
        self::section('Independent desktop and mobile layouts','Each device has its own placement, size and layout. Spacing also respects device safe areas.');
        self::check($c,'desktop','Show on desktop'); self::check($c,'mobile','Show on mobile');
        foreach (['desktop_view'=>'Desktop','mobile_view'=>'Mobile'] as $path=>$label) {
            echo '<fieldset class="fcb-m2-fieldset"><legend>'.esc_html($label).'</legend>';
            self::choice($c,$path.'.position','Placement',['right'=>'Bottom right','left'=>'Bottom left','center'=>'Bottom center']);
            $layouts=['expandable'=>'Expandable contact panel','separate'=>'Separate contact buttons']; if ($path==='mobile_view') { $layouts['bar']='Full-width mobile contact bar'; }
            self::choice($c,$path.'.layout','Layout',$layouts);
            self::choice($c,$path.'.direction','Button direction',['vertical'=>'Vertical','horizontal'=>'Horizontal']);
            self::check($c,$path.'.expanded','Start expanded');
            echo '<div class="fcb-m2-grid">'; foreach (['x'=>['Horizontal edge spacing',0,300],'y'=>['Bottom spacing',0,300],'size'=>['Button size',40,100],'icon_size'=>['Icon size',16,48],'gap'=>['Gap between buttons',4,40]] as $key=>$data) { self::field($c,$path.'.'.$key,$data[0].' (px)','number','min="'.$data[1].'" max="'.$data[2].'"'); } echo '</div></fieldset>';
        } self::reset('positioning'); echo '<p class="fcb-m2-help">Resets placement and edge spacing only. Device visibility, button sizing and layout stay as configured.</p></section>';
        self::section('Choose where the widget appears','Exclusions always take priority. In Selected mode, any matching inclusion can show the widget. Administration and login screens always stay hidden.');
        self::choice($c,'rules.mode','Show the widget',['all'=>'Across the public website','home'=>'Homepage only','selected'=>'Selected pages and views']);
        foreach (['include'=>'Include','exclude'=>'Exclude'] as $prefix=>$label) {
            self::accordion($label.' pages and views',$prefix==='include'?'Advanced targeting: choose which content and website views can show the widget.':'Advanced targeting: hide the widget on selected content and website views.');
            foreach (['ids'=>['Pages, posts and public content','content'],'types'=>['Post types','types'],'templates'=>['Templates','templates'],'terms'=>['Categories, tags and taxonomy terms','terms'],'archives'=>['Archive views','archives']] as $kind=>$data) { self::picker($c,'rules.'.$prefix.'_'.$kind,$label.' '.$data[0],$data[1]); }
            self::field($c,'rules.'.$prefix.'_urls',$label.' URL patterns (one per line)','textarea','data-m2-list="1" placeholder="/contact/*"');
            echo '<p class="fcb-m2-help">Use a path such as /services/* or a full website URL. * matches any characters; paths and URL queries are evaluated as configured.</p></div></details>';
        }
        echo '<label class="fcb-field"><span>Explain visibility for a website URL</span><input type="url" id="fcb-m2-explain-url" value="'.esc_attr(home_url('/')).'"></label><button class="fcb-button fcb-button-light" type="button" data-m2-action="explain">Explain current draft</button><p id="fcb-m2-explanation" role="status" aria-live="polite">Uses your current draft, including unpublished rules.</p></section>';
    }
    public static function design($c) {
        self::ordering($c);
        self::section('Advanced appearance','Open only the sections you need. Each reset changes the draft and preview; publish when you are ready. Zero custom width or height keeps automatic sizing.');
        self::check($c,'tooltips','Show contact button tooltips'); self::check($c,'intro_enabled','Show introductory bubble');
        foreach (['launcher_style'=>['Expandable launcher','Style the main button that reveals your selected contact methods.'],'callback_style'=>['Callback button','Set colors, icon, spacing and edge alignment for the callback action.'],'form_style'=>['Callback and enquiry form','Keep the actual form readable with its own background and button colors.'],'panel_style'=>['Expanded contact panel','Optional background around the contact stack. Off by default for individually floating buttons.'],'animations'=>['Interaction animations','Control subtle launcher, contact button and callback transitions. Reduced-motion preferences are always respected.']] as $path=>$section) {
            self::accordion($section[0],$section[1]); echo '<div class="fcb-m2-grid">';
            if ($path==='callback_style') { Admin::field('Callback button label','submit_label','text',$c['submit_label'],'maxlength="80"'); }
            foreach (self::value($c,$path) as $key=>$value) {
                $label=ucwords(str_replace('_',' ',$key));
                if ($path==='panel_style' && $key==='enabled') { $label='Enable panel background'; }
                if ($path==='animations' && $key==='enabled') { $label='Enable interaction animations'; }
                if ($path==='animations' && $key==='stagger') { $label='Stagger contact button entrances'; }
                if ($key==='icon') { self::choice($c,$path.'.'.$key,$label,['none'=>'No icon','contact'=>'Previous contact icon','call'=>'Call','chat'=>'Chat','email'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp','telegram'=>'Telegram','messenger'=>'Messenger','custom'=>'Link']); }
                elseif ($key==='aria_label') { self::field($c,$path.'.'.$key,'Launcher accessibility text','text','maxlength="100"'); }
                elseif ($key==='shadow') { self::choice($c,$path.'.'.$key,$label,['none'=>'None','soft'=>'Soft','strong'=>'Strong']); }
                elseif ($key==='placement') { self::choice($c,$path.'.'.$key,$label,['above'=>'Above contact buttons','below'=>'Below contact buttons']); }
                elseif ($key==='edge') { self::check($c,$path.'.'.$key,'Align callback with screen edge'); }
                elseif (is_bool($value)) { self::check($c,$path.'.'.$key,$label); }
                elseif ($path==='animations' && $key==='duration') { self::field($c,$path.'.'.$key,'Duration (ms)','number','min="180" max="240"'); }
                elseif (is_numeric($value)) { $max=$key==='radius'?999:($key==='border_width'?8:($key==='width'?320:($key==='height'?120:64))); self::field($c,$path.'.'.$key,$label.' (px)','number','min="0" max="'.$max.'"'); }
                else { self::field($c,$path.'.'.$key,$label,'color'); }
            } echo '</div>'; self::reset($path); echo '</div></details>';
        }
        echo '<div class="fcb-advanced-channel-list">';
        foreach ($c['channels'] as $key=>$channel) {
            $name=self::channel_name($key);$path='channels.'.$key.'.appearance'; self::accordion($name.' button settings','Choose this button’s label, icon, content, styling and optional finite animation. Use the order list above to move it.');
            echo '<span data-style-channel="'.esc_attr($key).'" hidden></span><p class="fcb-channel-style-order" data-order-number="'.esc_attr($key).'"></p><label class="fcb-field"><span>Button label</span><input type="text" data-channel="'.esc_attr($key).'" data-property="label" value="'.esc_attr($channel['label']).'" maxlength="60"></label><div class="fcb-m2-grid">';
            foreach (['color'=>'Background','icon_color'=>'Icon and text color'] as $property=>$label) { echo '<label class="fcb-field"><span>'.esc_html($label).'</span><input type="color" data-channel="'.esc_attr($key).'" data-property="'.esc_attr($property).'" value="'.esc_attr($channel[$property]).'"></label>'; }echo '</div>';
            self::choice($c,$path.'.content_mode','Display style',['inherit'=>'Use preset','text'=>'Text only','icon_text'=>'Icon + Text','icon'=>'Icon only']);
            self::choice($c,$path.'.icon','Icon',['default'=>'Channel default','call'=>'Call','chat'=>'Chat','email'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp','telegram'=>'Telegram','messenger'=>'Messenger','custom'=>'Link']);
            self::choice($c,$path.'.animation','Button animation',['inherit'=>'Use Easy Setup default','none'=>'None','ripple'=>'Water Ripple','pulse'=>'Soft Pulse']);
            echo '<button type="button" class="fcb-animation-sample" data-preview-animation="'.esc_attr($key).'" aria-label="'.esc_attr('Preview '.$name.' animation').'"><span data-animation-preview-icon>'.Widget::icon($key).'</span><span>Preview animation</span></button><p class="fcb-m2-help">Optional effects run for two gentle cycles when the button first becomes available. They respect the global animation switch, reduced motion and hidden pages. They do not loop continuously.</p>';
            self::check($c,$path.'.custom','Use individual appearance for '.ucfirst($key),'These dimensions and styles override the shared button settings.');
            self::check($c,$path.'.desktop','Show this button on desktop'); self::check($c,$path.'.mobile','Show this button on mobile');
            self::choice($c,$path.'.shadow','Shadow',['none'=>'None','soft'=>'Soft','strong'=>'Strong']);
            echo '<div class="fcb-m2-grid">';
            foreach (['width'=>[0,320],'height'=>[0,120],'mobile_width'=>[0,280],'mobile_height'=>[0,100],'icon_size'=>[16,48],'mobile_icon_size'=>[16,40],'padding_x'=>[0,48],'padding_y'=>[0,32],'radius'=>[0,999],'border_width'=>[0,8]] as $field=>$range) { self::field($c,$path.'.'.$field,ucwords(str_replace('_',' ',$field)).' (px)','number','min="'.$range[0].'" max="'.$range[1].'"'); }
            foreach (['border_color','hover_color','focus_color'] as $field) { self::field($c,$path.'.'.$field,ucwords(str_replace('_',' ',$field)).($field==='hover_color'?' (blank uses channel color)':''),$field==='hover_color'?'text':'color',$field==='hover_color'?'placeholder="#15803d" maxlength="7"':''); }
            echo '</div>'; self::reset('contact',$key); echo '</div></details>';
        } echo '</div></section>'; self::grouping($c); self::mobile_bar($c);
    }
    private static function channel_name($key) { return ['whatsapp'=>'WhatsApp','call'=>'Call','email'=>'Email','sms'=>'SMS','telegram'=>'Telegram','messenger'=>'Messenger','custom'=>'Custom URL'][$key]??ucfirst($key); }
    private static function ordering($c) {
        self::section('Button order and locations','Use the up and down arrows to move each selected contact method. Its destination, hours and individual settings move with it. Changes update your draft preview immediately.');
        echo '<ol class="fcb-contact-order-list" data-order-holder>';
        foreach ($c['channels'] as $key=>$channel) {
            $name=self::channel_name($key);echo '<li data-order-card="'.esc_attr($key).'" data-channel-fields="'.esc_attr($key).'"'.(!$channel['enabled']?' hidden':'').'><div class="fcb-order-contact"><span class="fcb-order-icon">'.Widget::icon($key).'</span><strong>'.esc_html($name).'</strong></div>';Simple::order_controls($key,$name);
            echo '<div class="fcb-button-locations" data-button-location-details><span><b>Desktop</b> <span data-channel-location="'.esc_attr($key).'" data-view="desktop_view"></span></span><span><b>Mobile</b> <span data-channel-location="'.esc_attr($key).'" data-view="mobile_view"></span></span></div></li>';
        }
        echo '</ol><div class="fcb-callback-location-row" data-callback-location-row><strong>Request a Callback</strong><p class="fcb-m2-help">Uses its own Callback button position setting when displayed individually.</p><div class="fcb-button-locations" data-button-location-details><span><b>Desktop</b> <span data-channel-location="callback" data-view="desktop_view"></span></span><span><b>Mobile</b> <span data-channel-location="callback" data-view="mobile_view"></span></span></div></div><p class="fcb-m2-help">Desktop and Mobile locations show configured placement when more than two actions are selected. Business hours and device visibility still apply. Callback uses its own above/below placement when shown as an individual button.</p></section>';
    }
    private static function grouping($c) {
        self::section('Always visible buttons and additional options','Choose the direct actions for each device. Other enabled methods appear once inside Contact options / More. Assigning a channel here never enables it or changes its destination.');
        $names=['call'=>'Call','whatsapp'=>'WhatsApp','email'=>'Email','telegram'=>'Telegram','messenger'=>'Messenger','sms'=>'SMS','custom'=>'Custom URL','callback'=>'Callback'];
        foreach (['desktop_view'=>'Desktop','mobile_view'=>'Mobile'] as $view=>$label) {
            self::accordion($label.' contact grouping','Always visible buttons stay direct. Remaining enabled actions are available in the expandable menu; an empty menu is hidden automatically.');
            self::check($c,$view.'.grouped','Choose always-visible buttons for '.$label,'Off preserves the existing layout. On keeps the selected direct actions visible and uses a launcher for additional options, including when a separate-button style is selected.');
            $layouts=['expandable'=>'Expandable contact options','separate'=>'Separate contact buttons']; if ($view==='mobile_view') { $layouts['bar']='Full-width mobile contact bar'; }
            self::choice($c,$view.'.layout',$label.' layout',$layouts);
            echo '<fieldset class="fcb-m2-fieldset" data-launcher-placement="'.esc_attr($view).'"><legend>Launcher / Close position</legend>';
            $placements=[0=>'Top of additional buttons'];for($i=1;$i<=8;$i++){$placements[$i]='After '.$i.' additional button'.($i===1?'':'s');}$placements[-1]='Bottom of additional buttons';
            self::choice($c,$view.'.launcher_index','Launcher / Close position',$placements);
            echo '<div class="fcb-launcher-move"><span data-launcher-order="'.esc_attr($view).'"></span><button type="button" class="fcb-reorder" data-move-launcher="-1" data-view="'.esc_attr($view).'" aria-label="'.esc_attr('Move '.$label.' Close control up').'">↑</button><button type="button" class="fcb-reorder" data-move-launcher="1" data-view="'.esc_attr($view).'" aria-label="'.esc_attr('Move '.$label.' Close control down').'">↓</button></div><p class="fcb-m2-help">Floating contact stacks support Top, Bottom or a position among the additional actions. Positions beyond the available actions use the bottom. The mobile bar keeps More in the bar.</p></fieldset><p class="fcb-m2-help" data-launcher-support="'.esc_attr($view).'"></p>';
            echo '<fieldset class="fcb-m2-fieldset" data-always-options="'.esc_attr($view).'"><legend>Always visible contact buttons</legend>';
            foreach ($names as $key=>$name) {
                $enabled=$key==='callback'?!empty($c['callback']):!empty($c['channels'][$key]['enabled']);
                echo '<label class="fcb-switch-row"><input type="checkbox" data-always-view="'.esc_attr($view).'" data-always-channel="'.esc_attr($key).'" '.checked(in_array($key,$c[$view]['always']??[],true),true,false).'><span><strong>'.esc_html($name).'</strong><small>'.esc_html($enabled?'Enabled contact method':'Currently disabled; selecting this group does not enable it.').'</small></span></label>';
            }
            echo '</fieldset><p class="fcb-m2-help">Additional contact options inside the launcher: all other enabled methods, in your saved channel order. A channel appears in only one group.</p></div></details>';
        }
        echo '</section>';
    }
    private static function mobile_bar($c) {
        self::section('Mobile contact bar','A full-width sticky bar for mobile screens. Call and WhatsApp are direct actions when enabled; More reveals additional enabled contact options. Desktop keeps its own layout.');
        self::accordion('Bar placement and behavior','Choose a top or bottom bar, its mobile breakpoint and whether to reserve space for page content. Select the Mobile Contact Bar preset or Mobile layout to enable it.');
        self::choice($c,'mobile_bar.position','Mobile bar position',['bottom'=>'Bottom of mobile screen','top'=>'Top of mobile screen']);
        self::field($c,'mobile_bar.breakpoint','Mobile breakpoint (px)','number','min="320" max="1200"');
        self::choice($c,'mobile_bar.more_action','Additional action',['menu'=>'More menu','callback'=>'Direct Callback when it is the only extra action']);
        self::check($c,'mobile_bar.content_spacing','Reserve space for the mobile bar','Adds temporary content-safe spacing while the bar is active. No theme template is changed.');
        echo '</div></details>';
        self::accordion('Mobile bar appearance','Use readable text, clear borders and comfortable tap targets. Device safe-area insets are included automatically.');
        echo '<div class="fcb-m2-grid">'; foreach (['background'=>'Contact bar background color','button_background'=>'Button background color','icon_color'=>'Button icon color','text'=>'Button text color','border_color'=>'Border color'] as $key=>$label) { self::field($c,'mobile_bar.'.$key,$label,'color'); }
        foreach (['border_width'=>['Border width',0,8],'padding'=>['Padding',0,24],'height'=>['Minimum action height',48,100]] as $key=>$data) { self::field($c,'mobile_bar.'.$key,$data[0].' (px)','number','min="'.$data[1].'" max="'.$data[2].'"'); } echo '</div>';self::reset('mobile_bar');echo '</div></details></section>';
    }
    public static function controls($page,$c) {
        if ($page==='fcb-hours') {
            self::section('Contact hours for each channel','Hours follow your chosen timezone, including daylight-saving changes. Overnight intervals continue into the following day. All weekdays use Monday through Sunday.');
            $website_timezone=class_exists(Help::class)?Help::timezone_label():'Use website timezone ('.wp_timezone_string().')';
            $schedule_keys=array_merge(array_keys($c['channels']),['callback']);
            foreach ($schedule_keys as $key) {
                $callback=$key==='callback';
                $path=$callback?'callback_schedule':'channels.'.$key.'.schedule'; echo '<details class="fcb-details" id="fcb-schedule-'.esc_attr($key).'" data-schedule="'.esc_attr($key).'"><summary><strong>'.esc_html($callback?'Callback form hours':ucfirst($key).' contact hours').'</strong><small>Set available days, intervals, timezone and what visitors see outside these hours.</small></summary><div class="fcb-details-body">';
                self::check($c,$path.'.enabled',$callback?'Restrict callback form hours':'Use contact hours for '.ucfirst($key),$callback?'Off by default: callback requests are available at any time. Enable this to restrict the form to the intervals below.':'');
                $timezones=['site'=>$website_timezone]; foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $zone) { $timezones[$zone]=$zone; }
                self::choice($c,$path.'.timezone','Timezone',$timezones);
                echo '<p class="fcb-m2-help">Uses the timezone configured in WordPress → Settings → General. Your contact buttons will follow these hours in that timezone. To use separate hours, select a city timezone instead; that channel then follows the selected timezone, including its daylight-saving changes.</p><div data-intervals="'.esc_attr($key).'"></div><button type="button" class="fcb-button fcb-button-light" data-add-interval="'.esc_attr($key).'">Add interval</button>';
                $closed=['hide'=>$callback?'Hide the callback form':'Hide the button','unavailable'=>'Show unavailable message','alternative'=>'Offer another channel'];
                if (!$callback) { $closed=['hide'=>'Hide the button','unavailable'=>'Show unavailable message','callback'=>'Offer callback request','alternative'=>'Offer another channel']; }
                self::choice($c,$path.'.closed',$callback?'When the callback form is closed':'When this channel is closed',$closed);
                self::field($c,$path.'.message','Unavailable message');
                self::choice($c,$path.'.alternative','Alternative channel',array_combine(array_keys($c['channels']),array_map('ucfirst',array_keys($c['channels']))));
                echo '</div></details>';
            } echo '</section>';
        } elseif ($page==='fcb-recovery') {
            self::section('Follow-up settings','Offer the existing callback form or WhatsApp when those contact methods are available. Phone links always open immediately.');
            self::check($c,'recovery.followup.enabled','Enable Call Follow-up','Off by default. After a phone-button click, offer another way to contact you. This interaction does not need analytics consent; optional tracking still follows your consent settings.');
            self::field($c,'recovery.followup.delay','Show after (seconds)','number','min="2" max="120"');
            echo '<p class="fcb-m2-help">Example: 10 waits ten seconds after the click. The prompt can appear only while the website page is visible.</p><dl class="fcb-followup-rules"><dt>Show when the visitor returns</dt><dd>Automatic: if the delay passes while a phone app is open, the prompt can appear when the visitor returns to a visible page. Browser support varies.</dd><dt>Show only once per session</dt><dd>Built in: an automatic prompt appears at most once per browser session. Its X Close button also keeps it hidden for that session.</dd><dt>Hide after a callback request</dt><dd>Built in: an accepted callback or enquiry suppresses further automatic prompts for that browser session.</dd><dt>Include existing website phone links</dt><dd>Automatic: enabled Call Follow-up watches your floating Call button and matching phone links already on your website. Use the number and page filters below to limit them.</dd></dl>';
            self::accordion('Change follow-up wording','Use a gentle question. Avoid claiming that a call failed or that someone did not answer.');
            self::field($c,'recovery.followup.title','Follow-up heading','text','maxlength="100"');self::field($c,'recovery.followup.text','Follow-up message','textarea','maxlength="300"');echo '</div></details></section>';
            self::section('Choose where the follow-up appears','These number and page filters apply to your floating Call button and eligible phone links already on the website. Contact availability and business hours still apply.');
            self::choice($c,'recovery.number_mode','Phone numbers to match',['all'=>'All website phone numbers','include'=>'Only these numbers','exclude'=>'All except these numbers']);
            self::field($c,'recovery.numbers','Number filters (one per line)','textarea','data-m2-list="1" placeholder="+12125550100"');
            echo '<p class="fcb-m2-help">Example: choose Only these numbers and enter your sales number to leave support phone links out of the follow-up.</p>';
            self::picker($c,'recovery.include_ids','Show on pages and content','content');self::picker($c,'recovery.exclude_ids','Do not show on pages and content','content');
            echo '<p class="fcb-m2-help">An empty Show on list includes all public pages. Do not show exclusions always take priority. Each action follows its own enable switch, device visibility and business hours.</p></section>';
            self::section('Advanced phone-link options','Keep existing sitewide click tracking and earlier callback prompt settings when you need them. These switches are separate from Enable Call Follow-up.');
            self::accordion('Existing tracking and earlier callback prompts','For an existing setup that uses return or delay signals. Click tracking counts a selection; it cannot establish that a call connected.');
            self::check($c,'recovery.enabled','Enable existing phone-link tracking and earlier prompts','Not required for the newer Call Follow-up prompt above. Optional counting also requires local analytics and its configured consent.');
            self::check($c,'recovery.prompt','Offer an earlier callback prompt','Requires callback requests. Turn this off to retain only opted-in click tracking for existing phone links.');
            self::choice($c,'recovery.timing','When to show the earlier prompt',['return'=>'After returning to the page','delay'=>'After a delay','both'=>'Return or delay','manual'=>'Manual callback only']);
            self::field($c,'recovery.delay','Earlier prompt delay (seconds)','number','min="2" max="120"');self::field($c,'recovery.title','Earlier prompt heading');self::field($c,'recovery.text','Earlier prompt message','textarea');echo '</div></details></section>';
        } elseif ($page==='fcb-integrations') {
            self::section('Google Analytics 4','GA4 is independent of local Insights. This plugin uses an existing gtag or Google Tag Manager installation and never injects a tracking tag. No contact details, messages, phone numbers, full page URLs or submission IDs are included in its events.');
            self::check($c,'ga4','Send consented contact events to your existing Google tag'); self::choice($c,'ga4_mode','Existing tag integration',['gtag'=>'Existing gtag function','gtm'=>'Existing Google Tag Manager dataLayer']); self::check($c,'ga4_debug','Enable GA4 debug mode');
            echo '<p class="fcb-m2-help">Connect your consent manager to <code>window.FCBConsent.setAnalytics(true)</code> when analytics consent is granted, and <code>window.FCBConsent.setAnalytics(false)</code> when it is revoked. The WordPress Consent API <code>statistics</code> category is supported. Without analytics consent, GA4 events stay off. Preview never sends tracking.</p><p class="fcb-m2-help">GTM mode pushes events to your existing dataLayer; configure your own GA4 event tag and consent requirements in your container.</p><details class="fcb-details"><summary>GTM event and parameter reference</summary><p class="fcb-m2-help">Events: <code>fcb_phone_click</code>, <code>fcb_whatsapp_click</code>, <code>fcb_contact_click</code>, <code>fcb_callback_open</code> and <code>generate_lead</code> after an accepted submission. Parameters: <code>channel_type</code>, <code>widget_location</code>, <code>source_category</code> and optional <code>debug_mode</code>. Click events never establish that a call connected or a message was sent.</p></details></section>';
            self::section('Move your design safely','Export settings only. Enquiries, event history, notification addresses and report recipients are excluded. Imported settings remain an unpublished draft, with tracking and scheduled reports switched off.');
            echo '<label class="fcb-switch-row"><input id="fcb-m2-export-destinations" type="checkbox"><span><strong>Include public business contact destinations</strong><small>Optional: exported phone numbers, business email addresses and channel links will be readable in the JSON file.</small></span></label><button class="fcb-button fcb-button-light" type="button" data-m2-action="export">Download settings JSON</button><label class="fcb-field"><span>Import settings JSON (up to 150 KB)</span><input type="file" id="fcb-m2-import-file" accept="application/json,.json"></label><button class="fcb-button fcb-button-light" type="button" data-m2-action="import">Load file into draft</button><p id="fcb-m2-transfer-status" role="status" aria-live="polite"></p></section>';
        }
    }
    private static function metrics($summary) {
        echo '<div class="fcb-m2-kpis">'; foreach (['clicks'=>'Contact link clicks (analytics)','callback_requests'=>'Accepted callbacks (analytics)','enquiries'=>'Accepted enquiries (analytics)','failures'=>'Form / notification errors (analytics)'] as $key=>$label) { echo '<div><strong>'.(int)$summary[$key].'</strong><span>'.esc_html($label).'</span></div>'; } echo '</div>';
        echo '<h3>Daily contact link clicks</h3><p class="fcb-m2-help">Site-local days. These are link selections, not completed calls or messages.</p>';
        $days=$summary['days']; $peak=0; foreach ($days as $day) { $peak=max($peak,$day['clicks']); } $max=max(1,$peak);
        $width=700; $height=150; $n=max(1,count($days)); $bar=min(36,($width/$n)*.7); $i=0;
        echo '<p class="fcb-m2-help">Highest daily count: '.(int)$peak.'. All daily values are available in the accessible table.</p><svg class="fcb-m2-chart" viewBox="0 0 700 150" role="img" aria-label="Daily contact link click counts, highest daily count '.(int)$peak.'"><line x1="0" y1="150" x2="700" y2="150" stroke="currentColor" opacity=".3"/>';
        foreach ($days as $date=>$day) { $x=$i*$width/$n+($width/$n-$bar)/2; $h=$day['clicks']/$max*$height; echo '<rect x="'.esc_attr($x).'" y="'.esc_attr($height-$h).'" width="'.esc_attr($bar).'" height="'.esc_attr($h).'" fill="#204c3a"><title>'.esc_html($date.': '.$day['clicks'].' clicks').'</title></rect>'; $i++; } echo '</svg><div class="fcb-m2-chart-labels"><span>'.esc_html(key($days)?:'').'</span><span>'.esc_html($days?array_key_last($days):'').'</span></div>';
        echo '<details class="fcb-details"><summary>Accessible daily count table</summary><div class="fcb-m2-table-scroll"><table class="widefat"><thead><tr><th>Date</th><th>Clicks</th><th>Callbacks</th><th>Enquiries</th><th>Form / notification errors</th></tr></thead><tbody>'; foreach ($days as $date=>$day) { echo '<tr><td>'.esc_html($date).'</td><td>'.(int)$day['clicks'].'</td><td>'.(int)$day['callback_requests'].'</td><td>'.(int)$day['enquiries'].'</td><td>'.(int)$day['failures'].'</td></tr>'; } echo '</tbody></table></div></details><h3>Clicks by channel</h3><ul>'; foreach ($summary['by_channel'] as $channel=>$count) { echo '<li>'.esc_html(ucfirst($channel)).': '.(int)$count.'</li>'; } if (!$summary['by_channel']) { echo '<li>No recorded clicks in this period.</li>'; } echo '</ul><p class="fcb-m2-help">Callback conversion: '.($summary['form_opens'] ? esc_html(round(100*$summary['callback_requests']/$summary['form_opens'],1).'%') : 'Unavailable (no recorded form opens)').' — '.(int)$summary['callback_requests'].' accepted callbacks / '.(int)$summary['form_opens'].' form opens. Consent and retention may make these counts incomplete.</p>';
    }
    public static function insights($c,$live) {
        $snapshot=Reports::snapshot(); $start=$snapshot['period']['start']; $end=$snapshot['period']['end'];
        echo '<div class="fcb-page-heading"><div><h1>Contact Analytics</h1><p>See which contact buttons visitors click most often. Clicks do not confirm successful conversations.</p></div><span class="fcb-draft-badge">Editing draft</span></div><div class="fcb-editor-layout fcb-m2-insights-layout" inert data-wizard="0"><div class="fcb-controls">';
        Simple::controls($c); echo '<div class="fcb-advanced-settings">';
        self::section('Contact Insights','Date filters use '.wp_timezone_string().' and inclusive full local dates. These analytics counts respect the published consent setting. Switching analytics off does not disable the private inbox.');
        if (empty($live['analytics'])) { echo '<p class="fcb-m2-notice">Local analytics is currently off. Any historical counters may be incomplete. Enable it below and publish to start collecting consented events.</p>'; }
        echo '<div class="fcb-m2-grid"><label class="fcb-field"><span>From</span><input type="date" id="fcb-m2-date-start" value="'.esc_attr($start).'"></label><label class="fcb-field"><span>Through</span><input type="date" id="fcb-m2-date-end" value="'.esc_attr($end).'"></label></div><button class="fcb-button fcb-button-light" type="button" data-m2-action="filter">Apply date range</button><p id="fcb-m2-range-status" role="status" aria-live="polite"></p><div id="fcb-m2-metrics">'; self::metrics($snapshot['current']['analytics']);
        echo '</div><h3>Retained inbox submissions: '.esc_html($start.' to '.$end).'</h3><p>'.(int)$snapshot['current']['retained_callbacks'].' callback requests and '.(int)$snapshot['current']['retained_quick_enquiries'].' quick enquiries. These saved records are independent of analytics consent; records never stored or already deleted are excluded. This fixed reporting window is separate from the analytics date filter.</p><h3>All retained customers still waiting</h3><p><strong>'.(int)$snapshot['waiting']['total'].'</strong> unresolved requests: '.(int)$snapshot['waiting']['new'].' New and '.(int)$snapshot['waiting']['contacted'].' Contacted. Includes older requests outside the selected analytics dates.</p><a class="fcb-text-link" href="'.esc_url(admin_url('admin.php?page=fcb-inbox')).'">Open private inbox</a></section>';
        self::section('Analytics privacy and retention'); self::check($c,'analytics','Enable local aggregate analytics','Stores daily event/channel/source counts only. No cookies, raw IP addresses, visitor profiles, contact details or messages in analytics. Short-lived salted guards prevent spam and duplicate events.');
        self::choice($c,'analytics_consent','Analytics consent',['required'=>'Require explicit analytics consent','not_required'=>'Count local events without analytics consent']); self::field($c,'analytics_retention','Keep aggregate days','number','min="1" max="365"');
        echo '<p class="fcb-m2-help">Consent defaults to required. Review your privacy policy and obligations before changing it. Local analytics and GA4 are separate switches. Retention includes today and runs through scheduled cleanup; low-traffic sites may clean up late.</p></section>';
        self::section('Weekly email reports','The report covers the last seven complete local days and compares the previous seven. Counts include all retained unresolved enquiries. Publish recipient, schedule and detail changes before generating a preview or test.');
        self::check($c,'reports.enabled','Send a weekly report'); self::field($c,'reports.recipients','Report recipients (comma or newline separated)','textarea','maxlength="6000"');
        self::choice($c,'reports.weekday','Local weekday',[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday']); self::field($c,'reports.time','Local send time','time');
        self::check($c,'reports.details','Include full customer details in report emails','Explicit opt-in: recipients and anyone with mailbox access may read, forward or retain customer names, contact details, messages and source paths. Mailbox retention is separate. Deleting local records cannot recall sent copies. Summary reports contain counts and an authenticated inbox link.');
        $due=wp_next_scheduled(Reports::HOOK); $last=get_option('fcb_last_report',[]);
        echo '<p class="fcb-m2-help">Timezone: '.esc_html(wp_timezone_string()).'. Next scheduled run: '.($due?esc_html(wp_date('D j M Y, H:i T',$due)):'None').'. '; if ($due && $due<time()) { echo 'This run is overdue. '; } echo 'WordPress cron depends on site traffic or server cron; the time is not guaranteed.</p>';
        if ($last) { echo '<p class="fcb-m2-help">Last report: '.esc_html($last['status']??'unknown').' at '.esc_html(wp_date('j M Y H:i',$last['at']??0)).'. '.esc_html($last['message']??'').'</p>'; }
        echo '<div class="fcb-editor-actions"><button class="fcb-button fcb-button-light" type="button" data-m2-action="report-generate">Generate private report preview</button><button class="fcb-button fcb-button-light" type="button" data-m2-action="report-test">Send non-personal sample email</button></div><p class="fcb-m2-help">Transport acceptance does not confirm inbox delivery. A failed weekly send is not automatically resent. Test emails contain sample counts only.</p></section></div><div class="fcb-editor-actions"><button type="button" class="fcb-button fcb-button-light" data-admin-action="draft">Save draft</button><button type="button" class="fcb-button fcb-button-primary" data-admin-action="publish">Publish widget ↗</button></div><div class="fcb-action-feedback" role="status" aria-live="polite" tabindex="-1"></div></div><aside class="fcb-card-admin fcb-m2 fcb-m2-report-column fcb-advanced-only"><h2>Private report preview</h2><p>Generated from published report settings. Administrator sign-in is required.</p><div id="fcb-report-preview" role="region" aria-label="Private report preview"><p>Choose Generate private report preview to review the current reporting window.</p></div></aside>'; Admin::preview($c,true);echo '</div>';
    }
}
