<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Additive M2 configuration; never rewrites enquiry data or saved M1 options on reads. */
final class Configuration {
    const DOMAIN = 'floating-chat-buttons-whatsapp-telegram-call';
    public static function schedule_defaults() {
        return ['enabled'=>false,'timezone'=>'site','intervals'=>[['days'=>[1,2,3,4,5,6],'open'=>'09:00','close'=>'18:00']],'closed'=>'hide','message'=>__('This contact method is currently unavailable.',self::DOMAIN),'alternative'=>'whatsapp'];
    }
    public static function appearance_defaults() {
        return ['custom'=>false,'icon'=>'default','width'=>0,'height'=>0,'mobile_width'=>0,'mobile_height'=>0,'icon_size'=>24,'mobile_icon_size'=>22,'padding_x'=>12,'padding_y'=>12,'radius'=>999,'border_width'=>0,'border_color'=>'#204c3a','shadow'=>'soft','hover_color'=>'','focus_color'=>'#a16207','text_mode'=>'auto','content_mode'=>'inherit','animation'=>'inherit','desktop'=>true,'mobile'=>true];
    }
    public static function defaults($base) {
        foreach ($base['channels'] as &$ch) { $ch['schedule']=self::schedule_defaults(); $ch['appearance']=self::appearance_defaults(); } unset($ch);
        $view=['x'=>24,'y'=>24,'position'=>'right','direction'=>'vertical','expanded'=>false,'launcher_index'=>-1,'grouped'=>false,'always'=>[],'layout'=>$base['layout'],'size'=>56,'icon_size'=>24,'gap'=>12];
        return $base + [
            'mobile_bar'=>['position'=>'bottom','breakpoint'=>767,'more_action'=>'menu','background'=>'#172e2a','button_background'=>'#172e2a','icon_color'=>'#ffffff','text'=>'#ffffff','border_color'=>'#dbe4df','border_width'=>1,'padding'=>8,'height'=>60,'content_spacing'=>false],
            'desktop_view'=>$view,'mobile_view'=>array_replace($view,['x'=>16,'y'=>16,'size'=>50,'icon_size'=>22]),
            'callback_style'=>['background'=>'#204c3a','text'=>'#ffffff','icon_color'=>'#ffffff','icon'=>'call','border_color'=>'#204c3a','border_width'=>1,'radius'=>999,'padding_x'=>16,'padding_y'=>10,'gap'=>8,'margin'=>0,'placement'=>'below','edge'=>false,'hover_color'=>'#153b29','focus_color'=>'#a16207'],
            'launcher_style'=>['background'=>'#172e2a','text'=>'#ffffff','icon_color'=>'#ffffff','icon'=>'call','aria_label'=>'Contact options','border_color'=>'#172e2a','border_width'=>0,'radius'=>999,'padding_x'=>12,'padding_y'=>12,'width'=>0,'height'=>0,'shadow'=>'soft','hover_color'=>'#245840','focus_color'=>'#a16207'],
            'form_style'=>['background'=>'#ffffff','text'=>'#172e2a','button_background'=>'#204c3a','button_text'=>'#ffffff','border_color'=>'#b7c8bc','radius'=>22],
            'panel_style'=>['enabled'=>false,'background'=>'#ffffff','border_color'=>'#dbe4df','radius'=>20,'padding'=>14,'shadow'=>'soft'],
            'animations'=>['enabled'=>true,'duration'=>200,'stagger'=>true],
            'intro_style'=>['background'=>'#ffffff','text'=>'#263b36','border_color'=>'#dbe4df','radius'=>16,'font_size'=>16],
            'quick_animation'=>true,'intro_behavior'=>'hide_on_open','tooltips'=>true,'intro_enabled'=>true,'callback_schedule'=>self::schedule_defaults(),
            'rules'=>['mode'=>'all','include_ids'=>[],'exclude_ids'=>[],'include_types'=>[],'exclude_types'=>[],'include_templates'=>[],'exclude_templates'=>[],'include_terms'=>[],'exclude_terms'=>[],'include_archives'=>[],'exclude_archives'=>[],'include_urls'=>[],'exclude_urls'=>[]],
            'recovery'=>['enabled'=>false,'prompt'=>true,'number_mode'=>'all','numbers'=>[],'include_ids'=>[],'exclude_ids'=>[],'timing'=>'both','delay'=>8,'followup'=>['enabled'=>false,'delay'=>10,'title'=>'Were you able to reach us?','text'=>"If not, leave your contact details and we'll get back to you as soon as possible."],'title'=>__('Need another way to reach us?',self::DOMAIN),'text'=>__('Leave your phone number or email and we will get back to you.',self::DOMAIN)],
            'analytics'=>false,'analytics_retention'=>90,'analytics_consent'=>'required',
            'ga4'=>false,'ga4_mode'=>'gtag','ga4_debug'=>false,
            'reports'=>['enabled'=>false,'recipients'=>get_option('admin_email'),'weekday'=>1,'time'=>'09:00','details'=>false],
        ];
    }
    public static function merge($defaults,$saved) {
        if (!is_array($saved)) { return $defaults; }
        $result=[];
        foreach ($saved as $key=>$value) {
            if (!array_key_exists($key,$defaults)) { continue; }
            if (is_array($defaults[$key]) && is_array($value) && !self::is_list($defaults[$key]) && !self::is_list($value)) { $result[$key]=self::merge($defaults[$key],$value); }
            else { $result[$key]=$value; }
        }
        foreach ($defaults as $key=>$value) { if (!array_key_exists($key,$result)) { $result[$key]=$value; } }
        return $result;
    }
    private static function is_list($array) { return !$array || array_keys($array) === range(0,count($array)-1); }
    public static function legacy($saved) {
        if(is_array($saved)&&count($saved)>0&&!array_key_exists('quick_animation',$saved))$saved['quick_animation']=false;
        if (!is_array($saved) || !$saved) { return $saved; }
        if (is_array($saved['mobile_bar']??null)) {
            if(!array_key_exists('button_background',$saved['mobile_bar'])){$saved['mobile_bar']['button_background']=$saved['mobile_bar']['background']??'#172e2a';}
            if(!array_key_exists('icon_color',$saved['mobile_bar'])){$saved['mobile_bar']['icon_color']=$saved['mobile_bar']['text']??'#ffffff';}
        }
        if (!array_key_exists('inbox',$saved)) { $saved['inbox']=false; }
        if(is_array($saved['channels']??null)){foreach($saved['channels'] as &$channel){if(!is_array($channel)||!is_array($channel['appearance']??null))continue;$a=&$channel['appearance'];if(!array_key_exists('content_mode',$a)){$a['content_mode']=!empty($a['custom'])?(($a['text_mode']??'auto')==='show'?'icon_text':(($a['text_mode']??'auto')==='hide'?'icon':'inherit')):'inherit';}unset($a);}unset($channel);}
        foreach (['desktop_view','mobile_view'] as $view) {
            if (!isset($saved[$view])) {
                $saved[$view]=[
                    'x'=>(int)($saved['offset']??24),'y'=>(int)($saved['offset']??24),
                    'position'=>$saved['position']??'right','layout'=>$saved['layout']??'expandable',
                    'size'=>(int)($saved['size']??56),'gap'=>(int)($saved['gap']??12),
                ];
            }
        }
        return $saved;
    }
    private static function text($value,$limit,$fallback='') { return is_string($value) ? mb_substr(sanitize_text_field($value),0,$limit) : $fallback; }
    private static function choice($value,$values,$fallback) { return is_string($value) && in_array($value,$values,true) ? $value : $fallback; }
    private static function number($value,$min,$max,$fallback) { return is_scalar($value) && is_numeric($value) ? max($min,min($max,(int)$value)) : $fallback; }
    private static function error($message,$field) { return new \WP_Error('configuration',$message,['field'=>$field]); }
    public static function sanitize($raw,$out,$publishing) {
        $defaults=self::defaults(Settings::base_defaults());
        foreach (['quick_animation','tooltips','intro_enabled','analytics','ga4','ga4_debug'] as $key) { $out[$key]=array_key_exists($key,$raw) ? !empty($raw[$key]) : $defaults[$key]; }
        $out['intro_behavior']=self::choice($raw['intro_behavior']??'', ['always','hide_on_open','never'],'hide_on_open');
        $out['analytics_retention']=self::number($raw['analytics_retention']??90,1,365,90);
        $out['analytics_consent']=self::choice($raw['analytics_consent']??'', ['required','not_required'],'required');
        $out['ga4_mode']=self::choice($raw['ga4_mode']??'', ['gtag','gtm'],'gtag');
        foreach (['desktop_view','mobile_view'] as $view) {
            $r=is_array($raw[$view]??null) ? $raw[$view] : [];
            $d=$defaults[$view];
            $out[$view]=[
                'x'=>self::number($r['x']??($raw['offset']??$d['x']),0,300,$d['x']),
                'y'=>self::number($r['y']??($raw['offset']??$d['y']),0,300,$d['y']),
                'position'=>self::choice($r['position']??($raw['position']??'right'),['left','right','center'],'right'),
                'direction'=>self::choice($r['direction']??'', ['vertical','horizontal'],'vertical'),
                'expanded'=>!empty($r['expanded']),'launcher_index'=>self::number($r['launcher_index']??null,-1,8,-1),'grouped'=>!empty($r['grouped']),'always'=>array_values(array_unique(array_filter(is_array($r['always']??null)?array_slice($r['always'],0,8):[],function($id)use($out){return is_string($id)&&($id==='callback'||isset($out['channels'][$id]));}))),
                'layout'=>self::choice($r['layout']??($raw['layout']??''), ($view==='mobile_view'?['separate','expandable','bar']:['separate','expandable']),'expandable'),
                'size'=>self::number($r['size']??($raw['size']??$d['size']),40,100,$d['size']),
                'icon_size'=>self::number($r['icon_size']??$d['icon_size'],16,48,$d['icon_size']),
                'gap'=>self::number($r['gap']??($raw['gap']??$d['gap']),4,40,$d['gap']),
            ];
        }
        foreach (['callback_style','launcher_style','form_style','intro_style'] as $section) {
            $r=is_array($raw[$section]??null) ? $raw[$section] : [];
            $out[$section]=$defaults[$section];
            foreach (['background','text','icon_color','border_color','hover_color','focus_color','button_background','button_text'] as $key) {
                if (isset($out[$section][$key])) { $out[$section][$key]=is_string($r[$key]??null) ? (sanitize_hex_color($r[$key]) ?: $out[$section][$key]) : $out[$section][$key]; }
            }
            foreach (['radius'=>[0,999],'border_width'=>[0,8],'padding_x'=>[4,48],'padding_y'=>[4,32],'gap'=>[0,32],'margin'=>[0,64],'width'=>[0,320],'height'=>[0,120]] as $key=>$range) {
                if (isset($out[$section][$key])) { $out[$section][$key]=self::number($r[$key]??null,$range[0],$range[1],$out[$section][$key]); }
            }
            if ($section==='intro_style') { $out[$section]['font_size']=self::number($r['font_size']??null,12,24,16); }
            if (!in_array($section,['form_style','intro_style'],true)) { $out[$section]['icon']=self::choice($r['icon']??'', ['none','call','contact','chat','email','sms','whatsapp','telegram','messenger','custom'],$out[$section]['icon']); }
            if ($section==='launcher_style') { $out[$section]['aria_label']=self::text($r['aria_label']??null,100,'Contact options'); $out[$section]['shadow']=self::choice($r['shadow']??'', ['none','soft','strong'],'soft'); }
            if ($section==='callback_style') { $out[$section]['placement']=self::choice($r['placement']??'', ['above','below'],'below'); $out[$section]['edge']=!empty($r['edge']); }
        }
        $bar=is_array($raw['mobile_bar']??null)?$raw['mobile_bar']:[];$out['mobile_bar']=$defaults['mobile_bar'];
        $out['mobile_bar']['position']=self::choice($bar['position']??'', ['top','bottom'],'bottom');$out['mobile_bar']['more_action']=self::choice($bar['more_action']??'', ['menu','callback'],'menu');
        $out['mobile_bar']['content_spacing']=!empty($bar['content_spacing']);foreach(['breakpoint'=>[320,1200],'height'=>[48,100],'padding'=>[0,24],'border_width'=>[0,8]] as $key=>$range){$out['mobile_bar'][$key]=self::number($bar[$key]??null,$range[0],$range[1],$defaults['mobile_bar'][$key]);}
        foreach(['background','button_background','icon_color','text','border_color'] as $key){$out['mobile_bar'][$key]=is_string($bar[$key]??null)?(sanitize_hex_color($bar[$key])?:$defaults['mobile_bar'][$key]):$defaults['mobile_bar'][$key];}
        foreach(['button_background'=>'background','icon_color'=>'text'] as $field=>$old){if(!array_key_exists($field,$bar)&&is_string($bar[$old]??null)){$out['mobile_bar'][$field]=sanitize_hex_color($bar[$old])?:$defaults['mobile_bar'][$field];}}
        $panel=is_array($raw['panel_style']??null)?$raw['panel_style']:[];
        $out['panel_style']=$defaults['panel_style'];$out['panel_style']['enabled']=!empty($panel['enabled']);
        foreach(['background','border_color'] as $field){$out['panel_style'][$field]=is_string($panel[$field]??null)?(sanitize_hex_color($panel[$field])?:$defaults['panel_style'][$field]):$defaults['panel_style'][$field];}
        $out['panel_style']['radius']=self::number($panel['radius']??null,0,64,20);$out['panel_style']['padding']=self::number($panel['padding']??null,0,32,14);$out['panel_style']['shadow']=self::choice($panel['shadow']??'', ['none','soft','strong'],'soft');
        $motion=is_array($raw['animations']??null)?$raw['animations']:[];
        $out['animations']=['enabled'=>array_key_exists('enabled',$motion)?!empty($motion['enabled']):true,'duration'=>self::number($motion['duration']??null,180,240,200),'stagger'=>array_key_exists('stagger',$motion)?!empty($motion['stagger']):true];
        $callback=class_exists(Schedule::class)?Schedule::sanitize($raw['callback_schedule']??self::schedule_defaults()):self::schedule_defaults();
        if(is_wp_error($callback)){return self::error($callback->get_error_message(),'callback_schedule');}
        if(($callback['closed']??'')==='callback'||(($callback['closed']??'')==='alternative'&&($callback['alternative']??'')==='callback')){return self::error(__('Choose another channel instead of replacing callback with itself.',self::DOMAIN),'callback_schedule.closed');}
        $out['callback_schedule']=$callback;
        foreach ($out['channels'] as $key=>&$ch) {
            $source=is_array($raw['channels'][$key]??null) ? $raw['channels'][$key] : [];
            $appearance=is_array($source['appearance']??null) ? $source['appearance'] : [];
            $a=self::appearance_defaults();
            foreach (['custom','desktop','mobile'] as $field) { $a[$field]=array_key_exists($field,$appearance) ? !empty($appearance[$field]) : $a[$field]; }
            foreach (['width'=>[0,320],'height'=>[0,120],'mobile_width'=>[0,280],'mobile_height'=>[0,100],'icon_size'=>[16,48],'mobile_icon_size'=>[16,40],'padding_x'=>[0,48],'padding_y'=>[0,32],'radius'=>[0,999],'border_width'=>[0,8]] as $field=>$range) {
                $a[$field]=self::number($appearance[$field]??null,$range[0],$range[1],$a[$field]);
            }
            foreach (['border_color','hover_color','focus_color'] as $field) { $a[$field]=is_string($appearance[$field]??null) ? (sanitize_hex_color($appearance[$field]) ?: $a[$field]) : $a[$field]; }
            $a['content_mode']=array_key_exists('content_mode',$appearance)?self::choice($appearance['content_mode'],['inherit','text','icon_text','icon'],'inherit'):(!empty($a['custom'])?(($appearance['text_mode']??'auto')==='show'?'icon_text':(($appearance['text_mode']??'auto')==='hide'?'icon':'inherit')):'inherit');
            $a['animation']=self::choice($appearance['animation']??'', ['inherit','none','ripple','pulse'],'inherit');
            $a['icon']=self::choice($appearance['icon']??'', ['default','call','chat','email','sms','whatsapp','telegram','messenger','custom'],'default');
            $a['shadow']=self::choice($appearance['shadow']??'', ['none','soft','strong'],'soft');
            $a['text_mode']=self::choice($appearance['text_mode']??'', ['auto','show','hide'],'auto');
            $ch['appearance']=$a;
            $schedule=class_exists(Schedule::class) ? Schedule::sanitize($source['schedule']??self::schedule_defaults()) : self::schedule_defaults();
            if (is_wp_error($schedule)) { return self::error($schedule->get_error_message(),'channels.'.$key.'.schedule'); }
            if ($publishing && !empty($schedule['enabled']) && ($schedule['closed']??'')==='callback' && !$out['callback']) { return self::error(__('Enable callback requests before using callback as an out-of-hours action.',self::DOMAIN),'callback'); }
            if(($schedule['closed']??'')==='alternative'&&($schedule['alternative']??'')==='callback'){$schedule['closed']='callback';}
            $ch['schedule']=$schedule;
        } unset($ch);
        $rules=class_exists(Rules::class) ? Rules::sanitize($raw['rules']??$defaults['rules']) : $defaults['rules'];
        if (is_wp_error($rules)) { return self::error($rules->get_error_message(),'rules'); }
        $out['rules']=$rules;
        $r=is_array($raw['recovery']??null) ? $raw['recovery'] : [];
        $out['recovery']=$defaults['recovery'];
        foreach (['enabled','prompt'] as $key) { $out['recovery'][$key]=array_key_exists($key,$r) ? !empty($r[$key]) : $defaults['recovery'][$key]; }
        $f=is_array($r['followup']??null)?$r['followup']:[];$out['recovery']['followup']=['enabled'=>!empty($f['enabled']),'delay'=>self::number($f['delay']??10,2,120,10),'title'=>self::text($f['title']??null,100,$defaults['recovery']['followup']['title']),'text'=>self::text($f['text']??null,300,$defaults['recovery']['followup']['text'])];
        $out['recovery']['number_mode']=self::choice($r['number_mode']??'', ['all','include','exclude'],'all');
        $numbers=$r['numbers']??[];if(is_string($numbers))$numbers=preg_split('/[,\r\n]+/',$numbers);
        $out['recovery']['numbers']=[];
        if (is_array($numbers)) {
            foreach(array_slice($numbers,0,100) as $number) {
                $phone=Settings::contact($number);
                if(!$phone || strpos($phone,'@')!==false){return self::error(__('Recovery number filters must contain valid phone numbers.',self::DOMAIN),'recovery.numbers');}
                $out['recovery']['numbers'][]=preg_replace('/\D/','',$phone);
            }
        }
        $out['recovery']['numbers']=array_values(array_unique($out['recovery']['numbers']));
        foreach (['include_ids','exclude_ids'] as $key) { $out['recovery'][$key]=array_values(array_unique(array_filter(array_map('absint',is_array($r[$key]??null)?array_slice($r[$key],0,500):[])))); }
        $out['recovery']['timing']=self::choice($r['timing']??'', ['return','delay','both','manual'],'both');
        $out['recovery']['delay']=self::number($r['delay']??8,2,120,8);
        foreach(['title'=>100,'text'=>300] as $key=>$max){$out['recovery'][$key]=self::text($r[$key]??null,$max,$defaults['recovery'][$key]);}
        if($publishing && $out['recovery']['enabled'] && $out['recovery']['prompt'] && empty($out['recovery']['followup']['enabled']) && !$out['callback']){return self::error(__('Enable callback requests to offer sitewide callback recovery, or switch recovery prompts off.',self::DOMAIN),'callback');}
        $reports=class_exists(Reports::class) ? Reports::sanitize($raw['reports']??$defaults['reports']) : $defaults['reports'];
        if(is_wp_error($reports)){return self::error($reports->get_error_message(),'reports.recipients');}
        $out['reports']=$reports;
        return $out;
    }
    public static function recovery_allowed($c,$context=null) {
        if (empty($c['recovery']['enabled']) && empty($c['recovery']['followup']['enabled'])) { return false; }
        $r=$c['recovery'];$copy=$c;$copy['rules']=['mode'=>empty($r['include_ids'])?'all':'selected','include_ids'=>$r['include_ids']??[],'exclude_ids'=>$r['exclude_ids']??[]];
        return !class_exists(Rules::class) || Rules::evaluate($copy,$context)['visible'];
    }
    public static function export($config,$destinations=false) {
        $config['notification_email']='';
        $config['reports']['recipients']='';$config['reports']['enabled']=false;$config['reports']['details']=false;
        $config['analytics']=false;$config['ga4']=false;$config['ga4_debug']=false;$config['enabled']=false;
        if(!$destinations){foreach($config['channels'] as &$ch){$ch['value']='';}unset($ch);}
        return ['format'=>'fcb-settings','version'=>2,'includes_business_destinations'=>(bool)$destinations,'settings'=>$config];
    }
    public static function import($json) {
        if(!is_string($json)||strlen($json)>150000){return self::error(__('Choose a settings JSON file smaller than 150 KB.',self::DOMAIN),'import');}
        $data=json_decode($json,true);
        if(!is_array($data)||($data['format']??'')!=='fcb-settings'||($data['version']??0)!==2||!is_array($data['settings']??null)){return self::error(__('This is not a supported Contact Buttons settings file.',self::DOMAIN),'import');}
        $raw=$data['settings'];if(isset($raw['reports'])&&!is_array($raw['reports'])){return self::error(__('Report settings must be an object.','floating-chat-buttons-whatsapp-telegram-call'),'import');}$raw['reports']=is_array($raw['reports']??null)?$raw['reports']:[];$raw['enabled']=false;$raw['notification_email']=Settings::draft()['notification_email'];
        $raw['reports']['recipients']=Settings::draft()['reports']['recipients'];$raw['reports']['enabled']=false;$raw['reports']['details']=false;
        $raw['analytics']=false;$raw['ga4']=false;$raw['ga4_debug']=false;
        return Settings::sanitize($raw,false);
    }
}
