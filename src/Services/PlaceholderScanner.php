<?php
// File: src/Services/PlaceholderScanner.php
namespace NowOnline\EltBlocks\Services;

if (!defined('ABSPATH')) { exit; }

final class PlaceholderScanner
{
    /**
     * [[type:key]] – type er valgfri (case-insensitive)
     * Inkluderer også gallery/galleri og video.
     */
    private const TOKEN_PATTERN =
        '/\[\[(?:(h[1-6]|p|text|textarea|rich|wysiwyg|img|image|bg|url|gallery|galleri|video):)?([a-zA-Z0-9_\-]+)\]\]/i';

    /** data-now-key="key" (", ' eller &quot;) → URL */
    private const ATTR_KEY_PATTERN =
        '/\bdata-now-key\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';
    /** data-now-key | key (pipe-syntax) */
    private const ATTR_KEY_PIPE =
        '/\bdata-now-key\s*\|\s*([a-zA-Z0-9_\-]+)/i';

    /** class="... now-link-key ..." → URL */
    private const CLASS_LINK_PATTERN = '/\bnow-link-([a-z0-9_\-]+)\b/i';

    /** data-now-img="key" / data-now-image="key" / data-now-bg="key" → img/bg (bevar typen) */
    private const ATTR_MEDIA_PATTERN =
        '/\bdata-now-(img|image|bg)\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';
    /** pipe-syntax for img/bg */
    private const ATTR_MEDIA_PIPE_IMG =
        '/\bdata-now-(img|image)\s*\|\s*([a-zA-Z0-9_\-]+)/i';
    private const ATTR_MEDIA_PIPE_BG =
        '/\bdata-now-bg\s*\|\s*([a-zA-Z0-9_\-]+)/i';

    /** class="... now-img-key ..." / "now-image-key" / "now-bg-key" → img/bg (bevar typen) */
    private const CLASS_MEDIA_PATTERN =
        '/\bnow-(img|image|bg)-([a-z0-9_\-]+)\b/i';

    /** GALLERI: attributes + klasser + pipe-syntax */
    private const ATTR_GAL_PATTERN =
        '/\bdata-now-(gallery|galleri)\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';
    private const ATTR_GAL_PIPE =
        '/\bdata-now-(gallery|galleri)\s*\|\s*([a-zA-Z0-9_\-]+)/i';
    private const CLASS_GAL_PATTERN =
        '/\bnow-(gallery|galleri)-([a-z0-9_\-]+)\b/i';

    /** VIDEO: attributes + klasser + pipe-syntax */
    private const ATTR_VIDEO_PATTERN =
        '/\bdata-now-video\s*=\s*(?:"|\'|&quot;)([a-zA-Z0-9_\-]+)(?:"|\'|&quot;)/i';
    private const ATTR_VIDEO_PIPE =
        '/\bdata-now-video\s*\|\s*([a-zA-Z0-9_\-]+)/i';
    private const CLASS_VIDEO_PATTERN =
        '/\bnow-video-([a-z0-9_\-]+)\b/i';

    /** Normalisér pipe-syntax i en streng så regex’erne rammer */
    private static function normalizePipesInString(string $s): string
    {
        $s = preg_replace(self::ATTR_MEDIA_PIPE_IMG, 'data-now-$1="$2"', $s);
        $s = preg_replace(self::ATTR_MEDIA_PIPE_BG,  'data-now-bg="$1"',  $s);
        $s = preg_replace(self::ATTR_GAL_PIPE,       'data-now-$1="$2"',  $s);
        $s = preg_replace(self::ATTR_KEY_PIPE,       'data-now-key="$1"', $s);
        $s = preg_replace(self::ATTR_VIDEO_PIPE,     'data-now-video="$1"', $s); // ← NY
        return $s;
    }

    /** Normaliser type + aliaser */
    private static function normalizeType(?string $type, string $key): string
    {
        $t = strtolower((string)$type);
        $k = strtolower($key);

        // aliaser
        if ($t === 'wysiwyg') $t = 'rich';
        if ($t === 'galleri') $t = 'gallery';
        if ($t === 'image')   $t = 'img';
        if (in_array($t, ['h1','h2','h3','h4','h5','h6','p','text'], true)) $t = 'text';

        // hvis type mangler, gæt ud fra nøgle
        if ($t === '') {
            if (in_array($k, ['titel','undertitel','beskrivelse'], true)) return 'rich';
            if ($k === 'billede')  return 'img';
            if ($k === 'galleri')  return 'gallery';
            if (strpos($k, 'video') !== false) return 'video'; // ← NY: gæt 'video' ud fra nøgle
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
        if ($out[$k] === 'text' && in_array($t, ['url','rich','img','bg','gallery','textarea','video'], true)) {
            $out[$k] = $t;
        }
    }

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
        } elseif (function_exists('get_post')) {
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

    /** Rekursiv gennemgang af arrays/strings; udfylder $out by-ref */
    private function scanNode($node, array &$out): void
    {
        if (is_array($node)) {
            foreach ($node as $v) { $this->scanNode($v, $out); }
            return;
        }
        if (!is_string($node)) return;

        // Gør pipe-syntax parat til scanning
        $node = self::normalizePipesInString($node);

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

        // 3) Media: img/bg via data-now-XXX og klasser
        if (preg_match_all(self::ATTR_MEDIA_PATTERN, $node, $im, PREG_SET_ORDER)) {
            foreach ($im as $mm) {
                $kind = strtolower($mm[1]); // img | image | bg
                $key  = $mm[2];
                self::add($out, $key, $kind === 'bg' ? 'bg' : 'img');
            }
        }
        if (preg_match_all(self::CLASS_MEDIA_PATTERN, $node, $gm, PREG_SET_ORDER)) {
            foreach ($gm as $mm) {
                $kind = strtolower($mm[1]); // img | image | bg
                $key  = $mm[2];
                self::add($out, $key, $kind === 'bg' ? 'bg' : 'img');
            }
        }

        // 4) Galleri via data-now-gallery/galleri og klasser
        if (preg_match_all(self::ATTR_GAL_PATTERN, $node, $gm1, PREG_SET_ORDER)) {
            foreach ($gm1 as $mm) self::add($out, $mm[2], 'gallery');
        }
        if (preg_match_all(self::CLASS_GAL_PATTERN, $node, $gm2, PREG_SET_ORDER)) {
            foreach ($gm2 as $mm) self::add($out, $mm[2], 'gallery');
        }

        // 5) Video via data-now-video og klasser
        if (preg_match_all(self::ATTR_VIDEO_PATTERN, $node, $vm1, PREG_SET_ORDER)) {
            foreach ($vm1 as $mm) self::add($out, $mm[1], 'video');
        }
        if (preg_match_all(self::CLASS_VIDEO_PATTERN, $node, $vm2, PREG_SET_ORDER)) {
            foreach ($vm2 as $mm) self::add($out, $mm[1], 'video');
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
