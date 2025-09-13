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
    // Classic editor (TinyMCE) assets skal være til stede
    if (function_exists('wp_enqueue_editor')) {
        wp_enqueue_editor();
    }

    // Data til editor.js … (uændret)
    $map       = $this->repo->get_templates_map();
    $ids       = array_map(static fn($t) => isset($t['id']) ? (int)$t['id'] : 0, $map);
    $field_map = $this->repo->get_templates_fieldmap($ids, $this->scanner);

    $map_json  = function_exists('wp_json_encode') ? wp_json_encode($map)       : json_encode($map);
    $fmap_json = function_exists('wp_json_encode') ? wp_json_encode($field_map) : json_encode($field_map);

    $deps = ['wp-blocks','wp-element','wp-i18n','wp-components','wp-block-editor','wp-editor','wp-dom-ready'];
    if (!wp_script_is('nowonline-elt-blocks-js','registered')){
        wp_register_script('nowonline-elt-blocks-js', plugins_url('assets/editor.js', NOWONLINE_ELT_FILE), $deps, $ver, true);
    }
    wp_add_inline_script('nowonline-elt-blocks-js', 'window.NOWONLINE_TEMPLATES='.$map_json.';window.NOWONLINE_FIELDS='.$fmap_json.';', 'before');
    wp_enqueue_script('nowonline-elt-blocks-js');

    if (!wp_style_is('nowonline-elt-blocks-css','registered')){
        wp_register_style('nowonline-elt-blocks-css', plugins_url('assets/editor.css', NOWONLINE_ELT_FILE), [], $ver);
    }
    wp_enqueue_style('nowonline-elt-blocks-css');
}

}
