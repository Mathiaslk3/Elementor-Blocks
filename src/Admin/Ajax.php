<?php
// File: src/Admin/Ajax.php
namespace NowOnline\EltBlocks\Admin;

use NowOnline\EltBlocks\Repository\TemplatesRepo;

if (!defined('ABSPATH')) { exit; }

final class Ajax
{
    public const NONCE_MEDIA = 'nowonline_elt_media';

    public function register(): void
    {
        add_action('wp_ajax_nowonline_elt_set_thumb',    [$this, 'ajax_set_thumb']);
        add_action('wp_ajax_nowonline_elt_remove_thumb', [$this, 'ajax_remove_thumb']);
    }

    /* ----------------- helpers ----------------- */

    private function grab_nonce(): string
    {
        return isset($_REQUEST['_wpnonce'])
            ? (string) $_REQUEST['_wpnonce']
            : (isset($_REQUEST['nonce']) ? (string) $_REQUEST['nonce'] : '');
    }

    private function grab_int(array $keys, array $src): int
    {
        foreach ($keys as $k) {
            if (isset($src[$k])) {
                $v = (int) $src[$k];
                if ($v > 0) return $v;
            }
        }
        return 0;
    }

    private function maybe_debug(array $extra = []): array
    {
        if (current_user_can('manage_options')) {
            return array_merge($extra, [
                'seen_keys' => array_keys($_POST),
            ]);
        }
        return $extra;
    }

    /* ----------------- actions ----------------- */

    public function ajax_set_thumb(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['reason' => 'not_logged_in']);
        }

        $nonce = $this->grab_nonce();
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_MEDIA)) {
            wp_send_json_error(['reason' => 'bad_nonce']);
        }

        $post_id = $this->grab_int(['post_id','post','template_id','template','pid'], $_POST);
        $att_id  = $this->grab_int(['attachment_id','attachment','att_id','thumb_id','media_id','mid'], $_POST);

        if ($post_id <= 0 || $att_id <= 0) {
            wp_send_json_error($this->maybe_debug([
                'reason' => 'missing_params',
                'post_id' => $post_id,
                'attachment_id' => $att_id,
            ]));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['reason' => 'cannot_edit_post']);
        }
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['reason' => 'capability_denied']);
        }

        $pt = get_post_type($post_id);
        if ($pt !== 'elementor_library') {
            wp_send_json_error($this->maybe_debug([
                'reason' => 'invalid_post_type',
                'seen'   => $pt,
            ]));
        }

        $mime = (string) get_post_mime_type($att_id);
        if (strpos($mime, 'image/') !== 0) {
            wp_send_json_error(['reason' => 'not_image', 'mime' => $mime]);
        }

        // Sørg for at post-typen HAR thumbnail-support i denne request
        if (function_exists('post_type_supports') && !post_type_supports('elementor_library', 'thumbnail')) {
            add_post_type_support('elementor_library', 'thumbnail');
        }

        // Primært forsøg
        $ok = set_post_thumbnail($post_id, $att_id);

        // Fallback: direkte meta-opdatering hvis set_post_thumbnail fejler
        if (!$ok) {
            $ok = update_post_meta($post_id, '_thumbnail_id', $att_id);
            if (!$ok) {
                delete_post_meta($post_id, '_thumbnail_id');
                $ok = add_post_meta($post_id, '_thumbnail_id', $att_id, true);
            }
        }

        if (!$ok) {
            wp_send_json_error($this->maybe_debug([
                'reason' => 'set_thumbnail_failed',
            ]));
        }

        // **NYT**: Persistér override så editoren altid kan finde billedet
        $over = get_option(TemplatesRepo::OPT_THUMB_OVERRIDES, []);
        if (!is_array($over)) { $over = []; }
        $over[$post_id] = $att_id;
        update_option(TemplatesRepo::OPT_THUMB_OVERRIDES, $over, false);

        // Vælg pæneste URL til preview
        $url_medium_large = wp_get_attachment_image_url($att_id, 'medium_large');
        $url_large        = wp_get_attachment_image_url($att_id, 'large');
        $url_medium       = wp_get_attachment_image_url($att_id, 'medium');
        $url_thumb        = wp_get_attachment_image_url($att_id, 'thumbnail');
        $url_full         = wp_get_attachment_url($att_id);

        $url   = $url_medium_large ?: ($url_large ?: ($url_medium ?: ($url_thumb ?: $url_full)));
        $thumb = $url_thumb ?: ($url_medium ?: ($url_large ?: ($url_medium_large ?: $url_full)));

        wp_send_json_success([
            'url'       => $url,
            'url_thumb' => $thumb,
            'id'        => $att_id,
            'post_id'   => $post_id,
        ]);
    }

    public function ajax_remove_thumb(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['reason' => 'not_logged_in']);
        }

        $nonce = $this->grab_nonce();
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_MEDIA)) {
            wp_send_json_error(['reason' => 'bad_nonce']);
        }

        $post_id = $this->grab_int(['post_id','post','template_id','template','pid'], $_POST);
        if ($post_id <= 0) {
            wp_send_json_error($this->maybe_debug(['reason' => 'missing_params']));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['reason' => 'cannot_edit_post']);
        }
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['reason' => 'capability_denied']);
        }

        $pt = get_post_type($post_id);
        if ($pt !== 'elementor_library') {
            wp_send_json_error(['reason' => 'invalid_post_type', 'seen' => $pt]);
        }

        delete_post_thumbnail($post_id);

        // **NYT**: Fjern override
        $over = get_option(TemplatesRepo::OPT_THUMB_OVERRIDES, []);
        if (is_array($over) && isset($over[$post_id])) {
            unset($over[$post_id]);
            update_option(TemplatesRepo::OPT_THUMB_OVERRIDES, $over, false);
        }

        wp_send_json_success(['post_id' => $post_id]);
    }
}
