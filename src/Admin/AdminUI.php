<?php
namespace NowOnline\EltBlocks\Admin;

if (!defined('ABSPATH')) { exit; }

final class AdminUI
{
    public function register(): void
    {
        // Fælles admin/editor assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_common']); // editor
        add_action('admin_enqueue_scripts',       [$this, 'enqueue_common']); // admin (liste mv.)

        // Block category – WP 5.8+ bruger block_categories_all
        if (class_exists('\WP_Block_Editor_Context')) {
            add_filter('block_categories_all',  [$this, 'add_block_category_all'], 10, 2);
        } else {
            // Fallback til ældre WP uden deprecated notice
            add_filter('block_categories',      [$this, 'add_block_category_legacy'], 10, 2);
        }
    }

    // VIGTIGT: skal være public (ikke private/protected), og gerne acceptere evt. $hook param
    public function enqueue_common($hook = ''): void
    {
        // Indlæs dine admin assets (tilpas stier/handles efter dit projekt)
        wp_enqueue_style(
            'nowonline-elt-admin',
            plugins_url('../../assets/admin.css', __FILE__),
            [],
            defined('NOWONLINE_ELTBLOCKS_VER') ? NOWONLINE_ELTBLOCKS_VER : null
        );

        wp_enqueue_script(
            'nowonline-elt-admin',
            plugins_url('../../assets/admin.js', __FILE__),
            ['jquery'],
            defined('NOWONLINE_ELTBLOCKS_VER') ? NOWONLINE_ELTBLOCKS_VER : null,
            true
        );

        // Eksempel på data til JS (tilpas eller fjern)
        wp_localize_script('nowonline-elt-admin', 'NowElt', [
            'ajax' => admin_url('admin-ajax.php'),
        ]);
    }

    // WP 5.8+ signatur
    public function add_block_category_all(array $categories, $editor_context): array
    {
        return $this->ensure_category($categories);
    }

    // Ældre WP signatur
    public function add_block_category_legacy(array $categories, $post): array
    {
        return $this->ensure_category($categories);
    }

    private function ensure_category(array $categories): array
    {
        $slug = 'nowonline-elt';
        foreach ($categories as $c) {
            if (!empty($c['slug']) && $c['slug'] === $slug) {
                return $categories;
            }
        }
        array_unshift($categories, [
            'slug'  => $slug,
            'title' => __('NowOnline Blocks', 'nowonline'),
            'icon'  => null,
        ]);
        return $categories;
    }
}
