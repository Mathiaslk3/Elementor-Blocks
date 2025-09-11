<?php
namespace NowOnline\EltBlocks\Rendering;

if (!defined('ABSPATH')) { exit; }

final class LivePreview {
    public const QV = 'nowonline_elt_live_preview'; // query var + nonce action

    public function register(): void {
        // Frontend hook – kører på den normale theme-stack
        add_action('template_redirect', [$this, 'maybe_render']);
    }

    public function maybe_render(): void {
        if (!isset($_GET[self::QV])) return;

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string)$_GET['_wpnonce']) : '';
        $id    = (int)($_GET['template_id'] ?? 0);

        // Sikkerhed: kun loggede brugere med rettighed + gyldig nonce
        if (!is_user_logged_in() || !current_user_can('edit_posts') || !wp_verify_nonce($nonce, self::QV)) {
            status_header(403);
            wp_die('Unauthorized', 403);
        }
        if ($id <= 0) {
            status_header(400);
            wp_die('Missing template_id', 400);
        }

        // Sørg for at Elementor assets er klar
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->frontend->enqueue_styles();
            \Elementor\Plugin::$instance->frontend->enqueue_scripts();
        }

        // Render Elementor-template ind i et minimalt HTML-dokument
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $html = class_exists('\Elementor\Plugin')
            ? \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id, true)
            : '';

        echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        // Print styles/scripts som tema og plugins har queued
        wp_head();
        echo '<style>html,body{margin:0;padding:0}</style>';
        echo '</head><body>';
        echo $html; // Elementor output
        wp_footer();
        echo '</body></html>';
        exit;
    }
}
