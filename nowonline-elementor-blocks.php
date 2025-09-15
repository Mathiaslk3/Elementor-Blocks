<?php
/**
 * File: nowonline-elementor-blocks.php
 * Plugin Name: NowOnline – Elementor Blocks
 * Description: Allow-list Elementor templates as Gutenberg block variations, with typed placeholders ([[text]], [[rich]], [[img]], [[bg]], [[url]], [[p]], [[h1]]..[[h6]]). Includes thumbnails, field mapping and diagnostics.
 * Version: 2.12.19
 * Author: NowOnline
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('NOWONLINE_ELT_FILE')) {
    define('NOWONLINE_ELT_FILE', __FILE__);
}

/** PSR-4 autoloader for NowOnline\EltBlocks\* */
spl_autoload_register(function($class){
    $prefix = 'NowOnline\\EltBlocks\\';
    if (strpos($class, $prefix) !== 0) return;
    $rel  = substr($class, strlen($prefix)); // e.g. "Assets\Assets"
    $path = __DIR__ . '/src/' . str_replace('\\','/',$rel) . '.php';
    if (is_readable($path)) require_once $path;
});

/** Fallback: indlæs Plugin.php direkte hvis autoloader ikke har gjort det */
if (!class_exists('NowOnline\\EltBlocks\\Plugin')) {
    $fallback = __DIR__ . '/src/Plugin.php';
    if (is_readable($fallback)) require_once $fallback;
}

/** Boot plugin */
\NowOnline\EltBlocks\Plugin::boot();

/* -------------------------------------------------------------------------
 * TinyMCE kontroller (font family/size/farve) – Crocoblock-sikker
 * ------------------------------------------------------------------------- */

/* Server-side fallback (hvis nogen init’er TinyMCE korrekt via WP) */
add_filter('mce_buttons', function(array $btns){
    foreach (['fontselect','fontsizeselect','forecolor'] as $b) {
        if (!in_array($b, $btns, true)) array_unshift($btns, $b);
    }
    return $btns;
}, 100);

add_filter('mce_buttons_2', function(array $btns){
    foreach (['fontselect','fontsizeselect','forecolor'] as $b) {
        if (!in_array($b, $btns, true)) array_unshift($btns, $b);
    }
    return $btns;
}, 100);

add_filter('teeny_mce_buttons', function(array $btns){
    if (!in_array('fontsizeselect', $btns, true)) array_unshift($btns, 'fontsizeselect');
    return $btns;
}, 100);

add_filter('tiny_mce_before_init', function(array $init){
    $init['fontsize_formats'] = $init['fontsize_formats'] ?? '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px';
    $init['font_formats'] = $init['font_formats'] ?? (
        'Montserrat=Montserrat, Arial, sans-serif;'.
        'System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'.
        'Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;'.
        'Courier New=Courier New, Courier, monospace'
    );
    $t1 = $init['toolbar1'] ?? 'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo';
    foreach (['fontselect','fontsizeselect'] as $b) { if (strpos($t1, $b) === false) $t1 = $b.' '.$t1; }
    if (strpos($t1, 'forecolor') === false) $t1 .= ',forecolor';
    $init['toolbar1'] = $t1;
    return $init;
}, 100);

add_filter('wp_editor_settings', function(array $settings){
    $settings['teeny'] = false;
    if (!isset($settings['tinymce']) || !is_array($settings['tinymce'])) $settings['tinymce'] = [];
    if (empty($settings['tinymce']['fontsize_formats'])) {
        $settings['tinymce']['fontsize_formats'] = '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px';
    }
    return $settings;
}, 100);

/* Tillad inline styles for at bevare valg */
add_filter('wp_kses_allowed_html', function(array $tags, string $ctx){
    if ($ctx !== 'post') return $tags;
    foreach (['span','p','div','h1','h2','h3','h4','h5','h6'] as $t) {
        if (!isset($tags[$t])) $tags[$t] = [];
        $tags[$t]['style'] = true; // hvorfor: font-size/family/color
    }
    return $tags;
}, 100, 2);

/* FRONT + ADMIN: indlæs Montserrat (kan overrides via filter) */
add_action('admin_enqueue_scripts', function(){
    $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
    if (!empty($url)) wp_enqueue_style('nowonline-elt-font', $url, [], null);
});
add_action('wp_enqueue_scripts', function(){
    $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
    if (!is_admin() && !empty($url)) wp_enqueue_style('nowonline-elt-font', $url, [], null);
}, 5);

/* Client-side: EARLY admin patch – før Crocoblock init */
add_action('admin_head', function () {
    ?>
    <script>
    (function(){
      function ensure(init){
        init = init || {};
        var t1 = (init.toolbar1 || 'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo');
        var parts = t1.split(/[, ]+/).filter(Boolean);
        ['fontselect','fontsizeselect'].forEach(function(btn){ if (parts.indexOf(btn) === -1) parts.unshift(btn); });
        if (parts.indexOf('forecolor') === -1) parts.push('forecolor');
        init.toolbar1 = parts.join(',');
        init.fontsize_formats = init.fontsize_formats || '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px';
        init.font_formats = init.font_formats || 'Montserrat=Montserrat, Arial, sans-serif;System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;Courier New=Courier New, Courier, monospace';
        return init;
      }
      // 1) Patch allerede forberedte configs
      if (window.tinyMCEPreInit && tinyMCEPreInit.mceInit) {
        Object.keys(tinyMCEPreInit.mceInit).forEach(function(key){
          tinyMCEPreInit.mceInit[key] = ensure(tinyMCEPreInit.mceInit[key]);
        });
      }
      // 2) Wrap tinymce.init før andre scripts kalder den
      var tryWrap = function(){
        if (!window.tinymce || !tinymce.init || tinymce.__nowonlineWrapped) return !!window.tinymce;
        var orig = tinymce.init;
        tinymce.init = function(opts){
          opts = ensure(opts || {});
          return orig.call(this, opts);
        };
        tinymce.__nowonlineWrapped = true;
        return true;
      };
      if (!tryWrap()){
        var iv = setInterval(function(){ if (tryWrap()) clearInterval(iv); }, 10);
        setTimeout(function(){ clearInterval(iv); tryWrap(); }, 5000);
      }
      // 3) Wrap wp.editor.initialize (klassisk WP init)
      if (window.wp && wp.editor && typeof wp.editor.initialize === 'function' && !wp.editor.__nowonlineWrapped) {
        var origInit = wp.editor.initialize;
        wp.editor.initialize = function(id, settings){
          settings = settings || {};
          settings.tinymce = ensure(settings.tinymce || {});
          return origInit.call(this, id, settings);
        };
        wp.editor.__nowonlineWrapped = true;
      }
    })();
    </script>
    <?php
}, 0);

/* -------------------------------------------------------------------------
 * Heading fix: undgå dobbelt <hN> i Elementor Heading-widget
 * ------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/fix-headings.js';
    $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
    wp_enqueue_script(
        'nowonline-elt-fix-headings',
        plugins_url('assets/fix-headings.js', NOWONLINE_ELT_FILE),
        [],
        $ver,
        true
    );
}, 20);

add_action('elementor/frontend/after_enqueue_scripts', function () {
    if (!wp_script_is('nowonline-elt-fix-headings', 'enqueued')) {
        $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/fix-headings.js';
        $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
        wp_enqueue_script(
            'nowonline-elt-fix-headings',
            plugins_url('assets/fix-headings.js', NOWONLINE_ELT_FILE),
            [],
            $ver,
            true
        );
    }
}, 20);
