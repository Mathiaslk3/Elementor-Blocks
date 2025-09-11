<?php
// File: src/Admin/Ajax.php
namespace NowOnline\EltBlocks\Admin;

if (!defined('ABSPATH')) { exit; }

final class Ajax
{
    public const NONCE_MEDIA = 'nowonline_elt_media';

    public function register(): void
    {
        add_action('wp_ajax_nowonline_elt_set_thumb',    [$this, 'ajax_set_thumb']);
        add_action('wp_ajax_nowonline_elt_remove_thumb', [$this, 'ajax_remove_thumb']);
    }

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
}
