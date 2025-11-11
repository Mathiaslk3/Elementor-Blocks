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
    private static bool $hasHeaderBlock = false;

    public function register(): void
    {
        // Kør CSS sent så vi vinder over Elementor/tema (og undgår minify-race)
        add_action('wp_head',   [$this, 'frontend_css'], 999);
        add_action('wp_footer', [$this, 'inject_header_body_class']);
    }

    public static function mark_header_block_rendered(): void
    {
        self::$hasHeaderBlock = true;
    }

    public function frontend_css(): void
    {
        if (is_admin()) return;

        $sel = apply_filters('nowonline_elt_header_hide_selectors', [
            'header[role="banner"]','.elementor-location-header','#masthead','.site-header',
            'header.site-header','header.header','.ast-desktop-header','.ast-mobile-header-wrap',
            '.oceanwp-header','.main-header','.header-main','.gen-header','#header'
        ]);
        $prefA = array_map(static fn($s) => 'body.nowelt-replace-header ' . $s, $sel);
        $prefB = array_map(static fn($s) => 'html.nowelt-replace-header ' . $s, $sel);
        $hideCss = implode(',', array_merge($prefA, $prefB)) . '{display:none!important}';

        $btnScope = '.nowonline-elt-wrapper[style*="--now-btn-"] .nowonline-elt-module';

        $btnSel = $btnScope . ' a[data-now-key],'
                . $btnScope . ' a[class*="now-link-"],'
                . $btnScope . ' a[id^="now-link-"],'
                . $btnScope . ' a.elementor-button,'
                . $btnScope . ' a.elementor-button-link';

        // === Desktop font-size mapping (fra Design-fane) ===
        $mk = static function(string $level): string {
            $sel = '.nowonline-elt-wrapper.nowelt-fs-'.$level.' .nowonline-elt-module ';
            return $sel.$level.','.
                   $sel.'.elementor-widget-heading '.$level.'.elementor-heading-title' .
                   '{font-size:var(--now-fs-'.$level.')!important;}';
        };

        $fsCss =
              $mk('h1') . $mk('h2') . $mk('h3') . $mk('h4') . $mk('h5') . $mk('h6')
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module p,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor,'
            . '.nowonline-elt-wrapper.nowelt-fs-body .nowonline-elt-module .elementor-widget-text-editor p'
            . '{font-size:var(--now-fs-body)!important;}'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module a.elementor-button,'
            . '.nowonline-elt-wrapper.nowelt-fs-btn .nowonline-elt-module .elementor-button'
            . '{font-size:var(--now-fs-btn)!important;}';

        
        // --- START PÅ DEN KIRURGISKE RETTELSE ---
        
        // Definer vores base-selectors
        $killBase = '.nowonline-elt-wrapper .nowonline-elt-module';
        $headingSelector = $killBase.' .elementor-widget-heading .elementor-heading-title';
        $textEditorSelector = $killBase.' .elementor-widget-text-editor';

        // Byg en "intelligent" nulstilling.
        // Nulstil KUN den specifikke egenskab, hvis et barn-span har den defineret.
        $killInlineCss = "
            /* Nulstil kun font-size, hvis et span[style*='font-size'] findes */
            $headingSelector:has(span[style*='font-size']),
            $textEditorSelector:has(span[style*='font-size']),
            $textEditorSelector p:has(span[style*='font-size']) {
                font-size: inherit !important;
            }

            /* Nulstil kun font-family, hvis et span[style*='font-family'] findes */
            $headingSelector:has(span[style*='font-family']),
            $textEditorSelector:has(span[style*='font-family']),
            $textEditorSelector p:has(span[style*='font-family']) {
                font-family: inherit !important;
            }

            /* Nulstil kun color, hvis et span[style*='color'] findes */
            $headingSelector:has(span[style*='color']),
            $textEditorSelector:has(span[style*='color']),
            $textEditorSelector p:has(span[style*='color']) {
                color: inherit !important;
            }

            /* Nulstil kun font-weight (fra bold/B-knappen) */
            $headingSelector:has(strong), $headingSelector:has(b),
            $textEditorSelector:has(strong), $textEditorSelector:has(b),
            $textEditorSelector p:has(strong), $textEditorSelector p:has(b) {
                font-weight: inherit !important;
            }

            /* Nulstil kun line-height */
            $headingSelector:has(span[style*='line-height']),
            $textEditorSelector:has(span[style*='line-height']),
            $textEditorSelector p:has(span[style*='line-height']) {
                line-height: inherit !important;
            }
        ";
        
        // --- SLUT PÅ RETTELSE ---


        // Byg CSS
        $css  = '';
        $css .= '.nowonline-elt-gallery{display:flex;flex-wrap:wrap;gap:8px}';
        $css .= '.nowonline-elt-gallery img{max-width:100%;height:auto;display:block}';

        // Knap-variabler
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

        // Desktop font-size overrides (hvis de er sat)
        $css .= '@media (min-width:1025px){'.$fsCss.'}';

        // Vores nye, "intelligente" nulstillings-CSS
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
        if (is_admin()) return;

        if (!self::$hasHeaderBlock) return;
        echo "<script>(function(){var d=document;d.documentElement.classList.add('nowelt-replace-header');if(d.body){d.body.classList.add('nowelt-replace-header');}})();</script>";
    }
}