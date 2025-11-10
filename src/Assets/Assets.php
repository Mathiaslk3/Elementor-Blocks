<?php
// Fil: src/Assets/Assets.php
namespace NowOnline\EltBlocks\Assets;

use NowOnline\EltBlocks\Repository\TemplatesRepo;
use NowOnline\EltBlocks\Services\PlaceholderScanner;

if (!defined('ABSPATH')) { exit; }

final class Assets
{
    private TemplatesRepo $repo;
    private PlaceholderScanner $scanner;
    private string $plugin_path;
    private string $plugin_url;

    public function __construct(TemplatesRepo $repo, PlaceholderScanner $scanner)
    {
        $this->repo    = $repo;
        $this->scanner = $scanner;
        $this->plugin_path = plugin_dir_path(NOWONLINE_ELT_FILE);
        $this->plugin_url  = plugin_dir_url(NOWONLINE_ELT_FILE);
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
        // Sikrer at kategorien kun tilføjes én gang
        $found = false;
        foreach ($cats as $cat) {
            if ($cat['slug'] === 'nowonline-elementor') {
                $found = true;
                break;
            }
        }
        if (!$found) {
            // Sørg for at bruge den kategori, din AdminUI.php OGSÅ registrerer, hvis den findes
            $cats[] = ['slug' => 'nowonline-elementor', 'title' => __('Elementor','nowonline')];
        }
        return $cats;
    }

    /**
     * Sikrer at vores blok OG en simpel fallback altid er tilladt.
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

        if ($allowed === true) return true;
        if ($allowed === false || $allowed === null) return $ensure([]);
        if (is_array($allowed)) return $ensure($allowed);
        return $allowed;
    }

    /**
     * Ekstra net: sørg for at editorens egne settings også har fallback-blokke.
     */
    public function force_editor_settings(array $settings, $context): array
    {
        // (Denne funktion er uændret)
        $normalize = function($val) use ($context) {
            if ($val === true) return true;
            if ($val === false || $val === null) {
                return ['nowonline/elt-template', 'core/paragraph', 'core/freeform'];
            }
            if (is_array($val)) {
                foreach (['nowonline/elt-template', 'core/paragraph', 'core/freeform'] as $blk) {
                    if (!in_array($blk, $val, true)) $val[] = $blk;
                }
                return $val;
            }
            return $val;
        };
        if (isset($settings['allowedBlockTypes'])) {
            $settings['allowedBlockTypes'] = $normalize($settings['allowedBlockTypes']);
        }
        if (empty($settings['defaultBlock'])) {
            $settings['defaultBlock'] = 'core/paragraph';
        }
        return $settings;
    }

    /**
     * Editor assets + data bridge (OPDATERET til @wordpress/scripts)
     */
    private function enqueue_editor(string $ver): void
    {
        // --- START OPDATERING ---
        $script_path = $this->plugin_path . 'build/index.js';
        $asset_path  = $this->plugin_path . 'build/index.asset.php';
        $script_url  = $this->plugin_url . 'build/index.js';

        if (!file_exists($script_path) || !file_exists($asset_path)) {
            // Vis en fejl i admin, hvis build-filerne mangler
            if (current_user_can('manage_options')) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>NowOnline Elementor Blocks:</strong> Build-filer mangler. Kør venligst <code>npm run build</code> i plugin-mappen.';
                    echo '</p></div>';
                });
            }
            return;
        }

        // Indlæs den "magiske" asset-fil
        $asset_file = require($asset_path);
        $deps = $asset_file['dependencies'] ?? [];
        $script_ver = $asset_file['version'] ?? $ver;

        // Registrer det nye script
        wp_register_script(
            'nowonline-elt-blocks-js', // Samme "handle" som før
            $script_url,
            $deps,
            $script_ver,
            true // Load in footer
        );
        // --- SLUT OPDATERING ---

        // Data bridge er uændret
        $map       = $this->repo->get_templates_map();
        $ids       = array_map(static fn($t) => isset($t['id']) ? (int)$t['id'] : 0, $map);
        $field_map = $this->repo->get_templates_fieldmap($ids, $this->scanner);

        $map_json  = function_exists('wp_json_encode') ? wp_json_encode($map)       : json_encode($map);
        $fmap_json = function_exists('wp_json_encode') ? wp_json_encode($field_map) : json_encode($field_map);

        wp_add_inline_script(
            'nowonline-elt-blocks-js',
            'window.NOWONLINE_TEMPLATES='.$map_json.';window.NOWONLINE_FIELDS='.$fmap_json.';',
            'before'
        );
        wp_enqueue_script('nowonline-elt-blocks-js');

        // Indlæs den gamle editor.css - den virker stadig
        $css_path = $this->plugin_path . 'assets/editor.css';
        if (file_exists($css_path)) {
            wp_register_style(
                'nowonline-elt-blocks-css', // Samme "handle" som før
                $this->plugin_url . 'assets/editor.css',
                [],
                filemtime($css_path)
            );
            wp_enqueue_style('nowonline-elt-blocks-css');
        }
    }
}