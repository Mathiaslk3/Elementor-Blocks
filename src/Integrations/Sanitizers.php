<?php
// Fil: src/Integrations/Sanitizers.php
namespace NowOnline\EltBlocks\Integrations;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer server-side og client-side "rensning" af Elementor-widgets.
 * Især for at undgå nestede <h2>-tags eller duplikerede overskrifter.
 * Flyttet fra nowonline-elementor-blocks.php for SRP.
 */
final class Sanitizers
{
    public function register(): void
    {
        // Server-side PHP sanitizers
        add_action('elementor/frontend/widget/before_render', [$this, 'clean_widget_title_setting'], 9);
        add_filter('elementor/widget/render_content', [$this, 'merge_duplicate_headings_render'], 20, 2);

        // Client-side JS fix (betinget indlæsning)
        if ($this->should_enqueue_heading_js_fix()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_heading_js_fix'], 20);
            add_action('elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_heading_js_fix'], 20);
        }
    }

    /**
     * Fjerner <h> tags fra 'title' setting FØR render.
     */
    public function clean_widget_title_setting($widget): void
    {
        try {
            if (!method_exists($widget, 'get_name') || $widget->get_name() !== 'heading') {
                return;
            }
            $title = $widget->get_settings('title');
            if (!is_string($title) || $title === '' || stripos($title, '<h') === false) {
                return;
            }
            $clean = preg_replace('/<\/?h[1-6][^>]*>/i', '', $title);
            if ($clean !== null && $clean !== $title) {
                $widget->set_settings('title', $clean);
            }
        } catch (\Throwable $e) {
            // Fejl, men vi lader være med at crashe render
        }
    }

    /**
     * Slår duplikerede <h> tags sammen EFTER render.
     */
    public function merge_duplicate_headings_render($content, $widget): string
    {
        try {
            if (!is_string($content) || $content === '') {
                return $content;
            }
            if (!method_exists($widget, 'get_name') || $widget->get_name() !== 'heading') {
                return $content;
            }
            if (stripos($content, 'elementor-heading-title') === false) {
                return $content;
            }

            // libxml constants
            if (!defined('LIBXML_HTML_NOIMPLIED')) define('LIBXML_HTML_NOIMPLIED', 0);
            if (!defined('LIBXML_HTML_NODEFDTD')) define('LIBXML_HTML_NODEFDTD', 0);

            $wrap_html = '<div id="_nowonline_head_wrap">' . $content . '</div>';
            $dom = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrap_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $xp = new \DOMXPath($dom);

            $container = $xp->query('//*[@id="_nowonline_head_wrap"]//div[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ")]')->item(0);
            if (!$container) {
                return $content;
            }

            $title = null;
            foreach (['h1','h2','h3','h4','h5','h6'] as $tag) {
                $node = $xp->query('.//'.$tag.'[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]', $container)->item(0);
                if ($node) {
                    $title = $node;
                    break;
                }
            }
            if (!$title) {
                return $content;
            }

            $extras = [];
            for ($n = $title->nextSibling; $n; $n = $n->nextSibling) {
                if ($n->nodeType !== XML_ELEMENT_NODE) {
                    break;
                }
                $tn = strtolower($n->nodeName);
                if (!in_array($tn, ['h1','h2','h3','h4','h5','h6'], true)) {
                    break;
                }
                $extras[] = $n;
            }
            if (!$extras) {
                return $content;
            }

            $first = $extras[0];
            $desired = strtolower($first->tagName);
            $baseStyle = $title->hasAttribute('style') ? trim($title->getAttribute('style')) : '';
            $userStyle = $first->hasAttribute('style') ? trim($first->getAttribute('style')) : '';
            $mergedStyle = trim(($baseStyle ? $baseStyle.'; ' : '') . $userStyle);
            $move = static function(\DOMElement $from, \DOMElement $to) {
                while ($from->firstChild) {
                    $to->appendChild($from->firstChild);
                }
            };

            if (strtolower($title->tagName) !== $desired) {
                $repl = $dom->createElement($desired);
                if ($title->hasAttributes()) {
                    foreach (iterator_to_array($title->attributes) as $attr) {
                        $repl->setAttribute($attr->name, $attr->value);
                    }
                }
                foreach ($extras as $ex) {
                    $move($ex, $repl);
                }
                $title->parentNode->replaceChild($repl, $title);
                $title = $repl;
            } else {
                while ($title->firstChild) {
                    $title->removeChild($title->firstChild);
                }
                foreach ($extras as $ex) {
                    $move($ex, $title);
                }
            }
            if ($mergedStyle !== '') {
                $title->setAttribute('style', $mergedStyle);
            }
            foreach ($extras as $ex) {
                if ($ex->parentNode) {
                    $ex->parentNode->removeChild($ex);
                }
            }

            $wrap = $dom->getElementById('_nowonline_head_wrap');
            if (!$wrap) {
                return $content;
            }
            $out = '';
            foreach ($wrap->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
            return $out;
        } catch (\Throwable $e) {
            return $content;
        }
    }

    /**
     * Betinget logik for at indlæse JS-fixet.
     */
    private function should_enqueue_heading_js_fix(): bool
    {
        $enable = apply_filters('nowonline_elt_enqueue_heading_js_fix', false);
        if (isset($_GET['nowonline_js_fix'])) {
            $enable = ($_GET['nowonline_js_fix'] === '1');
        }
        return $enable;
    }

    /**
     * Indlæs assets/fix-headings.js
     */
    public function enqueue_heading_js_fix(): void
    {
        if (wp_script_is('nowonline-elt-fix-headings', 'enqueued')) {
            return;
        }
        $path = plugin_dir_path(NOWONLINE_ELT_FILE) . 'assets/fix-headings.js';
        $ver  = file_exists($path) ? (string) filemtime($path) : '1.0.0';
        wp_enqueue_script('nowonline-elt-fix-headings', plugins_url('assets/fix-headings.js', NOWONLINE_ELT_FILE), [], $ver, true);
    }
}