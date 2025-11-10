<?php
// Fil: src/Services/DataHelper.php
namespace NowOnline\EltBlocks\Services;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer al datarensning og -validering (sanitize).
 * Indeholder funktioner flyttet fra Renderer.php for SRP.
 * Navngivet DataHelper for at undgå konflikt med Integrations/Sanitizers.php.
 */
final class DataHelper
{
    /**
     * Renser og retter URL-strenge.
     */
    public static function fix_url(string $u): string
    {
        $u = trim($u);
        if ($u === '') return $u;

        $u = str_ireplace(['http//','https//'], ['http://','https://'], $u);
        $u = preg_replace('#^(https?://)(https?://)+#i', '$1', $u);
        if (strpos($u, '//') === 0) $u = 'https:' . $u;
        if (stripos($u, 'www.') === 0) $u = 'https://' . $u;

        if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $u)) {
            if ($u[0] === '/') {
                $u = rtrim(home_url(), '/') . $u;
            } elseif (preg_match('#^[a-z0-9\.-]+\.[a-z]{2,}(/.*)?$#i', $u)) {
                $u = (is_ssl() ? 'https' : 'http') . '://' . $u;
            }
        }

        // undgå mixed content når site går på SSL
        if (is_ssl() && stripos($u, 'http://') === 0) {
            $homeHost = parse_url(home_url(), PHP_URL_HOST);
            $urlHost  = parse_url($u, PHP_URL_HOST);
            if (!$urlHost || !$homeHost || strcasecmp((string)$urlHost, (string)$homeHost) === 0) {
                $u = preg_replace('#^http://#i', 'https://', $u);
            }
        }

        return $u;
    }

    /**
     * Renser en farveværdi.
     */
    public static function sanitize_color(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        $ok = preg_match('/^(?:#[0-9a-fA-F]{3,8}|(?:rgb|hsl)a?\(\s*[^()]*\)|var\(\s*--[a-zA-Z0-9_-]+\s*\)|[a-zA-Z]+)$/', $v);
        return $ok ? $v : '';
    }

    /**
     * Renser en CSS-længdeværdi.
     */
    public static function sanitize_length(string $v, string $defaultUnit = 'px'): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^0+$/', $v)) return '0';
        if (preg_match('/^[0-9]*\.?[0-9]+$/', $v)) return $v . $defaultUnit;
        if (preg_match('/^[0-9]*\.?[0-9]+(px|rem|em|%|vh|vw|ch|ex)$/i', $v)) return $v;
        return '';
    }

    /**
     * Renser en spacing-værdi (padding/margin).
     */
    public static function sanitize_spacing(?string $v): string
    {
        $v = trim((string)$v);
        if ($v === '' || strcasecmp($v,'ingen')===0 || strcasecmp($v,'standard')===0) return '';
        return self::sanitize_length($v);
    }

    /**
     * Renser en background-position værdi.
     */
    public static function sanitize_bg_pos(string $v): string
    {
        $v = strtolower(trim($v));
        if ($v === '') return '';
        $allowed = ['center center','top center','bottom center','center left','center right','top left','top right','bottom left','bottom right','center','top','bottom','left','right'];
        if (in_array($v, $allowed, true)) return $v;
        if (preg_match('#^[0-9]+%(\s+[0-9]+%)$#', $v)) return $v;
        return '';
    }

    /**
     * Renser en background-size værdi.
     */
    public static function sanitize_bg_size(string $v): string
    {
        $v = strtolower(trim($v));
        if ($v === '') return '';
        if (in_array($v, ['cover','contain','auto'], true)) return $v;
        if (preg_match('#^[0-9]*\.?[0-9]+(px|%|rem|em|vh|vw)(\s+[0-9]*\.?[0-9]+(px|%|rem|em|vh|vw))?$#i', $v)) return $v;
        return '';
    }

    /**
     * Renser en background-repeat værdi.
     */
    public static function sanitize_bg_repeat(string $v): string
    {
        $v = strtolower(trim($v));
        if ($v === '') return '';
        $allowed = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
        if (in_array($v, $allowed, true)) {
            return $v;
        }
        return ''; // Returner 'no-repeat' som en sikker default, hvis du foretrækker
    }

    /**
     * Server-side sanitizer til rich HTML.
     */
    public static function sanitize_rich_html(string $html, bool $inlineOnly = false): string
    {
        $html = (string)$html;
        if ($html === '') return '';

        // Fjern class-attributter (bl.a. Elementor-klasser) før wp_kses
        $html = preg_replace('/\sclass=("|\').*?\1/i', '', $html);

        if ($inlineOnly) {
            $html = preg_replace('#</?(?:p|div|h[1-6]|section|article|header|footer|blockquote|ul|ol|li)[^>]*>#i', '', $html);
            $html = preg_replace('/\sstyle=("|\').*?\1/i', '', $html);
            $html = preg_replace('#</?span[^>]*>#i', '', $html);

            $allowed = [
                'a'      => ['href' => true, 'target' => true, 'rel' => true],
                'strong' => [], 'em' => [], 'b' => [], 'i' => [], 'u' => [],
                'br'     => [], 'code' => [], 'sup' => [], 'sub' => [],
            ];
            return wp_kses($html, $allowed);
        }

        return wp_kses_post($html);
    }
}