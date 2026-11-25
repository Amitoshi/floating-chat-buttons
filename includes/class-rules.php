<?php
namespace AmitShaw\FCB;
defined('ABSPATH') || exit;

/** Predictable frontend targeting. The admin endpoint is responsible for authorization. */
final class Rules {
    public static function defaults() {
        return ['mode'=>'all','include_ids'=>[],'exclude_ids'=>[],'include_types'=>[],'exclude_types'=>[],
            'include_templates'=>[],'exclude_templates'=>[],'include_terms'=>[],'exclude_terms'=>[],
            'include_archives'=>[],'exclude_archives'=>[],'include_urls'=>[],'exclude_urls'=>[]];
    }

    /** Validate imported and admin-submitted rules; never silently broaden a malformed rule. */
    public static function sanitize($input) {
        if (!is_array($input)) { return new \WP_Error('rules', 'Visibility rules must be an object.'); }
        $out = self::defaults();
        if (isset($input['mode'])) {
            if (!in_array($input['mode'], ['all','home','selected'], true)) { return new \WP_Error('rules_mode', 'Choose a valid visibility mode.'); }
            $out['mode'] = $input['mode'];
        }
        foreach (['include','exclude'] as $direction) {
            foreach (['ids','types','templates','terms','archives','urls'] as $kind) {
                $field = $direction . '_' . $kind;
                $values = $input[$field] ?? [];
                if (is_string($values)) { $values = preg_split('/[\r\n]+/', $values, -1, PREG_SPLIT_NO_EMPTY); }
                if (!is_array($values) || count($values)>250) { return new \WP_Error('rules_'.$field, 'Use a list of up to 250 values for '.$field.'.'); }
                foreach ($values as $value) {
                    if (!is_scalar($value) || is_bool($value)) { return new \WP_Error('rules_'.$field, 'Invalid value in '.$field.'.'); }
                    $value = trim((string)$value);
                    if ($value === '') { continue; }
                    $valid = strlen($value)<=1000 && !preg_match('/[\x00-\x1f\x7f]/', $value);
                    if ($kind==='ids') { $valid = $valid && ctype_digit($value) && (int)$value>0; $value = (int)$value; }
                    if ($kind==='types') { $valid = $valid && (bool)preg_match('/^[a-zA-Z0-9_-]+$/D', $value); }
                    if ($kind==='templates') { $valid = $valid && strpos($value,'..')===false && (bool)preg_match('/^[a-zA-Z0-9_\/.-]+$/D',$value); }
                    if ($kind==='terms') { $valid = $valid && (bool)preg_match('/^[a-zA-Z0-9_-]+:[1-9][0-9]*$/D',$value); }
                    if ($kind==='archives') { $valid = $valid && (bool)preg_match('/^(archive|home|search|404|author|date|post_type:[a-zA-Z0-9_-]+|taxonomy:[a-zA-Z0-9_-]+)$/D',$value); }
                    if ($kind==='urls') {
                        $value = explode('#',$value,2)[0];
                        $valid = $valid && (bool)preg_match('~^(https?://[^\s/]+(?:/[^\s]*)?|/[^\s]*)$~iD',$value) && strpos($value,'//')!==0;
                        if (strpos($value,'://')!==false && (wp_parse_url($value,PHP_URL_USER) || wp_parse_url($value,PHP_URL_PASS))) { $valid=false; }
                    }
                    if (!$valid) { return new \WP_Error('rules_'.$field, 'Enter a valid value for '.$field.'.'); }
                    if (!in_array($value,$out[$field],true)) { $out[$field][]=$value; }
                }
            }
        }
        return $out;
    }

    public static function evaluate($config, $context = null) {
        $context = $context === null ? self::current_context() : $context;
        if (is_wp_error($context) || !is_array($context)) { return ['visible'=>false,'reason'=>'This page could not be resolved.']; }
        $context = array_merge(['id'=>0,'type'=>'','template'=>'default','terms'=>[],'archives'=>[],'home'=>false,'admin'=>false,'login'=>false,'url'=>''], $context);
        if ($context['admin'] || $context['login']) { return ['visible'=>false,'reason'=>'The public widget is hidden on administration and login pages.']; }
        $rules = self::sanitize($config['rules'] ?? self::defaults());
        if (is_wp_error($rules)) { return ['visible'=>false,'reason'=>'Visibility rules need correction: '.$rules->get_error_message()]; }
        $excluded = self::match($rules, 'exclude', $context);
        if ($excluded) { return ['visible'=>false,'reason'=>'Excluded by '.$excluded.'. Exclusions take priority.']; }
        if ($rules['mode']==='all') { return ['visible'=>true,'reason'=>'Shown across the website; no exclusion matches this page.']; }
        if ($rules['mode']==='home') { return ['visible'=>(bool)$context['home'],'reason'=>$context['home'] ? 'Shown because this is the homepage.' : 'Hidden because only the homepage is selected.']; }
        $included = self::match($rules,'include',$context);
        return ['visible'=>(bool)$included,'reason'=>$included ? 'Shown by '.$included.'.' : 'Hidden because no selected inclusion matches this page.'];
    }

    private static function match($rules, $direction, $context) {
        if ($context['id'] && in_array((int)$context['id'],$rules[$direction.'_ids'],true)) { return 'the selected page or post'; }
        if ($context['type'] && in_array($context['type'],$rules[$direction.'_types'],true)) { return 'post type '.$context['type']; }
        if ($context['id'] && in_array($context['template'],$rules[$direction.'_templates'],true)) { return 'template '.$context['template']; }
        if (array_intersect((array)$context['terms'],$rules[$direction.'_terms'])) { return 'a selected category or taxonomy term'; }
        if (array_intersect((array)$context['archives'],$rules[$direction.'_archives'])) { return 'a selected archive view'; }
        foreach ($rules[$direction.'_urls'] as $pattern) {
            if (self::url_matches($pattern,$context['url'])) { return 'URL pattern '.$pattern; }
        }
        return '';
    }

    private static function url_matches($pattern, $url) {
        if (!$url) { return false; }
        if (strpos($pattern,'://')!==false) {
            $parts=wp_parse_url($url); $rule=wp_parse_url($pattern);
            if (!is_array($parts) || !is_array($rule) || strtolower($parts['host'] ?? '')!==strtolower($rule['host'] ?? '') || ($parts['port'] ?? null)!==($rule['port'] ?? null)) { return false; }
            $url=($parts['scheme'] ?? 'https').'://'.strtolower($parts['host']).(isset($parts['port'])?':'.$parts['port']:'').($parts['path'] ?? '/').(isset($rule['query'])?'?'.($parts['query'] ?? ''):'');
            $pattern=($rule['scheme'] ?? 'https').'://'.strtolower($rule['host']).(isset($rule['port'])?':'.$rule['port']:'').($rule['path'] ?? '/').(isset($rule['query'])?'?'.$rule['query']:'');
        } else {
            $url=(wp_parse_url($url,PHP_URL_PATH) ?: '/').(strpos($pattern,'?')!==false?'?'.(wp_parse_url($url,PHP_URL_QUERY) ?: ''):'');
        }
        if (strpos($pattern,'?')===false) { $pattern=rtrim($pattern,'/'); $url=rtrim($url,'/'); }
        $regex=str_replace('\\*','.*',preg_quote($pattern,'~'));
        return (bool)preg_match('~^'.$regex.'$~uD',$url);
    }

    /** Public post objects only. This method never returns private post data. */
    public static function lookup($kind, $query = '') {
        $query=mb_substr(sanitize_text_field(is_string($query)?$query:''),0,100); $items=[];
        $types=get_post_types(['public'=>true],'objects');
        if ($kind==='types') {
            foreach ($types as $type) { $items[]=['id'=>$type->name,'label'=>$type->labels->singular_name]; }
        } elseif ($kind==='templates') {
            $items[]=['id'=>'default','label'=>'Default template'];
            foreach ($types as $type) {
                foreach (wp_get_theme()->get_page_templates(null,$type->name) as $file=>$label) { $items[]=['id'=>$file,'label'=>$label]; }
            }
            if (function_exists('get_block_templates') && wp_is_block_theme()) {
                foreach (get_block_templates([],'wp_template') as $template) { $items[]=['id'=>$template->slug,'label'=>$template->title]; }
            }
        } elseif ($kind==='terms') {
            $taxonomies=get_taxonomies(['public'=>true]);
            $terms=get_terms(['taxonomy'=>array_values($taxonomies),'hide_empty'=>false,'search'=>$query,'number'=>50]);
            if (!is_wp_error($terms)) { foreach ($terms as $term) { $items[]=['id'=>$term->taxonomy.':'.$term->term_id,'label'=>$term->name.' ('.$term->taxonomy.')']; } }
            return $items;
        } elseif ($kind==='archives') {
            foreach (['archive'=>'All archive views','home'=>'Posts index','search'=>'Search results','404'=>'Page not found','author'=>'Author archives','date'=>'Date archives'] as $id=>$label) { $items[]=['id'=>$id,'label'=>$label]; }
            foreach ($types as $type) { if ($type->has_archive) { $items[]=['id'=>'post_type:'.$type->name,'label'=>$type->label.' archive']; } }
            foreach (get_taxonomies(['public'=>true],'objects') as $taxonomy) { $items[]=['id'=>'taxonomy:'.$taxonomy->name,'label'=>$taxonomy->label.' archives']; }
        } else {
            $post_types=$kind==='pages' ? ['page'] : ($kind==='posts' ? ['post'] : (isset($types[$kind]) ? [$kind] : []));
            if (!$post_types) { return []; }
            $posts=get_posts(['post_type'=>$post_types,'post_status'=>'publish','s'=>$query,'posts_per_page'=>30,'orderby'=>'title','order'=>'ASC','suppress_filters'=>false]);
            foreach ($posts as $post) { $items[]=['id'=>$post->ID,'label'=>get_the_title($post).' ('.$post->post_type.' #'.$post->ID.')']; }
            return $items;
        }
        $unique=[];
        foreach ($items as $item) { if ($query==='' || stripos($item['label'].' '.$item['id'],$query)!==false) { $unique[(string)$item['id']]=$item; } }
        return array_slice(array_values($unique),0,50);
    }

    public static function current_context() {
        global $wp_query, $pagenow;
        $url=home_url('/');
        if (!empty($_SERVER['REQUEST_URI'])) {
            $base=wp_parse_url(home_url('/'));
            $url=($base['scheme'] ?? 'https').'://'.$base['host'].(isset($base['port'])?':'.$base['port']:'').'/'.ltrim(wp_unslash($_SERVER['REQUEST_URI']),'/');
        }
        $context=self::query_context($wp_query instanceof \WP_Query ? $wp_query : null,$url);
        $context['admin']=is_admin(); $context['login']=$pagenow==='wp-login.php';
        return $context;
    }

    private static function query_context($query, $url) {
        $context=['id'=>0,'type'=>'','template'=>'default','terms'=>[],'archives'=>[],'home'=>false,'admin'=>false,'login'=>false,'url'=>$url];
        if (!$query) { return $context; }
        $context['home']=$query->is_front_page();
        if ($query->is_singular()) {
            $post=$query->get_queried_object();
            if ($post instanceof \WP_Post) {
                $context['id']=(int)$post->ID; $context['type']=$post->post_type; $context['template']=get_page_template_slug($post->ID) ?: 'default';
                $terms=wp_get_object_terms($post->ID,get_object_taxonomies($post->post_type));
                if (!is_wp_error($terms)) { foreach ($terms as $term) { $context['terms'][]=$term->taxonomy.':'.$term->term_id; } }
            }
        }
        if ($query->is_archive()) { $context['archives'][]='archive'; }
        foreach (['home','search','404','author','date'] as $kind) { $method='is_'.$kind; if ($query->$method()) { $context['archives'][]=$kind; } }
        if ($query->is_post_type_archive()) {
            foreach ((array)$query->get('post_type') as $type) { $context['archives'][]='post_type:'.$type; }
        }
        if ($query->is_tax() || $query->is_category() || $query->is_tag()) {
            $term=$query->get_queried_object();
            if ($term instanceof \WP_Term) { $context['terms'][]=$term->taxonomy.':'.$term->term_id; $context['archives'][]='taxonomy:'.$term->taxonomy; }
        }
        return $context;
    }

    /** Resolve same-site URLs without replacing the global query or changing request state. */
    public static function context_for_url($url) {
        if (!is_string($url) || strlen($url)>2048 || !filter_var($url,FILTER_VALIDATE_URL)) { return new \WP_Error('rules_url','Enter a complete website URL.'); }
        $parts=wp_parse_url($url);$home=wp_parse_url(home_url('/'));
        if (!in_array(strtolower($parts['scheme'] ?? ''),['http','https'],true) || strtolower($parts['host'] ?? '')!==strtolower($home['host'] ?? '') || ($parts['port'] ?? null)!==($home['port'] ?? null) || isset($parts['user']) || isset($parts['pass'])) { return new \WP_Error('rules_url','Choose a URL on this website.'); }
        $path=$parts['path'] ?? '/'; $base=untrailingslashit($home['path'] ?? '');
        if ($base && $path!==$base && strpos($path,$base.'/')!==0) { return new \WP_Error('rules_url','Choose a URL within this website.'); }
        $context=self::query_context(null,$url);
        $admin_path=untrailingslashit(wp_parse_url(admin_url(),PHP_URL_PATH));
        $login_path=wp_parse_url(wp_login_url(),PHP_URL_PATH);
        $context['admin']=$path===$admin_path || strpos($path,$admin_path.'/')===0;
        $context['login']=$path===$login_path;
        if ($context['admin'] || $context['login']) { return $context; }
        $id=url_to_postid($url);
        if ($id) {
            $post=get_post($id);
            if (!$post || $post->post_status!=='publish' || !is_post_type_viewable($post->post_type)) { return $context; }
            return self::query_context(new \WP_Query(['p'=>$id,'post_type'=>$post->post_type]),$url);
        }
        $vars=[];parse_str($parts['query'] ?? '',$vars);
        global $wp,$wp_rewrite;
        $vars=array_intersect_key($vars,array_flip($wp->public_query_vars));
        $relative=ltrim(substr($path,strlen($base)),'/');
        if ($relative!=='') {
            foreach ((array)$wp_rewrite->wp_rewrite_rules() as $match=>$query) {
                if (preg_match('#^'.$match.'#',$relative,$matches) || preg_match('#^'.$match.'#',urldecode($relative),$matches)) {
                    $rewritten=\WP_MatchesMapRegex::apply(preg_replace('!^.+\?!','',$query),$matches);
                    $rewrite_vars=[];parse_str($rewritten,$rewrite_vars);
                    $vars=array_merge(array_intersect_key($rewrite_vars,array_flip($wp->public_query_vars)),$vars);break;
                }
            }
        }
        if (!$vars && $relative!=='') { $context['archives']=['404']; return $context; }
        if (!$vars && get_option('show_on_front')==='page' && get_option('page_on_front')) { $vars=['page_id'=>(int)get_option('page_on_front')]; }
        // WP_Query's constructor does not execute an empty query array (the posts homepage).
        $query=new \WP_Query(); $query->query($vars);
        // WP::handle_404 normally applies this after the main query; previews use a local query.
        if ($query->is_singular() && !$query->post_count) { $query->set_404(); }
        $resolved=self::query_context($query,$url);
        return $resolved;
    }
}
