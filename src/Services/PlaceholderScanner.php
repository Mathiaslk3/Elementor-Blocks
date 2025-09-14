<?php
// File: src/Services/PlaceholderScanner.php
namespace NowOnline\EltBlocks\Services;

if ( ! defined('ABSPATH') ) { exit; }

final class PlaceholderScanner
{
    /** [[type:key]] tokens (type optional) */
    private const TOKEN_PATTERN = '/\[\[(?:(h[1-6]|p|text|textarea|rich|wysiwyg|img|bg|url):)?([a-zA-Z0-9_\-]+)\]\]/';

    /** data-now-key="key"  (tillad både ", ' og &quot;) */
    private const ATTR_KEY_PATTERN =
        '/data-now-key\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';

    /** class="... now-link-key ..." */
    private const CLASS_LINK_PATTERN = '/\bnow-link-([a-z0-9_\-]+)\b/i';

    /**
     * Scan Elementor’s _elementor_data og returnér key => type.
     * @return array<string,string>
     */
    public function scan(int $post_id): array
    {
        $out = [];

        $raw = get_post_meta($post_id, '_elementor_data', true);
        if ( ! $raw && function_exists('get_post') ) {
            $p = get_post($post_id);
            if ($p && isset($p->post_content)) {
                $this->scanNode((string)$p->post_content, $out);
            }
            return $out;
        }

        $json = is_array($raw) ? $raw : json_decode(is_string($raw) ? wp_unslash((string)$raw) : '', true);
        if ( ! is_array($json) ) { return $out; }

        $this->scanNode($json, $out);
        return $out;
    }

    /** Hjælpere */
    private static function normalizeType(?string $type, string $key): string
    {
        $type = strtolower((string)$type);
        $key  = strtolower($key);

        if ($type) return $type;

        // Nogle Danske nøgleord vi ved er rich
        if (in_array($key, ['titel','undertitel','beskrivelse'], true)) return 'rich';

        // Hvis navnet ligner et link-felt, så kald det url
        if (preg_match('#^(url|link|href)$#i', $key)) return 'url';

        return 'text';
    }

    private static function add(array &$out, string $key, string $type): void
    {
        $key  = strtolower($key);
        $type = strtolower($type);
        if ($key === '') return;

        if (!isset($out[$key])) {
            $out[$key] = $type;
            return;
        }

        // “Opgrader” text -> url/rich hvis vi opdager en mere specifik type
        if ($out[$key] === 'text' && in_array($type, ['url','rich','img','bg','gallery','textarea'], true)) {
            $out[$key] = $type;
        }
    }

    /** Rekursiv gennemgang af arrays/strings; udfylder $out by-ref */
    private function scanNode($node, array &$out): void
    {
        if (is_array($node)) {
            foreach ($node as $v) { $this->scanNode($v, $out); }
            return;
        }
        if (!is_string($node)) return;

        // 1) [[type:key]] tokens
        if (preg_match_all(self::TOKEN_PATTERN, $node, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $type = self::normalizeType($mm[1] ?? '', $mm[2]);
                self::add($out, $mm[2], $type);
            }
        }

        // 2) data-now-key="key"  => url
        if (preg_match_all(self::ATTR_KEY_PATTERN, $node, $am, PREG_SET_ORDER)) {
            foreach ($am as $mm) {
                self::add($out, $mm[1], 'url');
            }
        }

        // 3) class="... now-link-key ..." => url
        if (preg_match_all(self::CLASS_LINK_PATTERN, $node, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $mm) {
                self::add($out, $mm[1], 'url');
            }
        }
    }
}
