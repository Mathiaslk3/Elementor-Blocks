<?php
// File: src/Assets/Assets.php
namespace NowOnline\EltBlocks\Assets;

use NowOnline\EltBlocks\Repository\TemplatesRepo;
use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class Assets
{
    private TemplatesRepo $repo;
    private PlaceholderScanner $scanner;

    public function __construct(TemplatesRepo $repo, PlaceholderScanner $scanner)
    {
        $this->repo    = $repo;
        $this->scanner = $scanner;
    }

    public function register(string $ver): void
    {
        add_action('init', [$this->repo, 'ensure_thumbnails']);
        add_filter('block_categories_all', [$this, 'add_category'], 10, 2);
        add_filter('block_categories',     [$this, 'add_category'], 10, 2); // legacy
        add_action('enqueue_block_editor_assets', function() use ($ver){ $this->enqueue_editor($ver); });
        add_filter('allowed_block_types_all', [$this, 'allow_block'], 20, 2);
        add_filter('allowed_block_types',     [$this, 'allow_block'], 20, 2); // legacy
    }

    /**
     * Ensure our custom category exists in the inserter.
     * @param array $cats
     * @param mixed $post
     */
    public function add_category(array $cats, $post = null): array
    {
        $cats[] = ['slug' => 'nowonline-elementor', 'title' => __('Elementor','nowonline')];
        return $cats;
    }

    /**
     * Guarantee our block is allowed when Allowed Block Types is filtered.
     * @param mixed $allowed
     * @param mixed $context
     * @return mixed
     */
    public function allow_block($allowed, $context = null)
    {
        $name = 'nowonline/elt-template';
        if ($allowed === true)  return true;           // all allowed already
        if ($allowed === false || $allowed === null) return [$name];
        if (is_array($allowed)){
            if (!in_array($name, $allowed, true)) $allowed[] = $name;
            return $allowed;
        }
        return $allowed;
    }

    /**
     * Editor assets + data bridge for editor.js
     */
private function enqueue_editor(string $ver): void
{
    // Data for editor
    $map       = $this->repo->get_templates_map(); // [ [id,title,thumb], ... ]
    $ids       = array_map(static fn($t) => isset($t['id']) ? (int)$t['id'] : 0, $map);

    // ↓↓↓ ADD: filtrér efter allow-listen fra Settings
    $allowed = get_option( TemplatesRepo::OPT_ALLOW_LIST, [] );
    if ( is_array($allowed) && $allowed ) {
        $allow = array_map('intval', $allowed);
        $map   = array_values(array_filter($map, static function($t) use ($allow){
            return isset($t['id']) && in_array((int)$t['id'], $allow, true);
        }));
        $ids   = array_map(static fn($t) => (int)$t['id'], $map); // hold ids i sync
    }
    // ↑↑↑

    $field_map = $this->repo->get_templates_fieldmap($ids, $this->scanner);

    $map_json  = function_exists('wp_json_encode') ? wp_json_encode($map)       : json_encode($map);
    $fmap_json = function_exists('wp_json_encode') ? wp_json_encode($field_map) : json_encode($field_map);

    $deps = ['wp-blocks','wp-element','wp-i18n','wp-components','wp-block-editor','wp-editor','wp-dom-ready'];
    if (!wp_script_is('nowonline-elt-blocks-js','registered')){
        wp_register_script('nowonline-elt-blocks-js', plugins_url('assets/editor.js', NOWONLINE_ELT_FILE), $deps, $ver, true);
    }

    // --- ADD: preview AJAX bridge (url + nonce)
    $preview_data = [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(\NowOnline\EltBlocks\Admin\Ajax::NONCE_PREVIEW),
    ];

    wp_add_inline_script(
        'nowonline-elt-blocks-js',
        'window.NOWONLINE_TEMPLATES='.$map_json.';'.
        'window.NOWONLINE_FIELDS='.$fmap_json.';'.
        'window.NOWONLINE_ELT_AJAX='.wp_json_encode($preview_data).';',
        'before'
    );

    wp_enqueue_script('nowonline-elt-blocks-js');

    if (!wp_style_is('nowonline-elt-blocks-css','registered')){
        wp_register_style('nowonline-elt-blocks-css', plugins_url('assets/editor.css', NOWONLINE_ELT_FILE), [], $ver);
    }
    wp_enqueue_style('nowonline-elt-blocks-css');
}

}
