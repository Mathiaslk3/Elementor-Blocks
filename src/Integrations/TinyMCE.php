<?php
// Fil: src/Integrations/TinyMCE.php
namespace NowOnline\EltBlocks\Integrations;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer alle tilpasninger til TinyMCE (Classic Editor).
 * Flyttet fra nowonline-elementor-blocks.php for SRP.
 */
final class TinyMCE
{
    public function register(): void
    {
        add_filter('mce_buttons',           [$this, 'add_buttons_row1'], 100);
        add_filter('mce_buttons_2',         [$this, 'add_buttons_row2'], 100);
        add_filter('teeny_mce_buttons',     [$this, 'add_teeny_buttons'], 100);
        add_filter('tiny_mce_before_init',  [$this, 'configure_init'], 100);
        add_filter('wp_editor_settings',    [$this, 'configure_settings'], 100);
        add_filter('wp_kses_allowed_html',  [$this, 'allow_style_tags'], 100, 2);
        add_action('admin_head',            [$this, 'inline_patch_script'], 0);
    }

    public function add_buttons_row1(array $btns): array
    {
        foreach (['fontselect','fontsizeselect','forecolor'] as $b) {
            if (!in_array($b, $btns, true)) {
                array_unshift($btns, $b);
            }
        }
        return $btns;
    }

    public function add_buttons_row2(array $btns): array
    {
        foreach (['fontselect','fontsizeselect','forecolor'] as $b) {
            if (!in_array($b, $btns, true)) {
                array_unshift($btns, $b);
            }
        }
        return $btns;
    }

    public function add_teeny_buttons(array $btns): array
    {
        if (!in_array('fontsizeselect', $btns, true)) {
            array_unshift($btns, 'fontsizeselect');
        }
        return $btns;
    }

    public function configure_init(array $init): array
    {
        $init['fontsize_formats'] = $init['fontsize_formats'] ?? '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';
        $init['font_formats'] = $init['font_formats'] ?? (
            'Montserrat=Montserrat, Arial, sans-serif;'.
            'System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'.
            'Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;'.
            'Courier New=Courier New, Courier, monospace'
        );
        $t1 = $init['toolbar1'] ?? 'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo';
        foreach (['fontselect','fontsizeselect'] as $b) {
            if (strpos($t1, $b) === false) {
                $t1 = $b.' '.$t1;
            }
        }
        if (strpos($t1, 'forecolor') === false) {
            $t1 .= ',forecolor';
        }
        $init['toolbar1'] = $t1;
        return $init;
    }

    public function configure_settings(array $settings): array
    {
        $settings['teeny'] = false;
        $settings['tinymce'] = is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
        if (empty($settings['tinymce']['fontsize_formats'])) {
            $settings['tinymce']['fontsize_formats'] = '12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';
        }
        return $settings;
    }

    public function allow_style_tags(array $tags, string $ctx): array
    {
        if ($ctx !== 'post') {
            return $tags;
        }
        foreach (['span','p','div','h1','h2','h3','h4','h5','h6'] as $t) {
            $tags[$t]['style'] = true;
        }
        return $tags;
    }

    public function inline_patch_script(): void
    {
        ?>
<script>(function(){function e(n){n=n||{};var t=(n.toolbar1||'formatselect,bold,italic,link,unlink,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo');var p=t.split(/[, ]+/).filter(Boolean);['fontselect','fontsizeselect'].forEach(function(b){if(p.indexOf(b)===-1)p.unshift(b)});if(p.indexOf('forecolor')===-1)p.push('forecolor');n.toolbar1=p.join(',');n.fontsize_formats=n.fontsize_formats||'12px 14px 16px 18px 20px 24px 28px 32px 40px 48px 58px 64px 96px';n.font_formats=n.font_formats||'Montserrat=Montserrat, Arial, sans-serif;System=system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;Georgia=Georgia, serif;Times New Roman=Times New Roman, Times, serif;Courier New=Courier New, Courier, monospace';return n}if(window.tinyMCEPreInit&&tinyMCEPreInit.mceInit){Object.keys(tinyMCEPreInit.mceInit).forEach(function(k){tinyMCEPreInit.mceInit[k]=e(tinyMCEPreInit.mceInit[k])})}var r=function(){if(!window.tinymce||!tinymce.init||tinymce.__nowonlineWrapped)return!!window.tinymce;var o=tinymce.init;tinymce.init=function(i){i=e(i||{});return o.call(this,i)};tinymce.__nowonlineWrapped=true;return true};if(!r()){var iv=setInterval(function(){if(r())clearInterval(iv)},10);setTimeout(function(){clearInterval(iv);r()},5000)}if(window.wp&&wp.editor&&typeof wp.editor.initialize==='function'&&!wp.editor.__nowonlineWrapped){var oi=wp.editor.initialize;wp.editor.initialize=function(id,s){s=s||{};s.tinymce=e(s.tinymce||{});return oi.call(this,id,s)};wp.editor.__nowonlineWrapped=true}})();</script>
        <?php
    }
}