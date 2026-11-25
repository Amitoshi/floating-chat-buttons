<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Per-channel business hours, evaluated in local wall time including DST transitions. */
final class Schedule {
    public static function defaults() {
        return ['enabled'=>false,'timezone'=>'site','intervals'=>[['days'=>[1,2,3,4,5,6],'open'=>'09:00','close'=>'18:00']],
            'closed'=>'hide','message'=>"We're currently closed. Please try again during business hours.",'alternative'=>'whatsapp'];
    }

    public static function sanitize($input) {
        if (!is_array($input)) { return new \WP_Error('schedule','Business hours must be an object.'); }
        $out=self::defaults(); $out['enabled']=!empty($input['enabled']);
        if (isset($input['timezone'])) {
            if (!is_string($input['timezone']) || ($input['timezone']!=='site' && !in_array($input['timezone'],\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),true))) { return new \WP_Error('schedule_timezone','Choose the website timezone or a valid IANA timezone.'); }
            $out['timezone']=$input['timezone'];
        }
        if (isset($input['closed'])) {
            if (!in_array($input['closed'],['hide','unavailable','callback','alternative'],true)) { return new \WP_Error('schedule_closed','Choose a valid closed-hours action.'); }
            $out['closed']=$input['closed'];
        }
        if (isset($input['message'])) {
            if (!is_string($input['message'])) { return new \WP_Error('schedule_message','Enter a closed-hours message.'); }
            $out['message']=mb_substr(sanitize_text_field($input['message']),0,300);
        }
        if (isset($input['alternative'])) {
            if (!in_array($input['alternative'],['whatsapp','call','sms','telegram','messenger','email','custom','callback'],true)) { return new \WP_Error('schedule_alternative','Choose an alternative contact channel.'); }
            $out['alternative']=$input['alternative'];
        }
        if (isset($input['intervals'])) {
            if (!is_array($input['intervals']) || count($input['intervals'])>100) { return new \WP_Error('schedule_intervals','Use up to 100 business-hour intervals.'); }
            $out['intervals']=[];
            foreach ($input['intervals'] as $interval) {
                if (!is_array($interval) || empty($interval['days']) || !is_array($interval['days'])) { return new \WP_Error('schedule_days','Choose at least one weekday for each interval.'); }
                $days=[];
                foreach ($interval['days'] as $day) {
                    if ((!is_int($day) && !is_string($day)) || !preg_match('/^[1-7]$/D',(string)$day)) { return new \WP_Error('schedule_days','Weekdays must be Monday (1) through Sunday (7).'); }
                    $days[]=(int)$day;
                }
                foreach (['open','close'] as $key) {
                    if (!isset($interval[$key]) || !is_string($interval[$key]) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$interval[$key])) { return new \WP_Error('schedule_time','Enter each time in 24-hour HH:MM format.'); }
                }
                if ($interval['open']===$interval['close']) { return new \WP_Error('schedule_time','Opening and closing times must differ. Disable restrictions for 24-hour availability.'); }
                $days=array_values(array_unique($days));sort($days);
                $out['intervals'][]=['days'=>$days,'open'=>$interval['open'],'close'=>$interval['close']];
            }
        }
        return $out;
    }

    /** $at is an instant: Unix seconds, DateTimeInterface, or ISO-8601 with offset. */
    public static function state($channel, $at = null) {
        if (!is_array($channel) || (array_key_exists('enabled',$channel) && !$channel['enabled'])) { return ['open'=>false,'action'=>'hide','message'=>'','alternative'=>'']; }
        $schedule=self::sanitize($channel['schedule'] ?? self::defaults());
        if (is_wp_error($schedule)) { return ['open'=>false,'action'=>'unavailable','message'=>'Contact availability is not configured correctly.','alternative'=>'']; }
        if (!$schedule['enabled']) { return ['open'=>true,'action'=>'direct','message'=>'','alternative'=>'']; }
        try {
            $timezone=$schedule['timezone']==='site' ? wp_timezone() : new \DateTimeZone($schedule['timezone']);
            if ($at instanceof \DateTimeInterface) { $instant=(new \DateTimeImmutable('@'.$at->getTimestamp()))->setTimezone($timezone); }
            elseif (is_int($at) || (is_string($at) && ctype_digit($at))) { $instant=(new \DateTimeImmutable('@'.$at))->setTimezone($timezone); }
            else { $instant=(new \DateTimeImmutable($at === null ? 'now' : (string)$at,new \DateTimeZone('UTC')))->setTimezone($timezone); }
            $day=(int)$instant->format('N');$previous=$day===1?7:$day-1;$time=$instant->format('H:i');
            foreach ($schedule['intervals'] as $interval) {
                if ($interval['close']>$interval['open']) {
                    $open=in_array($day,$interval['days'],true) && $time>=$interval['open'] && $time<$interval['close'];
                } else {
                    $open=(in_array($day,$interval['days'],true) && $time>=$interval['open']) || (in_array($previous,$interval['days'],true) && $time<$interval['close']);
                }
                if ($open) { return ['open'=>true,'action'=>'direct','message'=>'','alternative'=>'']; }
            }
        } catch (\Exception $error) { return ['open'=>false,'action'=>'unavailable','message'=>'Contact availability could not be determined.','alternative'=>'']; }
        return ['open'=>false,'action'=>$schedule['closed'],'message'=>$schedule['message'],'alternative'=>$schedule['closed']==='alternative'?$schedule['alternative']:''];
    }

    /** Annotate a copy. Saved enabled flags, destinations, and original schedules stay intact. */
    public static function apply($config, $at = null) {
        if (!is_array($config)) { return []; }
        $config['callback_state']=self::state(['enabled'=>!empty($config['callback']),'schedule'=>$config['callback_schedule'] ?? self::defaults()],$at);
        foreach ((array)($config['channels'] ?? []) as $key=>$channel) {
            if (!is_array($channel)) { continue; }
            $config['channels'][$key]['schedule_state']=self::state($channel,$at);
        }
        foreach ((array)($config['channels'] ?? []) as $key=>$channel) {
            if (!is_array($channel)) { continue; }
            $state=$channel['schedule_state'];
            if ($state['action']==='callback' && !$config['callback_state']['open']) { $state['action']='unavailable'; }
            if ($state['action']==='alternative') {
                $other=$state['alternative'];
                $available=$other==='callback' ? $config['callback_state']['open'] : ($other!==$key && !empty($config['channels'][$other]['enabled']) && !empty($config['channels'][$other]['schedule_state']['open']));
                if (!$available) { $state['action']='unavailable';$state['alternative']=''; }
            }
            $config['channels'][$key]['schedule_state']=$state;
            $config['channels'][$key]['available']=$state['open'];
        }
        return $config;
    }
}
