<?php
// File: src/Admin/SettingsPage.php
namespace NowOnline\EltBlocks\Admin;

use NowOnline\EltBlocks\Repository\TemplatesRepo;
use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class SettingsPage
{
    public const PAGE_SLUG = 'nowonline-elementor-blocks';

    private TemplatesRepo $repo;
    private PlaceholderScanner $scanner;

    public function __construct()
    {
        $this->repo    = new TemplatesRepo();
        $this->scanner = new PlaceholderScanner();
    }

    public function register(): void
    {
        add_action('admin_menu',            [$this, 'add_menu']);
        add_filter('plugin_action_links_' . plugin_basename(NOWONLINE_ELT_FILE), [$this, 'settings_link']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('NowOnline – Elementor Blocks','nowonline'),
            __('Elementor Blocks','nowonline'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function settings_link(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings','nowonline') . '</a>');
        return $links;
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) return;
        if (function_exists('wp_enqueue_media')) wp_enqueue_media();
        wp_enqueue_script('jquery');
        // Hvis du senere vil have egne admin assets
        $ver = defined('NOWONLINE_ELT_VER') ? NOWONLINE_ELT_VER : '1';
        if (file_exists(plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/admin.css')){
            wp_enqueue_style('nowonline-elt-admin', plugins_url('assets/admin.css', NOWONLINE_ELT_FILE), [], $ver);
        }
        if (file_exists(plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/admin.js')){
            wp_enqueue_script('nowonline-elt-admin', plugins_url('assets/admin.js', NOWONLINE_ELT_FILE), ['jquery'], $ver, true);
        }
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) return;

        $updated = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('nowonline_elt_save','nowonline_elt_nonce')){
            $ids = (isset($_POST['allowed_ids']) && is_array($_POST['allowed_ids'])) ? array_map('intval', $_POST['allowed_ids']) : [];
            update_option(TemplatesRepo::OPT_ALLOW_LIST, $ids, true);
            $updated = true;
        }

        $all    = $this->repo->get_all_for_admin();
        $chosen = get_option(TemplatesRepo::OPT_ALLOW_LIST, []);
        if (!is_array($chosen)) $chosen = [];
        $visible_count = count($this->repo->get_templates_map());

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('NowOnline – Elementor Blocks','nowonline') . '</h1>';
        if ($updated) echo '<div class="updated notice"><p>' . esc_html__('Settings saved.','nowonline') . '</p></div>';

        // Diagnostics
        global $wp_version; $env_php = PHP_VERSION; $env_wp = isset($wp_version) ? $wp_version : 'unknown';
        $is_elementor = did_action('elementor/loaded') ? 'Yes' : 'No';
        $has_posttype = post_type_exists('elementor_library') ? 'Yes' : 'No';
        $has_thumbs   = post_type_supports('elementor_library','thumbnail') ? 'Yes' : 'No';
        $allow_ids    = is_array($chosen) ? array_map('intval',$chosen) : [];
        $allow_count  = count($allow_ids);
        $visible_now  = (int)$visible_count;
        echo '<details class="nowonline-diag" style="margin:10px 0 16px 0"><summary style="cursor:pointer;font-weight:600">🛠 Troubleshooting / Diagnostics</summary>';
        echo '<div style="margin:10px 0 6px 0"><ul style="margin-left:20px;list-style:disc">'
            .'<li><strong>PHP:</strong> '.esc_html($env_php).'</li>'
            .'<li><strong>WordPress:</strong> '.esc_html($env_wp).'</li>'
            .'<li><strong>Elementor loaded:</strong> '.esc_html($is_elementor).'</li>'
            .'<li><strong>elementor_library post type:</strong> '.esc_html($has_posttype).'</li>'
            .'<li><strong>Thumbnails enabled:</strong> '.esc_html($has_thumbs).'</li>'
            .'<li><strong>Allow-list IDs:</strong> '.esc_html($allow_count).' ('.esc_html(implode(', ', $allow_ids)).')</li>'
            .'<li><strong>Vises i Blocks nu:</strong> '.esc_html($visible_now).'</li>'
            .'</ul></div></details>';

        echo '<p>' . esc_html__('Sæt flueben ved de templates, der skal vises i Blocks-panelet. Klik på billedet for at sætte/ændre thumbnail.','nowonline') . '</p>';
        echo '<p><strong>' . esc_html__('Vises i Blocks nu:','nowonline') . '</strong> ' . intval($visible_count) . '</p>';

        echo '<form method="post">';
        wp_nonce_field('nowonline_elt_save','nowonline_elt_nonce');
        echo '<input type="hidden" id="nowonline_elt_media_nonce" value="' . esc_attr(wp_create_nonce(\NowOnline\EltBlocks\Admin\Ajax::NONCE_MEDIA)) . '" />';

        echo '<div style="margin:12px 0;display:flex;gap:8px;align-items:center">';
        echo '<input type="search" id="nowonline-elt-search" placeholder="' . esc_attr__('Søg…','nowonline') . '" style="min-width:260px" />';
        echo '<button class="button" type="button" id="nowonline-elt-select-all">' . esc_html__('Select all','nowonline') . '</button>';
        echo '<button class="button" type="button" id="nowonline-elt-deselect-all">' . esc_html__('Deselect all','nowonline') . '</button>';
        echo '<span style="opacity:.7">' . esc_html__('Antal templates: ','nowonline') . count($all) . '</span>';
        echo '</div>';

        echo '<div class="nowonline-elt-grid">';
        foreach ($all as $t){
            $id      = (int)$t['id'];
            $checked = in_array($id, array_map('intval',$chosen), true) ? ' checked' : '';
            $thumb   = !empty($t['thumb']);
            echo '<label class="nowonline-elt-card" data-title="' . esc_attr(strtolower($t['title'])) . '" data-id="' . $id . '">';
            echo '<input type="checkbox" name="allowed_ids[]" value="' . $id . '"' . $checked . ' />';
            if ($thumb){ echo '<img class="thumb" src="' . esc_url($t['thumb']) . '" alt="" />'; }
            else { echo '<div class="noimg thumb">' . esc_html__('No image','nowonline') . '</div>'; }
            echo '<div class="actions">'
                .'<button type="button" class="button button-small setimg">' . esc_html__('Set image','nowonline') . '</button>'
                .'<button type="button" class="button button-small removeimg">' . esc_html__('Remove','nowonline') . '</button>'
                .'</div>';
            echo '<span class="title">' . esc_html($t['title']) . ' <em>#' . $id . '</em></span>';
            echo '</label>';
        }
        echo '</div>';

        submit_button(__('Save changes','nowonline'));
        echo '</form>';

        // ---- Placeholder overview ------------------------------------------------------------
        $tpl_fields = [];
        foreach ($this->repo->get_all_for_admin() as $t){
            $defs = $this->scanner->scan((int)$t['id']); // key => type
            $tpl_fields[(int)$t['id']] = $defs;
        }

        echo '<h2 style="margin-top:24px">' . esc_html__('Placeholders fundet','nowonline') . '</h2>';
        echo '<p>'
            . esc_html__('Brug disse tokens i Elementor:','nowonline') . ' '
            . '<code>[[key]]</code>, '
            . '<code>[[textarea]]</code>, '
            . '<code>[[rich]]</code> <em>(' . esc_html__('wysiwyg alias','nowonline') . ')</em>, '
            . '<code>[[h1]]…[[h6]]</code>, '
            . '<code>[[p]]</code>, '
            . '<code>[[img]]</code>, '
            . '<code>[[bg]]</code>, '
            . '<code>[[url]]</code>.'
            . '</p>';

        echo '<details style="margin:8px 0" open><summary style="cursor:pointer;font-weight:600">' . esc_html__('Typer per template','nowonline') . '</summary>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;margin-top:10px">';
        foreach ($this->repo->get_all_for_admin() as $t){
            $defs = isset($tpl_fields[$t['id']]) ? $tpl_fields[$t['id']] : [];
            echo '<div style="border:1px solid #e2e4e7;background:#fff;border-radius:8px;padding:8px">';
            echo '<div style="font-weight:600">' . esc_html($t['title']) . ' <em style="opacity:.6">#' . (int)$t['id'] . '</em></div>';
            if (empty($defs)){
                echo '<div style="opacity:.65">' . esc_html__('(Ingen tokens fundet)','nowonline') . '</div>';
            } else {
                echo '<ul style="margin:6px 0 0 18px">';
                foreach ($defs as $k => $type){
                    echo '<li><code>' . esc_html($k) . '</code> <span style="opacity:.65">' . esc_html($type) . '</span></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '</div></details>';

        // Inline styles + js
        echo '<style>.nowonline-diag code,.nowonline-diag pre{font-family:monospace}'
            .'.nowonline-elt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:8px}'
            .'.nowonline-elt-card{border:1px solid #e2e4e7;border-radius:10px;overflow:hidden;display:grid;grid-template-rows:auto 1fr auto;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.04);position:relative}'
            .'.nowonline-elt-card input{position:absolute;transform:scale(1.2);margin:10px;z-index:2}'
            .'.nowonline-elt-card img.thumb,.nowonline-elt-card .thumb.noimg{width:100%;height:140px;object-fit:cover;background:#f6f7f7}'
            .'.nowonline-elt-card .noimg{display:flex;align-items:center;justify-content:center;color:#888}'
            .'.nowonline-elt-card .title{padding:10px;font-weight:600;border-top:1px solid #eee;display:block}'
            .'.nowonline-elt-card em{opacity:.6;font-style:normal;margin-left:6px}'
            .'.nowonline-elt-card .actions{position:absolute;right:8px;top:8px;display:flex;gap:6px;z-index:3}'
            .'</style>';

        echo '</div>';
    }
}
