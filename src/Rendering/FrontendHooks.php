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

        
        // --- START PÅ DEN ENDELIGE RETTELSE ---
        
        // Denne CSS skal *KUN* gælde for widgets, der indeholder inline styling.
        $killBase = '.nowonline-elt-wrapper .nowonline-elt-module';

        // Vi kombinerer :has(span[style]) med widget-klasserne.
        // Dette betyder: "Find en overskrift-widget, som et sted indeni har et span-tag
        // med en style-attribut, og NULSTIL DEN."
        $killSelectors = [
            $killBase.' .elementor-widget-heading .elementor-heading-title:has(span[style])',
            $killBase.' .elementor-widget-text-editor:has(span[style])',
            $killBase.' .elementor-widget-text-editor p:has(span[style])'
        ];

        // Denne CSS-regel anvendes nu KUN på de felter, du aktivt har stylet.
        // Felter, du ikke har rørt (som "Titel"), vil ikke matche
        // denne selector og vil derfor beholde deres Elementor-styling.
        $killInlineCss = implode(',', $killSelectors) . '{
            font-size: inherit !important;
            line-height: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            color: inherit !important;
        }';
        
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