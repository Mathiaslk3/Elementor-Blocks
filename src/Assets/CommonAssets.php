<?php
// Fil: src/Assets/CommonAssets.php
namespace NowOnline\EltBlocks\Assets;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer fælles assets (fx fonts) for både frontend og admin.
 * Flyttet fra nowonline-elementor-blocks.php for SRP.
 */
final class CommonAssets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_fonts_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_fonts_frontend'], 5);
    }

    /**
     * Indlæs skrifttype i admin.
     */
    public function enqueue_fonts_admin(): void
    {
        $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
        if (!empty($url)) {
            wp_enqueue_style('nowonline-elt-font', $url, [], null);
        }
    }

    /**
     * Indlæs skrifttype på frontend.
     */
    public function enqueue_fonts_frontend(): void
    {
        $url = apply_filters('nowonline_elt_font_url', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap');
        if (!is_admin() && !empty($url)) {
            wp_enqueue_style('nowonline-elt-font', $url, [], null);
        }
    }
}