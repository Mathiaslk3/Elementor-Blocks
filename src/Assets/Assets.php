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

        add_action('enqueue_block_editor_assets', function() use ($ver) {
            $this->enqueue_editor($ver);
        });

        // Kør SENT, så vi overstyrer andre plugins/temaer.
        add_filter('allowed_block_types_all', [$this, 'allow_block'], 9999, 2);
        add_filter('allowed_block_types',     [$this, 'allow_block'], 9999, 2); // legacy

        // Ekstra sikkerhedsnet på editor-indstillingerne.
        add_filter('block_editor_settings_all', [$this, 'force_editor_settings'], 9999, 2);
    }

    /**
     * Editor-kategori.
     */
    public function add_category(array $cats, $post = null): array
    {
        $cats[] = ['slug' => 'nowonline-elementor', 'title' => __('Elementor','nowonline')];
        return $cats;
    }

    /**
     * Sikrer at vores blok OG en simpel fallback altid er tilladt.
     * VIGTIGT: uden Paragraph kan sidste blok ikke slettes.
     */
    public function allow_block($allowed, $context = null)
    {
        $ensure = function(array $list): array {
            $must = ['nowonline/elt-template', 'core/paragraph', 'core/freeform'];
            foreach ($must as $blk) {
                if (!in_array($blk, $list, true)) {
                    $list[] = $blk;
                }
            }
            return $list;
        };

        // Ubegrænset → behold ubegrænset.
        if ($allowed === true) {
            return true;
        }

        // Ingen liste eller alt slået fra → lav minimal whitelist.
        if ($allowed === false || $allowed === null) {
            return $ensure([]);
        }

        // Merge, hvis der er en liste.
        if (is_array($allowed)) {
            return $ensure($allowed);
        }

        return $allowed;
    }

    /**
     * Ekstra net: sørg for at editorens egne settings også har fallback-blokke.
     * (Nogle setups ignorerer PHP-listen og bruger kun settings-objektet)
     */
    public function force_editor_settings(array $settings, $context): array
    {
        $normalize = function($val) use ($context) {
            // Genbrug logikken fra allow_block
            if ($val === true) {
                return true;
            }
            if ($val === false || $val === null) {
                return ['nowonline/elt-template', 'core/paragraph', 'core/freeform'];
            }
            if (is_array($val)) {
                foreach (['nowonline/elt-template', 'core/paragraph', 'core/freeform'] as $blk) {
                    if (!in_array($blk, $val, true)) {
                        $val[] = $blk;
                    }
                }
                return $val;
            }
            return $val;
        };

        // Tving fallback i allowedBlockTypes
        if (isset($settings['allowedBlockTypes'])) {
            $settings['allowedBlockTypes'] = $normalize($settings['allowedBlockTypes']);
        }

        // Sørg for standard-blok er Paragraph (ellers deadlock ved sletning)
        if (empty($settings['defaultBlock'])) {
            $settings['defaultBlock'] = 'core/paragraph';
        }

        return $settings;
    }

    /**
     * Editor assets + data bridge for editor.js
     */
    private function enqueue_editor(string $ver): void
    {
        // Bevidst: Classic editor lib, da editor.js bruger rich controls
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        $map       = $this->repo->get_templates_map();
        $ids       = array_map(static fn($t) => isset($t['id']) ? (int)$t['id'] : 0, $map);
        $field_map = $this->repo->get_templates_fieldmap($ids, $this->scanner);

        $map_json  = function_exists('wp_json_encode') ? wp_json_encode($map)       : json_encode($map);
        $fmap_json = function_exists('wp_json_encode') ? wp_json_encode($field_map) : json_encode($field_map);

        $deps = ['wp-blocks','wp-element','wp-i18n','wp-components','wp-block-editor','wp-editor','wp-dom-ready'];

        if (!wp_script_is('nowonline-elt-blocks-js','registered')){
            wp_register_script(
                'nowonline-elt-blocks-js',
                plugins_url('assets/editor.js', NOWONLINE_ELT_FILE),
                $deps,
                $ver,
                true
            );
        }

        wp_add_inline_script(
            'nowonline-elt-blocks-js',
            'window.NOWONLINE_TEMPLATES='.$map_json.';window.NOWONLINE_FIELDS='.$fmap_json.';',
            'before'
        );
        wp_enqueue_script('nowonline-elt-blocks-js');

        if (!wp_style_is('nowonline-elt-blocks-css','registered')){
            wp_register_style(
                'nowonline-elt-blocks-css',
                plugins_url('assets/editor.css', NOWONLINE_ELT_FILE),
                [],
                $ver
            );
        }
        wp_enqueue_style('nowonline-elt-blocks-css');
    }
}
