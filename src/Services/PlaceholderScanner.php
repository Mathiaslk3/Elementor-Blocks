<?php
// File: src/Services/PlaceholderScanner.php
namespace NowOnline\EltBlocks\Services;

if (!defined('ABSPATH')) { exit; }

final class PlaceholderScanner
{
    /**
     * [[type:key]] – type er valgfri (case-insensitive)
     * Inkluderer også gallery/galleri.
     */
    private const TOKEN_PATTERN =
        '/\[\[(?:(h[1-6]|p|text|textarea|rich|wysiwyg|img|bg|url|gallery|galleri):)?([a-zA-Z0-9_\-]+)\]\]/i';

    /** data-now-key="key" (", ' eller &quot;) → URL */
    private const ATTR_KEY_PATTERN =
        '/\bdata-now-key\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';

    /** class="... now-link-key ..." → URL */
    private const CLASS_LINK_PATTERN = '/\bnow-link-([a-z0-9_\-]+)\b/i';

    /** data-now-img="key" / data-now-bg="key" → billede */
    private const ATTR_IMG_PATTERN =
        '/\bdata-now-(?:img|image|bg)\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';

    /** class="... now-img-key ..." / "now-bg-key" → billede */
    private const CLASS_IMG_PATTERN = '/\bnow-(?:img|image|bg)-([a-z0-9_\-]+)\b/i';

    /**
     * Scan Elementor’s data og returnér key => type.
     * @return array<string,string>
     */
    public function scan(int $post_id): array
    {
        $out = [];

        // 1) Scan _elementor_data JSON (primær kilde)
        $raw = get_post_meta($post_id, '_elementor_data', true);

        if ($raw) {
            $json = is_array($raw) ? $raw : json_decode(is_string($raw) ? wp_unslash((string)$raw) : '', true);
            if (is_array($json)) {
                $this->scanNode($json, $out);
            }
        } else if (function_exists('get_post')) {
            // fallback: scan post_content hvis ingen meta
            $p = get_post($post_id);
            if ($p && isset($p->post_content)) {
                $this->scanNode((string)$p->post_content, $out);
            }
        }

        // 2) Fallback/supplement: scan den renderede HTML (fanger global widgets, nested osv.)
        $this->scanRenderedHtml($post_id, $out);

        return $out;
    }

    /** Normaliser type + aliaser */
    private static function normalizeType(?string $type, string $key): string
    {
        $t = strtolower((string)$type);
        $k = strtolower($key);

        // aliaser
        if ($t === 'wysiwyg') $t = 'rich';
        if ($t === 'galleri') $t = 'gallery';
        if (in_array($t, ['h1','h2','h3','h4','h5','h6','p','text'], true)) $t = 'text';

        if ($t === '') {
            if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
            if (preg_match('#^(url|link|href)$#i', $k)) return 'url';
            return 'text';
        }
        return $t;
    }

    /** Tilføj/“opgrader” registreret type for en nøgle */
    private static function add(array &$out, string $key, string $type): void
    {
        $k = strtolower($key);
        $t = strtolower($type);
        if ($k === '') return;

        if (!isset($out[$k])) { $out[$k] = $t; return; }

        // Opgrader fra text til mere specifik
        if ($out[$k] === 'text' && in_array($t, ['url','rich','img','bg','gallery','textarea'], true)) {
            $out[$k] = $t;
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

        // 2) Links via data-now-key / now-link-KEY
        if (preg_match_all(self::ATTR_KEY_PATTERN, $node, $am, PREG_SET_ORDER)) {
            foreach ($am as $mm) self::add($out, $mm[1], 'url');
        }
        if (preg_match_all(self::CLASS_LINK_PATTERN, $node, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $mm) self::add($out, $mm[1], 'url');
        }

        // 3) Billeder via data-now-img/bg / now-img-KEY / now-bg-KEY
        if (preg_match_all(self::ATTR_IMG_PATTERN,  $node, $im, PREG_SET_ORDER)) {
            foreach ($im as $mm) self::add($out, $mm[1], 'img');
        }
        if (preg_match_all(self::CLASS_IMG_PATTERN, $node, $gm, PREG_SET_ORDER)) {
            foreach ($gm as $mm) self::add($out, $mm[1], 'img');
        }
    }

    /** Scan renderet Elementor HTML og supplér $out */
    private function scanRenderedHtml(int $post_id, array &$out): void
    {
        if (!did_action('elementor/loaded') || !class_exists('\\Elementor\\Plugin')) return;

        $inst = \Elementor\Plugin::$instance;
        if (!$inst || !isset($inst->frontend) || !method_exists($inst->frontend, 'get_builder_content_for_display')) return;

        $html = $inst->frontend->get_builder_content_for_display($post_id, true);
        if (is_string($html) && $html !== '') {
            $this->scanNode($html, $out);
        }
    }
}
