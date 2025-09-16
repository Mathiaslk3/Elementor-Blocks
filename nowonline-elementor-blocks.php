<?php
/**
 * Plugin Name: NowOnline – Elementor Blocks
 * Description: Allow-list Elementor templates as Gutenberg block variations, with typed placeholders ([[text]], [[rich]], [[img]], [[bg]], [[url]], [[p]], [[h1]]..[[h6]]).
 * Version: 2.13.6
 * Author: NowOnline
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
if (!defined('NOWONLINE_ELT_FILE')) { define('NOWONLINE_ELT_FILE', __FILE__); }

/** PSR-4 autoloader */
spl_autoload_register(function($class){
    $prefix = 'NowOnline\\EltBlocks\\';
    if (strpos($class, $prefix) !== 0) return;
    $rel  = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\','/',$rel) . '.php';
    if (is_readable($path)) require_once $path;
});
/** Fallback boot */
if (!class_exists('NowOnline\\EltBlocks\\Plugin')) {
    $fallback = __DIR__ . '/src/Plugin.php';
    if (is_readable($fallback)) require_once $fallback;
}
\NowOnline\EltBlocks\Plugin::boot();

/* ------------------------------- TinyMCE ---------------------------------- */
add_filter('mce_buttons', function(array $btns){
    foreach (['fontselect','fontsizeselect','forecolor'] as $b) if (!in_array($b, $btns, true)) array_unshift($btns, $b);
    return $btns;
}, 100);
add_filter('mce_buttons_2', function(array $btns){
    foreach (['fontselect','fontsizeselect','forecolor'] as $b) if (!in_array($b, $btns, true)) array_unshift($btns, $b);
    return $btns;
}, 100);
add_filter('teeny_mce_buttons', function(array $btns){
    if (!in_array('fontsizeselect', $btns, true)) array_unshift($btns, 'fontsizeselect');
    return $btns;
}, 100);
add_filter('tiny_mce_before_init', function(array $init){
    $init['fontsize_formats'] = $init['fontsize_formats'] ?? '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';
    $init['font_formats'] = $init['font_formats'] ?? (
        'Montserrat=Montserrat, Arial, sans-serif;'.
        'System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'.
        'Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;'.
        'Courier New=Courier New, Courier, monospace'
    );
    $t1 = $init['toolbar1'] ?? 'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo';
    foreach (['fontselect','fontsizeselect'] as $b) if (strpos($t1, $b) === false) $t1 = $b.' '.$t1;
    if (strpos($t1, 'forecolor') === false) $t1 .= ',forecolor';
    $init['toolbar1'] = $t1;
    return $init;
}, 100);
add_filter('wp_editor_settings', function(array $settings){
    $settings['teeny'] = false;
    $settings['tinymce'] = is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
    if (empty($settings['tinymce']['fontsize_formats'])) $settings['tinymce']['fontsize_formats'] = '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';
    return $settings;
}, 100);
add_filter('wp_kses_allowed_html', function(array $tags, string $ctx){
    if ($ctx !== 'post') return $tags;
    foreach (['span','p','div','h1','h2','h3','h4','h5','h6'] as $t) { $tags[$t]['style'] = true; }
    return $tags;
}, 100, 2);

/* ----------------------------- Fonts enqueue ------------------------------- */
add_action('admin_enqueue_scripts', function(){
    $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
    if (!empty($url)) wp_enqueue_style('nowonline-elt-font', $url, [], null);
});
add_action('wp_enqueue_scripts', function(){
    $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
    if (!is_admin() && !empty($url)) wp_enqueue_style('nowonline-elt-font', $url, [], null);
}, 5);

/* ---------------------- Admin: inline TinyMCE patch ------------------------ */
add_action('admin_head', function () {
    ?>
<script>(function(){function e(n){n=n||{};var t=(n.toolbar1||'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo');var p=t.split(/[, ]+/).filter(Boolean);['fontselect','fontsizeselect'].forEach(function(b){if(p.indexOf(b)===-1)p.unshift(b)});if(p.indexOf('forecolor')===-1)p.push('forecolor');n.toolbar1=p.join(',');n.fontsize_formats=n.fontsize_formats||'12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';n.font_formats=n.font_formats||'Montserrat=Montserrat, Arial, sans-serif;System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;Courier New=Courier New, Courier, monospace';return n}if(window.tinyMCEPreInit&&tinyMCEPreInit.mceInit){Object.keys(tinyMCEPreInit.mceInit).forEach(function(k){tinyMCEPreInit.mceInit[k]=e(tinyMCEPreInit.mceInit[k])})}var r=function(){if(!window.tinymce||!tinymce.init||tinymce.__nowonlineWrapped)return!!window.tinymce;var o=tinymce.init;tinymce.init=function(i){i=e(i||{});return o.call(this,i)};tinymce.__nowonlineWrapped=true;return true};if(!r()){var iv=setInterval(function(){if(r())clearInterval(iv)},10);setTimeout(function(){clearInterval(iv);r()},5000)}if(window.wp&&wp.editor&&typeof wp.editor.initialize==='function'&&!wp.editor.__nowonlineWrapped){var oi=wp.editor.initialize;wp.editor.initialize=function(id,s){s=s||{};s.tinymce=e(s.tinymce||{});return oi.call(this,id,s)};wp.editor.__nowonlineWrapped=true}})();</script>
    <?php
}, 0);

/* -------------------- Server-side heading sanitizers ----------------------- */
add_action('elementor/frontend/widget/before_render', function ($widget) {
    try {
        if (!method_exists($widget, 'get_name') || $widget->get_name() !== 'heading') return;
        $title = $widget->get_settings('title');
        if (!is_string($title) || $title === '' || stripos($title, '<h') === false) return;
        $clean = preg_replace('/<\/?h[1-6][^>]*>/i', '', $title);
        if ($clean !== null && $clean !== $title) $widget->set_settings('title', $clean);
    } catch (Throwable $e) {}
}, 9);

add_filter('elementor/widget/render_content', function ($content, $widget) {
    try {
        if (!is_string($content) || $content === '') return $content;
        if (!method_exists($widget, 'get_name') || $widget->get_name() !== 'heading') return $content;
        if (stripos($content, 'elementor-heading-title') === false) return $content;

        $wrap_html = '<div id="_nowonline_head_wrap">' . $content . '</div>';
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrap_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        $xp = new DOMXPath($dom);

        $container = $xp->query('//*[@id="_nowonline_head_wrap"]//div[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ")]')->item(0);
        if (!$container) return $content;

        $title = null;
        foreach (['h1','h2','h3','h4','h5','h6'] as $tag) {
            $node = $xp->query('.//'.$tag.'[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]', $container)->item(0);
            if ($node) { $title = $node; break; }
        }
        if (!$title) return $content;

        $extras = [];
        for ($n = $title->nextSibling; $n; $n = $n->nextSibling) {
            if ($n->nodeType !== XML_ELEMENT_NODE) break;
            $tn = strtolower($n->nodeName);
            if (!in_array($tn, ['h1','h2','h3','h4','h5','h6'], true)) break;
            $extras[] = $n;
        }
        if (!$extras) return $content;

        $first = $extras[0];
        $desired = strtolower($first->tagName);
        $baseStyle = $title->hasAttribute('style') ? trim($title->getAttribute('style')) : '';
        $userStyle = $first->hasAttribute('style') ? trim($first->getAttribute('style')) : '';
        $mergedStyle = trim(($baseStyle ? $baseStyle.'; ' : '') . $userStyle);
        $move = static function(DOMElement $from, DOMElement $to){ while ($from->firstChild) { $to->appendChild($from->firstChild); } };

        if (strtolower($title->tagName) !== $desired) {
            $repl = $dom->createElement($desired);
            if ($title->hasAttributes()) foreach (iterator_to_array($title->attributes) as $attr) $repl->setAttribute($attr->name, $attr->value);
            foreach ($extras as $ex) $move($ex, $repl);
            $title->parentNode->replaceChild($repl, $title);
            $title = $repl;
        } else {
            while ($title->firstChild) $title->removeChild($title->firstChild);
            foreach ($extras as $ex) $move($ex, $title);
        }
        if ($mergedStyle !== '') $title->setAttribute('style', $mergedStyle);
        foreach ($extras as $ex) if ($ex->parentNode) $ex->parentNode->removeChild($ex);

        $wrap = $dom->getElementById('_nowonline_head_wrap'); if (!$wrap) return $content;
        $out = ''; foreach ($wrap->childNodes as $child) $out .= $dom->saveHTML($child);
        return $out;
    } catch (Throwable $e) { return $content; }
}, 20, 2);

/* ---------------- Conditional JS fix enqueue (default: OFF) ---------------- */
if (!function_exists('nowonline_elt_should_enqueue_heading_js_fix')) {
    function nowonline_elt_should_enqueue_heading_js_fix(): bool {
        $enable = apply_filters('nowonline_elt_enqueue_heading_js_fix', false);
        if (isset($_GET['nowonline_js_fix'])) $enable = ($_GET['nowonline_js_fix'] === '1');
        return $enable;
    }
}
add_action('wp_enqueue_scripts', function () {
    if (!nowonline_elt_should_enqueue_heading_js_fix()) return;
    $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/fix-headings.js';
    $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
    wp_enqueue_script('nowonline-elt-fix-headings', plugins_url('assets/fix-headings.js', NOWONLINE_ELT_FILE), [], $ver, true);
}, 20);
add_action('elementor/frontend/after_enqueue_scripts', function () {
    if (!nowonline_elt_should_enqueue_heading_js_fix()) return;
    if (wp_script_is('nowonline-elt-fix-headings', 'enqueued')) return;
    $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/fix-headings.js';
    $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
    wp_enqueue_script('nowonline-elt-fix-headings', plugins_url('assets/fix-headings.js', NOWONLINE_ELT_FILE), [], $ver, true);
}, 20);

/* ----------------------- Design: container background ---------------------- */
add_action('enqueue_block_editor_assets', function(){
    $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/design-controls.js';
    $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
    wp_enqueue_script(
        'nowonline-elt-design-controls',
        plugins_url('assets/design-controls.js', NOWONLINE_ELT_FILE),
        ['wp-element','wp-blocks','wp-components','wp-hooks','wp-i18n','wp-edit-post','wp-block-editor'],
        $ver,
        true
    );
    $slug = apply_filters('nowonline_elt_block_slug', 'nowonline/elt');
    $inl  = 'window.nowonlineEltBlockSlug = ' . wp_json_encode($slug) . ';';
    wp_add_inline_script('nowonline-elt-design-controls', $inl, 'before');
});

add_filter('render_block', function( $html, $block ){
    try {
        $slug = apply_filters('nowonline_elt_block_slug', 'nowonline/elt');
        if ( ($block['blockName'] ?? null) !== $slug ) return $html;
        $bg = $block['attrs']['containerBg'] ?? '';
        if (!is_string($bg) || $bg === '') return $html;
        $bg = trim($bg);
        $allow = (
            preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $bg) ||
            preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $bg) ||
            preg_match('/^(rgb|rgba|hsl|hsla)\([^\)]+\)$/', $bg)
        );
        if (!$allow) return $html;
        if (preg_match('/^<([a-z0-9:-]+)\b[^>]*>/i', $html, $m)) {
            $open = $m[0];
            if (preg_match('/\sstyle=\"([^\"]*)\"/i', $open, $sm)) {
                $style = trim($sm[1]);
                $style = preg_replace('/background(?:-color)?\s*:\s*[^;]*;?/i', '', $style);
                $style = trim($style);
                $style = ($style ? $style.'; ' : '') . 'background-color: '.$bg.';';
                $open2 = preg_replace('/style=\"[^\"]*\"/i', 'style="'.esc_attr($style).'"', $open, 1);
            } else {
                $open2 = rtrim(substr($open, 0, -1)) . ' style="'.esc_attr('background-color: '.$bg.';').'">';
            }
            $html = $open2 . substr($html, strlen($open));
        }
        return $html;
    } catch (Throwable $e) {
        return $html;
    }
}, 10, 2);
