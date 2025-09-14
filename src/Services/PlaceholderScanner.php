<?php
// File: src/Services/PlaceholderScanner.php
namespace NowOnline\EltBlocks\Services;

if (!defined('ABSPATH')) { exit; }

final class PlaceholderScanner
{
    /**
     * [[type:key]]  – type er valgfri.
     * Inkluderer også gallery/galleri og er case-insensitive.
     */
    private const TOKEN_PATTERN =
        '/\[\[(?:(h[1-6]|p|text|textarea|rich|wysiwyg|img|bg|url|gallery|galleri):)?([a-zA-Z0-9_\-]+)\]\]/i';

    /** data-now-key="key"  (tillad ", ' og &quot;) */
    private const ATTR_KEY_PATTERN =
        '/\bdata-now-key\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';

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

        // fallback: scan post_content hvis data ikke findes
        if (!$raw && function_exists('get_post')) {
            $p = get_post($post_id);
            if ($p && isset($p->post_content)) {
                $this->scanNode((string) $p->post_content, $out);
            }
            return $out;
        }

        // _elementor_data er oftest en JSON-string; håndter også array
        $json = is_array($raw) ? $raw : json_decode(is_string($raw) ? wp_unslash((string) $raw) : '', true);
        if (!is_array($json)) { return $out; }

        $this->scanNode($json, $out);
        return $out;
    }

    /** Normaliser type + aliaser */
    private static function normalizeType(?string $type, string $key): string
    {
        $t = strtolower((string) $type);
        $k = strtolower($key);

        // map aliaser
        if ($t === 'wysiwyg') $t = 'rich';
        if ($t === 'galleri') $t = 'gallery';
        if ($t === 'h1' || $t === 'h2' || $t === 'h3' || $t === 'h4' || $t === 'h5' || $t === 'h6' || $t === 'p' || $t === 'text') {
            $t = 'text';
        }

        // ingen type? – gætværdi
        if ($t === '') {
            // kendte danske nøgler som skal være rich
            if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
            // link-agtige nøgler
            if (preg_match('#^(url|link|href)$#i', $k)) return 'url';
            return 'text';
        }

        return $t;
    }

    /** Tilføj/”opgrader” registreret type for en nøgle */
    private static function add(array &$out, string $key, string $type): void
    {
        $k = strtolower($key);
        $t = strtolower($type);
        if ($k === '') return;

        if (!isset($out[$k])) { $out[$k] = $t; return; }

        // Opgrader fra text til en mere specifik type
        if ($out[$k] === 'text' && in_array($t, ['url','rich','img','bg','gallery','textarea'], true)) {
            $out[$k] = $t;
        }
    }

    /** Rekursiv gennemgang; udfylder $out by-ref */
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
