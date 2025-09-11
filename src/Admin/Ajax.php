<?php
namespace NowOnline\EltBlocks\Admin;

if (!defined('ABSPATH')) { exit; }

final class Ajax
{
    public const NONCE_MEDIA   = 'nowonline_elt_media';
    public const NONCE_PREVIEW = 'nowonline_elt_preview'; // ← ny nonce til preview

    public function register(): void
    {
        add_action('wp_ajax_nowonline_elt_set_thumb',    [$this, 'ajax_set_thumb']);
        add_action('wp_ajax_nowonline_elt_remove_thumb', [$this, 'ajax_remove_thumb']);

        // ← ny AJAX route til live preview i editoren
        add_action('wp_ajax_nowonline_elt_preview',      [$this, 'ajax_preview']);
    }

    /**
     * Sæt thumbnail på et Elementor template (admin UI).
     */
    public function ajax_set_thumb(): void
    {
        // Cap & nonce
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['reason' => 'capability_denied']);
        }
        check_ajax_referer(self::NONCE_MEDIA, '_wpnonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $att_id  = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($post_id <= 0 || $att_id <= 0) {
            wp_send_json_error(['reason' => 'missing_params']);
        }
        // Must be able to edit the post whose thumbnail we change
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['reason' => 'cannot_edit_post']);
        }
        // Only allow on Elementor Library items (defensive)
        $pt = get_post_type($post_id);
        if ($pt !== 'elementor_library') {
            wp_send_json_error(['reason' => 'invalid_post_type']);
        }
        // Attachment must be an image
        $mime = (string) get_post_mime_type($att_id);
        if (strpos($mime, 'image/') !== 0) {
            wp_send_json_error(['reason' => 'not_image']);
        }

        $ok = set_post_thumbnail($post_id, $att_id);
        if (!$ok) {
            wp_send_json_error(['reason' => 'set_thumbnail_failed']);
        }

        $url_thumb = wp_get_attachment_image_url($att_id, 'thumbnail');
        $url_med   = wp_get_attachment_image_url($att_id, 'medium');
        $url_full  = wp_get_attachment_url($att_id);
        $url       = $url_med ?: ($url_thumb ?: $url_full);
        $thumb     = $url_thumb ?: ($url_med ?: $url_full);

        wp_send_json_success([
            'url'       => $url,
            'url_thumb' => $thumb,
            'id'        => $att_id,
            'post_id'   => $post_id,
        ]);
    }

    /**
     * Fjern thumbnail på et Elementor template (admin UI).
     */
    public function ajax_remove_thumb(): void
    {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['reason' => 'capability_denied']);
        }
        check_ajax_referer(self::NONCE_MEDIA, '_wpnonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['reason' => 'missing_params']);
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['reason' => 'cannot_edit_post']);
        }
        $pt = get_post_type($post_id);
        if ($pt !== 'elementor_library') {
            wp_send_json_error(['reason' => 'invalid_post_type']);
        }

        delete_post_thumbnail($post_id);
        wp_send_json_success(['post_id' => $post_id]);
    }

    /**
     * LIVE-preview til editoren: returnerer kun Elementor-HTML (ingen tema/admin bar).
     * GET-parametre:
     *  - post_id  (int)  : ID på elementor_library post
     *  - selector (str)  : CSS selector (#id eller .class) for at udtrække en enkelt container (valgfri)
     *  - _wpnonce       : NONCE_PREVIEW
     */
    public function ajax_preview(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die();
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_PREVIEW)) {
            wp_die();
        }

        $post_id  = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $selector = isset($_GET['selector']) ? sanitize_text_field((string) $_GET['selector']) : '';

        // Kun elementor_library tillades
        if ($post_id <= 0 || get_post_type($post_id) !== 'elementor_library') {
            $this->render_blank();
        }

        if (!class_exists('\Elementor\Plugin')) {
            $this->render_blank();
        }

        // Slå admin bar fra i denne respons
        add_filter('show_admin_bar', '__return_false', 1000);

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        // Render Elementor-indhold
        $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($post_id);

        // Udtræk valgfrit fragment (#id eller .class)
        if ($selector && $html) {
            $frag = $this->extract_fragment($html, $selector);
            if ($frag) {
                $html = $frag;
            }
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex">' .
             '<style>html,body{margin:0;padding:0;background:transparent}' .
             'img,video{max-width:100%;height:auto}' .
             '*,*:before,*:after{box-sizing:border-box}</style>' .
             '</head><body>' . $html . '</body></html>';
        wp_die();
    }

    /** Render en tom, gennemsigtig side til iframe fallback. */
    private function render_blank(): void
    {
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo '<!doctype html><meta charset="utf-8"><body style="margin:0;background:transparent"></body>';
        wp_die();
    }

    /**
     * Simpel DOM-extractor for #id eller .class (første match).
     * Returnerer null hvis ikke fundet eller DOM ikke er tilgængelig.
     */
    private function extract_fragment(string $html, string $selector): ?string
    {
        if (!class_exists('\DOMDocument')) {
            return null;
        }
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // forcer UTF-8
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);

        if ($selector[0] === '#') {
            $id   = substr($selector, 1);
            $node = $dom->getElementById($id);
            return $node ? $dom->saveHTML($node) : null;
        }

        if ($selector[0] === '.') {
            $class    = substr($selector, 1);
            $nodeList = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]");
            if ($nodeList && $nodeList->length) {
                return $dom->saveHTML($nodeList->item(0));
            }
        }
        return null;
    }
}
