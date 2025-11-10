<?php
// Fil: src/Integrations/DesignControls.php
namespace NowOnline\EltBlocks\Integrations;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer logikken for "Design: container background".
 * Både indlæsning af JS-kontroller i editor og server-side rendering af farven.
 * Flyttet fra nowonline-elementor-blocks.php for SRP.
 */
final class DesignControls
{
    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_controls']);
        
        // --- RETTELSE: DEAKTIVERET ---
        // Denne linje anvendte farven på den ydre wrapper, hvilket var forkert.
        // Den nye Renderer.php håndterer nu farven korrekt på den indre container.
        // add_filter('render_block', [$this, 'apply_container_background'], 10, 2);
    }

    /**
     * Indlæs JS for design-kontrollerne i Gutenberg.
     */
    public function enqueue_editor_controls(): void
    {
        $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/design-controls.js';
        if (!file_exists($path)) {
            return; // Gør intet, hvis filen ikke findes
        }

        $ver  = (string) filemtime($path);
        wp_enqueue_script(
            'nowonline-elt-design-controls',
            plugins_url('assets/design-controls.js', NOWONLINE_ELT_FILE),
            ['wp-element','wp-blocks','wp-components','wp-hooks','wp-i18n','wp-edit-post','wp-block-editor'],
            $ver,
            true
        );

        // Sikrer at vi bruger det korrekte bloknavn
        $slug = apply_filters('nowonline_elt_block_slug', 'nowonline/elt-template');
        $inl  = 'window.nowonlineEltBlockSlug = ' . wp_json_encode($slug) . ';';
        wp_add_inline_script('nowonline-elt-design-controls', $inl, 'before');
    }

    /**
     * Anvend containerBg-attributten på den renderede bloks wrapper.
     * (Denne funktion er nu deaktiveret via register()-metoden)
     */
    public function apply_container_background(string $html, array $block): string
    {
        try {
            $slug = apply_filters('nowonline_elt_block_slug', 'nowonline/elt-template');
            
            $blockName = $block['blockName'] ?? null;
            if ($blockName !== $slug) {
                return $html;
            }
            
            $bg = $block['attrs']['containerBg'] ?? '';
            if (!is_string($bg) || $bg === '') {
                return $html;
            }
            
            $bg = trim($bg);
            $allow = (
                preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $bg) ||
                preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $bg) ||
                preg_match('/^(rgb|rgba|hsl|hsla)\([^\)]+\)$/', $bg)
            );
            
            if (!$allow) {
                return $html;
            }

            if (preg_match('/^<([a-z0-9:-]+)\b[^>]*>/i', $html, $m)) {
                $open = $m[0];
                if (preg_match('/\sstyle=\"([^\"]*)\"/i', $open, $sm)) {
                    $style = trim($sm[1]);
                    $style = preg_replace('/background(?:-color)?\s*:\s*[^;]*;?/i', '', $style);
                    $style = trim($style);
                    $style = ($style ? $style.'; ' : '') . 'background-color: '.$bg.';';
                    $open2 = preg_replace('/style=\"[^\"]*\"/i', 'style="'.esc_attr($style).'"', $open, 1);
                } else {
                    $open2 = rtrim(substr($open, 0, -1)) . ' style="'.esc_attr('background-color: '.$bg.';').'">';
                }
                $html = $open2 . substr($html, strlen($open));
            }
            return $html;
        } catch (\Throwable $e) {
            return $html;
        }
    }
}