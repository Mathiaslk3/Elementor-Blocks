<?php
// Fil: src/Rendering/FrontendHooks.php
namespace NowOnline\EltBlocks\Rendering;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer alle frontend hooks (wp_head, wp_footer).
 * Flyttet fra Renderer.php for SRP.
 */
final class FrontendHooks
{
    /**
     * Holder styr på, om en header-blok er blevet renderet på denne side.
     */
    private static bool $hasHeaderBlock = false;

    public function register(): void
    {
        // Kør CSS sent så vi vinder over Elementor/tema (og undgår minify-race)
        add_action('wp_head',   [$this, 'frontend_css'], 999);
        add_action('wp_footer', [$this, 'inject_header_body_class']);
    }

    /**
     * Markér, at en header-blok er blevet renderet.
     * Denne kaldes af Renderer.php.
     */
    public static function mark_header_block_rendered(): void
    {
        self::$hasHeaderBlock = true;
    }

    /**
     * Injicerer den globale CSS i <head>.
     */
    public function frontend_css(): void
    {
        // VIGTIGT: Ingen frontend-CSS i admin (Gutenberg m.m.)
        if (is_admin()) return;

        $sel = apply_filters('nowonline_elt_header_hide_selectors', [
            'header[role="banner"]','.elementor-location-header','#masthead','.site-header',
            'header.site-header','header.header','.ast-desktop-header','.ast-mobile-header-wrap',
            '.oceanwp-header','.main-header','.header-main','.gen-header','#header'
        ]);
        $prefA = array_map(static fn($s) => 'body.nowelt-replace-header ' . $s, $sel);
        $prefB = array_map(static fn($s) => 'html.nowelt-replace-header ' . $s, $sel);
        $hideCss = implode(',', array_merge($prefA, $prefB)) . '{display:none!important}';

        $targets = '.nowonline-elt-wrapper [data-now-bg],.nowonline-elt-wrapper .now-bg,'
                 . '.nowonline-elt-wrapper [data-nowonline-bg],.nowonline-elt-wrapper .nowonline-bg';
        $overlayTargets = $targets . '>.elementor-background-overlay,' . $targets . ' .elementor-background-overlay';

        // Kun hvis wrapperen faktisk har mindst én --now-btn-* variabel sat
        $btnScope = '.nowonline-elt-wrapper[style*="--now-btn-"] .nowonline-elt-module';

        // Begræns til <a> (Elementor-knapper er typisk <a>) + dine now-link-varianter
        $btnSel = $btnScope . ' a[data-now-key],'
                . $btnScope . ' a[class*="now-link-"],'
                . $btnScope . ' a[id^="now-link-"],'
                . $btnScope . ' a.elementor-button,'
                . $btnScope . ' a.elementor-button-link';

        // === Desktop font-size mapping – AKTIVERES KUN pr. level via wrapper-klasser ===
        $mk = static function(string $level): string {
            $sel = '.nowonline-elt-wrapper.nowelt-fs-'.$level.' .nowonline-elt-module ';
            return $sel.$level.','.
                   $sel.'.elementor-widget-heading '.$level.'.elementor-heading-title' .
                   '{font-size:var(--now-fs-'.$level.')!important;}';
        };

        $fsCss =
              $mk('h1')
            . $mk('h2')
            . $mk('h3')
            . $mk('h4')
            . $mk('h5')
            . $mk('h6')
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module p,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor p'
            . '{font-size:var(--now-fs-body)!important;}'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module a.elementor-button,'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module .elementor-button'
            . '{font-size:var(--now-fs-btn)!important;}';

        // Neutraliser INLINE font-size/line-height i overskrifter KUN hvis wrapper har heading-fs:
        $killBase = '.nowonline-elt-wrapper.nowelt-fs-headings .nowonline-elt-module';
        $killInlineSel = implode(',', [
            $killBase.' .elementor-heading-title[style*="font-size"]',
            $killBase.' .elementor-heading-title [style*="font-size"]',
            $killBase.' h1[style*="font-size"]', $killBase.' h1 [style*="font-size"]',
            $killBase.' h2[style*="font-size"]', $killBase.' h2 [style*="font-size"]',
            $killBase.' h3[style*="font-size"]', $killBase.' h3 [style*="font-size"]',
            $killBase.' h4[style*="font-size"]', $killBase.' h4 [style*="font-size"]',
            $killBase.' h5[style*="font-size"]', $killBase.' h5 [style*="font-size"]',
            $killBase.' h6[style*="font-size"]', $killBase.' h6 [style*="font-size"]'
        ]);
        $killInlineCss = $killInlineSel.'{font-size:inherit!important;line-height:inherit!important;}';

        // Byg CSS
        $css  = '';
        $css .= '.nowonline-elt-gallery{display:flex;flex-wrap:wrap;gap:8px}';
        $css .= '.nowonline-elt-gallery img{max-width:100%;height:auto;display:block}';
        
        // --- RETTELSE: Disse to linjer er fjernet ---
        // $css .= $targets.'{background-color:var(--now-bg-color)!important;}';
        // $css .= $overlayTargets.'{background-color:var(--now-bg-color)!important;}';
        // --- SLUT PÅ RETTELSE ---

        // Knap-variabler – ingen tvungen border-style (template arver som default)
        $css .= $btnSel.'{'
              . 'color:var(--now-btn-color)!important;'
              . 'border-color:var(--now-btn-bdc)!important;'
              . 'border-width:var(--now-btn-bdw)!important;'
              . 'border-radius:var(--now-btn-rad)!important;'
              . '}';
        $css .= $btnSel.':hover,'.$btnSel.':focus{'
              . 'color:var(--now-btn-color)!important;'
              . 'border-color:var(--now-btn-bdc)!important;'
              . '}';

        // Var-mapping KUN på desktop (≥1025px) og kun for wrappers med de relevante klasser
        $css .= '@media (min-width:1025px){'.$fsCss.'}';

        // Nulstil inline font-size/line-height for headings (scopet til wrappers med heading-fs)
        $css .= $killInlineCss;

        $css .= '.nowonline-elt-wrapper .nowelt-has-bgvid{position:relative;overflow:hidden;}';
        $css .= '.nowonline-elt-wrapper .nowelt-bg-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;pointer-events:none;}';

        // Skjul header selectors
        $css .= $hideCss;

        echo '<style>'.$css.'</style>';
    }

    /**
     * Injicerer <body>-klassen, hvis en header-blok er blevet brugt.
     */
    public function inject_header_body_class(): void
    {
        // VIGTIGT: Ingen DOM-klasse-scripts i admin
        if (is_admin()) return;

        if (!self::$hasHeaderBlock) return;
        echo "<script>(function(){var d=document;d.documentElement.classList.add('nowelt-replace-header');if(d.body){d.body.classList.add('nowelt-replace-header');}})();</script>";
    }
}