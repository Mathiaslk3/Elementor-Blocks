<?php
// File: src/Services/PlaceholderScanner.php
namespace NowOnline\EltBlocks\Services;

if (!defined('ABSPATH')) { exit; }

final class PlaceholderScanner
{
    /** Single source of truth for token format */
    private const TOKEN_PATTERN = '/\[\[(?:(h[1-6]|p|text|textarea|rich|wysiwyg|img|bg|url):)?([a-zA-Z0-9_\-]+)\]\]/';

    /**
     * Scan Elementor's _elementor_data JSON and collect placeholders.
     * @return array<string,string> key => type
     */
    public function scan(int $post_id): array
    {
        $out = [];
        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (!$raw && function_exists('get_post')) {
            // Some installs store tokens directly in content; scan as last resort.
            $post = get_post($post_id);
            if ($post && isset($post->post_content)) {
                $this->scanNode((string) $post->post_content, $out);
            }
            return $out;
        }

        // Elementor stores as JSON string; occasionally slashed. Decode robustly.
        $json = is_array($raw) ? $raw : json_decode(is_string($raw) ? wp_unslash((string)$raw) : '', true);
        if (!is_array($json)) { return $out; }

        $this->scanNode($json, $out);
        return $out;
    }

    /** Recursive walk of arrays/strings; fills $out (by ref). */
    private function scanNode($node, array &$out): void
    {
        if (is_array($node)){
            foreach ($node as $v){ $this->scanNode($v, $out); }
            return;
        }
        if (!is_string($node)) { return; }

        if (preg_match_all(self::TOKEN_PATTERN, $node, $m, PREG_SET_ORDER)){
            foreach ($m as $mm){
                $type = !empty($mm[1]) ? strtolower($mm[1]) : 'text';
                $key  = strtolower($mm[2]);
                if (!isset($out[$key])) { $out[$key] = $type; }
            }
        }
    }
}
