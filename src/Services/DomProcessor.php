<?php
// Fil: src/Services/DomProcessor.php
namespace NowOnline\EltBlocks\Services;

// Vigtig: Sørg for at den kan finde DataHelper-klassen
use NowOnline\EltBlocks\Services\DataHelper;

if (!defined('ABSPATH')) { exit; }

/**
 * Håndterer al avanceret DOM-manipulation for at injicere data i HTML-skabelonen.
 * Logik flyttet fra Renderer.php for SRP.
 */
final class DomProcessor
{
    /**
     * Sikker wrapper omkring DOMDocument.
     */
    private static function safeDom(string $html, callable $fn): string
    {
        if (!class_exists('\DOMDocument')) return $html;
        try {
            $doc = new \DOMDocument();
            \libxml_use_internal_errors(true);
            // Sørg for libxml constants er defineret
            if (!defined('LIBXML_HTML_NOIMPLIED')) define('LIBXML_HTML_NOIMPLIED', 0);
            if (!defined('LIBXML_HTML_NODEFDTD')) define('LIBXML_HTML_NODEFDTD', 0);
            
            $doc->loadHTML('<?xml encoding="utf-8"?><div id="__nowroot">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            \libxml_clear_errors();
            $xpath = new \DOMXPath($doc);
            $root  = $doc->getElementById('__nowroot');
            $fn($doc, $xpath, $root);
            $out = '';
            foreach ($root->childNodes as $child) { $out .= $doc->saveHTML($child); }
            return $out;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) error_log('[NowOnline EltBlocks] DOM fail: '.$e->getMessage());
            return $html;
        }
    }

    /**
     * Erstat billeder og kilder baseret på imgMap og bgMap.
     */
    public function rewrite_media_dom(string $html, array $imgMap, array $bgMap): string
    {
        if (empty($imgMap) && empty($bgMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($imgMap, $bgMap) {
            $wipe = function(\DOMElement $el, array $names) {
                foreach ($names as $n) if ($el->hasAttribute($n)) $el->removeAttribute($n);
            };
            $setBg = function(\DOMElement $el, string $url) {
                $style = $el->getAttribute('style');
                $style = preg_replace('/background-image\s*:\s*[^;]+;?/i', '', (string)$style);
                $style = rtrim(trim((string)$style), '; ');
                if ($style !== '') $style .= '; ';
                $style .= 'background-image:url(' . esc_url($url) . ')';
                $el->setAttribute('style', $style);
            };

            if (!empty($imgMap)) {
                foreach ($xpath->query('//*[@data-now-img or @data-now-image]') as $wrap) {
                    /** @var \DOMElement $wrap */
                    $key = strtolower($wrap->getAttribute('data-now-img') ?: $wrap->getAttribute('data-now-image'));
                    if ($key && isset($imgMap[$key])) {
                        $url = $imgMap[$key];
                        $img = $xpath->query('.//img', $wrap)->item(0);
                        if ($img instanceof \DOMElement) {
                            $wipe($img, ['src','srcset','sizes','data-src','data-srcset','data-lazy-src','data-lazy-srcset']);
                            $img->setAttribute('src', esc_url($url));
                        }
                        foreach ($xpath->query('.//source', $wrap) as $src) {
                            /** @var \DOMElement $src */
                            $wipe($src, ['srcset','data-srcset']);
                            $src->setAttribute('srcset', esc_url($url));
                        }
                    }
                }
                foreach ($xpath->query('//*[contains(@class,"now-img-") or contains(@class,"now-image-")]') as $wrap) {
                    /** @var \DOMElement $wrap */
                    $class = ' ' . $wrap->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-(?:img|image)-([a-z0-9_-]+)\b/i', $class, $m)) {
                        $key = strtolower($m[1]);
                        $url = $imgMap[$key] ?? '';
                        if ($url === '') continue;
                        $img = $xpath->query('.//img', $wrap)->item(0);
                        if ($img instanceof \DOMElement) {
                            $wipe($img, ['src','srcset','sizes','data-src','data-srcset','data-lazy-src','data-lazy-srcset']);
                            $img->setAttribute('src', esc_url($url));
                        }
                        foreach ($xpath->query('.//source', $wrap) as $src) {
                            $wipe($src, ['srcset','data-srcset']);
                            $src->setAttribute('srcset', esc_url($url));
                        }
                    }
                }
            }

            if (!empty($bgMap)) {
                foreach ($xpath->query('//*[@data-now-bg]') as $el) {
                    /** @var \DOMElement $el */
                    $key = strtolower($el->getAttribute('data-now-bg'));
                    if ($key && isset($bgMap[$key])) $setBg($el, $bgMap[$key]);
                }
                foreach ($xpath->query('//*[contains(@class,"now-bg-")]') as $el) {
                    /** @var \DOMElement $el */
                    $class = ' ' . $el->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-bg-([a-z0-9_-]+)\b/i', $class, $m)) {
                        $key = strtolower($m[1]);
                        if (isset($bgMap[$key])) $setBg($el, $bgMap[$key]);
                    }
                }
            }
        });
    }

    private static function parse_elementor_settings(\DOMElement $el): array
    {
        $raw = html_entity_decode((string)$el->getAttribute('data-settings'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $j = $raw ? json_decode($raw, true) : [];
        return is_array($j) ? $j : [];
    }

    private static function build_slide_from_prototype(\DOMDocument $doc, ?\DOMElement $proto, string $url, bool $lazyDefault): \DOMElement
    {
        if ($proto instanceof \DOMElement) {
            $slide = $proto->cloneNode(true);
            $cls = ' ' . $slide->getAttribute('class') . ' ';
            $cls = preg_replace('/\bswiper-slide-(?:duplicate|prev|next|active)\b/', '', $cls);
            $slide->setAttribute('class', trim(preg_replace('/\s+/', ' ', $cls)));
            foreach (['aria-label','aria-hidden','style','inert','data-swiper-slide-index'] as $rm) {
                if ($slide->hasAttribute($rm)) $slide->removeAttribute($rm);
            }
            $img = $slide->getElementsByTagName('img')->item(0);
            if (!($img instanceof \DOMElement)) {
                $img = $doc->createElement('img');
                $img->setAttribute('class', 'swiper-slide-image');
                $slide->appendChild($img);
            }
            $imgClass = ' ' . $img->getAttribute('class') . ' ';
            $lazy = (strpos($imgClass, ' swiper-lazy ') !== false) || $img->hasAttribute('data-src') || $lazyDefault;
            foreach (['src','srcset','sizes','data-src','data-srcset','data-lazy','data-lazy-src','data-lazy-srcset'] as $rm) {
                if ($img->hasAttribute($rm)) $img->removeAttribute($rm);
            }
            if ($lazy) {
                if (strpos($imgClass, ' swiper-lazy ') === false) {
                    $img->setAttribute('class', trim($img->getAttribute('class') . ' swiper-lazy'));
                }
                $img->setAttribute('data-src', esc_url($url));
                $img->setAttribute('src', esc_url($url));
            } else {
                $img->setAttribute('src', esc_url($url));
            }
            if (!$img->hasAttribute('alt')) $img->setAttribute('alt','');
            return $slide;
        }
        $slide  = $doc->createElement('div');
        $slide->setAttribute('class', 'swiper-slide');
        $fig    = $doc->createElement('figure');
        $fig->setAttribute('class', 'swiper-slide-inner');
        $img    = $doc->createElement('img');
        $img->setAttribute('class', $lazyDefault ? 'swiper-slide-image swiper-lazy' : 'swiper-slide-image');
        if ($lazyDefault) { $img->setAttribute('data-src', esc_url($url)); $img->setAttribute('src', esc_url($url)); }
        else { $img->setAttribute('src', esc_url($url)); }
        $img->setAttribute('alt','');
        $fig->appendChild($img);
        $slide->appendChild($fig);
        return $slide;
    }

    /**
     * Genopbygger gallerier (Swiper/grid) baseret på galMap.
     */
    public function rewrite_galleries_dom(string $html, array $galMap): string
    {
        if (empty($galMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($galMap) {
            $nodes = $xpath->query('//*[@data-now-gallery or @data-now-galleri or contains(@class,"now-gallery-") or contains(@class,"now-galleri-")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $key = strtolower($el->getAttribute('data-now-gallery') ?: $el->getAttribute('data-now-galleri'));
                if ($key === '') {
                    $cls = ' ' . $el->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-(?:gallery|galleri)-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
                }
                if ($key === '' || empty($galMap[$key])) continue;
                $urls = $galMap[$key];

                $settings    = self::parse_elementor_settings($el);
                $lazyDefault = !empty($settings['lazyload']) && ($settings['lazyload'] === 'yes' || $settings['lazyload'] === true);

                $wrapper = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " swiper-wrapper ")]', $el)->item(0);
                $proto   = null;
                if ($wrapper instanceof \DOMElement) {
                    $protoList = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")]', $wrapper);
                    if ($protoList && $protoList->length) $proto = $protoList->item(0);
                }

                if ($wrapper instanceof \DOMElement) {
                    while ($wrapper->firstChild) $wrapper->removeChild($wrapper->firstChild);
                    foreach ($urls as $u) {
                        $wrapper->appendChild(self::build_slide_from_prototype($doc, $proto, $u, $lazyDefault));
                    }
                } else {
                    while ($el->firstChild) $el->removeChild($el->firstChild);
                    $grid = $doc->createElement('div');
                    $grid->setAttribute('class', 'nowonline-elt-gallery');
                    foreach ($urls as $u) {
                        $img = $doc->createElement('img');
                        $img->setAttribute('decoding','async');
                        $img->setAttribute('src', esc_url($u));
                        $img->setAttribute('alt','');
                        $grid->appendChild($img);
                    }
                    $el->appendChild($grid);
                }
            }
        });
    }

    /**
     * Anvender baggrundsfarve inline.
     */
    public function apply_bg_color_inline(string $html, string $color): string
    {
        if ($color === '') return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($color) {
            $nodes = $xpath->query('//*[@data-nowonline-bg or contains(concat(" ", normalize-space(@class), " "), " nowonline-bg ")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $style = (string)$el->getAttribute('style');
                $style = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');
                if ($style !== '') $style .= '; ';
                $style .= 'background-color:' . $color;
                $el->setAttribute('style', $style);

                foreach ($el->getElementsByTagName('div') as $child) {
                    if (strpos(' '.$child->getAttribute('class').' ', ' elementor-background-overlay ') !== false) {
                        $ov = (string)$child->getAttribute('style');
                        $ov = preg_replace('/(?:^|;)\s*background(?:-color)?\s*:\s*[^;]*;?/i', ';', $ov);
                        $ov = trim(preg_replace('/;+/', ';', $ov), '; ');
                        if ($ov !== '') $ov .= '; ';
                        $ov .= 'background-color:' . $color . ';';
                        $child->setAttribute('style', $ov);
                    }
                }
            }
        });
    }

    private static function discover_bg_target_node(\DOMXPath $xpath): ?\DOMElement
    {
        $q = [
            '//*[@data-nowonline-bg]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " nowonline-bg ")]',
            '//*[@data-now-bg]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " now-bg ")]',
            '//*[@data-now-video]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " now-video- ")]',
            '//*[@data-element_type="container" and (contains(@data-settings, "\"background_background\":\"video\"") or @bgvideo)]',
        ];
        foreach ($q as $expr) {
            $n = $xpath->query($expr)->item(0);
            if ($n instanceof \DOMElement) return $n;
        }
               return null;
    }

    private static function is_video_file(string $u): bool
    {
        return (bool)preg_match('~\.(mp4|m4v|webm|ogv|ogg)(\?.*)?$~i', $u);
    }

    /**
     * Anvender baggrundsmedie (billede/video) inline.
     */
    public function apply_bg_media_inline(string $html, array $opts, array $videoMap = []): string
    {
        $img        = isset($opts['img']) ? (string)$opts['img'] : '';
        $imgTablet  = isset($opts['imgTablet']) ? (string)$opts['imgTablet'] : '';
        $imgMobile  = isset($opts['imgMobile']) ? (string)$opts['imgMobile'] : '';
        $pos        = isset($opts['pos']) ? (string)$opts['pos'] : '';
        $size       = isset($opts['size']) ? (string)$opts['size'] : '';
        $repeat     = isset($opts['repeat']) ? (string)$opts['repeat'] : ''; // Inkluderer bgRepeat
        $fixed      = !empty($opts['fixed']);
        $video      = isset($opts['video']) ? (string)$opts['video'] : '';

        if ($img === '' && $imgTablet === '' && $imgMobile === '' && $video === '' && $repeat === '') return $html;

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($img,$imgTablet,$imgMobile,$pos,$size,$repeat,$fixed,$video,$videoMap) {
            $node = self::discover_bg_target_node($xpath);
            if (!($node instanceof \DOMElement)) return;

            // Video via attrs eller data-now-video
            $vidUrl = $video;
            if ($vidUrl === '') {
                $key = '';
                if ($node->hasAttribute('data-now-video')) {
                    $key = strtolower($node->getAttribute('data-now-video'));
                } else {
                    $cls = ' ' . $node->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-video-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
                }
                if ($key !== '' && !empty($videoMap[$key]) && self::is_video_file($videoMap[$key])) {
                    $vidUrl = (string)$videoMap[$key];
                }
            }

            if ($vidUrl !== '' && self::is_video_file($vidUrl)) {
                $cls = ' ' . $node->getAttribute('class') . ' ';
                if (strpos($cls, ' nowelt-has-bgvid ') === false) {
                    $node->setAttribute('class', trim($node->getAttribute('class') . ' nowelt-has-bgvid'));
                }
                foreach (iterator_to_array($node->getElementsByTagName('video')) as $v) {
                    if (strpos(' ' . $v->getAttribute('class') . ' ', ' nowelt-bg-video ') !== false) {
                        $node->removeChild($v);
                    }
                }
                $vid = $doc->createElement('video');
                $vid->setAttribute('class', 'nowelt-bg-video');
                $vid->setAttribute('autoplay', 'autoplay');
                $vid->setAttribute('muted', 'muted');
                $vid->setAttribute('loop', 'loop');
                $vid->setAttribute('playsinline', 'playsinline');
                $vid->setAttribute('preload', 'auto');
                $vid->setAttribute('data-no-lazy', '1');
                $vid->setAttribute('data-skip-lazy', '1');
                $vid->setAttribute('data-rocket-lazyload', 'ignore');

                $source = $doc->createElement('source');
                $source->setAttribute('src', esc_url($vidUrl));
                $vid->appendChild($source);

                if ($node->firstChild) $node->insertBefore($vid, $node->firstChild);
                else $node->appendChild($vid);

                $style = (string)$node->getAttribute('style');
                $style = preg_replace('/(?:^|;)\s*background-(position|size|attachment|repeat)\s*:\s*[^;]*;?/i', ';', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');
                if ($style !== '') $style .= '; ';
                if ($pos  !== '') $style .= 'background-position:' . $pos . ';';
                if ($size !== '') $style .= 'background-size:' . $size . ';';
                if ($repeat !== '') $style .= 'background-repeat:' . $repeat . ';';
                $style .= 'background-attachment:' . ($fixed ? 'fixed' : 'scroll') . ';';
                $node->setAttribute('style', $style);
                return;
            }

            // fallback: billede
            $style = (string)$node->getAttribute('style');
            $style = preg_replace('/(?:^|;)\s*background-(image|position|size|attachment|repeat)\s*:\s*[^;]*;?/i', ';', $style);
            $style = trim(preg_replace('/;+/', ';', $style), '; ');
            if ($style !== '') $style .= '; ';
            if ($img  !== '') $style .= 'background-image:url(' . esc_url($img) . ');';
            if ($pos  !== '') $style .= 'background-position:' . $pos . ';';
            if ($size !== '') $style .= 'background-size:' . $size . ';';
            if ($repeat !== '') $style .= 'background-repeat:' . $repeat . ';';
            if ($fixed)       $style .= 'background-attachment:fixed;';
            $node->setAttribute('style', $style);

            $uid = 'nowbg-' . uniqid();
            $node->setAttribute('data-nowbg-id', $uid);

            $css = '';
            if ($imgTablet !== '') $css .= '@media (max-width:1024px){[data-nowbg-id="'.$uid.'"]{background-image:url(' . esc_url($imgTablet) . ')}}';
            if ($imgMobile !== '') $css .= '@media (max-width:767px){[data-nowbg-id="'.$uid.'"]{background-image:url(' . esc_url($imgMobile) . ')}}';
            if ($css !== '') {
                $styleTag = $doc->createElement('style');
                $styleTag->appendChild($doc->createTextNode($css));
                $root->appendChild($styleTag);
            }
        });
    }

    /**
     * Helper til at konvertere video-URL'er til embed-URL'er.
     * Gøres public static så Renderer kan bruge den i sin simple fallback.
     */
    public static function to_embed_url(string $u): array
    {
        $url = trim($u);
        if ($url === '') return ['kind'=>'file','url'=>$url];
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        if (strpos($host,'youtube.com') !== false || strpos($host,'youtu.be') !== false) {
            if (preg_match('~youtu\.be/([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (preg_match('~youtube\.com/shorts/([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (preg_match('~[?&]v=([^?&/]+)~i', $url, $m)) return ['kind'=>'youtube', 'url'=>'https://www.youtube.com/embed/'.$m[1]];
            if (strpos($url,'/embed/') !== false) return ['kind'=>'youtube', 'url'=>$url];
        }

        if (strpos($host,'vimeo.com') !== false) {
            if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) return ['kind'=>'vimeo', 'url'=>'https://player.vimeo.com/video/'.$m[1]];
            if (preg_match('~player\.vimeo\.com/video/([0-9]+)~i', $url, $m)) return ['kind'=>'vimeo', 'url'=>$url];
        }

        return ['kind'=> self::is_video_file($url) ? 'file' : 'file', 'url'=>$url];
    }

    /**
     * Omskriver video-widgets (iframes/video-tags) baseret på videoMap.
     */
    public function rewrite_videos_dom(string $html, array $videoMap, array $posterMap): string
    {
        if (empty($videoMap)) return $html;
        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($videoMap, $posterMap) {
            $nodes = $xpath->query('//*[@data-now-video or contains(@class,"now-video-")]');
            foreach ($nodes as $el) {
                /** @var \DOMElement $el */
                $key = strtolower($el->getAttribute('data-now-video'));
                if ($key === '') {
                    $cls = ' ' . $el->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-video-([a-z0-9_-]+)\b/i', $cls, $m)) $key = strtolower($m[1]);
                }
                if ($key === '' || empty($videoMap[$key])) continue;

                $vid = self::to_embed_url($videoMap[$key]);
                $poster = '';

                if ($el->hasAttribute('data-now-poster')) {
                    $pKey = strtolower($el->getAttribute('data-now-poster'));
                    $poster = $posterMap[$pKey] ?? '';
                } elseif (!empty($posterMap[$key])) {
                    $poster = $posterMap[$key];
                }

                $iframe = $xpath->query('.//iframe', $el)->item(0);
                if ($iframe instanceof \DOMElement && ($vid['kind'] === 'youtube' || $vid['kind'] === 'vimeo')) {
                    if ($iframe->hasAttribute('srcdoc')) $iframe->removeAttribute('srcdoc');
                    $iframe->setAttribute('src', esc_url($vid['url']));
                    continue;
                }

                $video = $xpath->query('.//video', $el)->item(0);
                if ($video instanceof \DOMElement) {
                    if ($poster !== '') $video->setAttribute('poster', esc_url($poster));
                    if ($video->hasAttribute('src')) $video->removeAttribute('src');
                    foreach (iterator_to_array($video->getElementsByTagName('source')) as $src) {
                        $video->removeChild($src);
                    }
                    $source = $doc->createElement('source');
                    $source->setAttribute('src', esc_url($vid['url']));
                    $video->appendChild($source);
                    continue;
                }

                if ($vid['kind'] === 'youtube' || $vid['kind'] === 'vimeo') {
                    $new = $doc->createElement('iframe');
                    $new->setAttribute('src', esc_url($vid['url']));
                    $new->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                    $new->setAttribute('allowfullscreen', 'allowfullscreen');
                    $el->appendChild($new);
                } else {
                    $new = $doc->createElement('video');
                    $new->setAttribute('class', 'elementor-video');
                    $new->setAttribute('controls', 'controls');
                    if ($poster !== '') $new->setAttribute('poster', esc_url($poster));
                    $src = $doc->createElement('source');
                    $src->setAttribute('src', esc_url($vid['url']));
                    $new->appendChild($src);
                    $el->appendChild($new);
                }
            }
        });
    }

    /**
     * Omskriver knap-labels baseret på fields-data.
     */
    public function rewrite_button_labels_dom(string $html, array $fields): string
    {
        $map = [];
        foreach ($fields as $k => $v) { $map[strtolower((string)$k)] = $v; }

        $getLabel = function(string $key) use ($map): string {
            $key = strtolower($key);
            $cands = [
                $key, $key.'label', $key.'_label',
                $key.'text', $key.'_text',
                $key.'title', $key.'_title',
                'label_'.$key, 'text_'.$key, 'title_'.$key,
            ];
            foreach ($cands as $ck) {
                if (!array_key_exists($ck, $map)) continue;
                $val = $map[$ck];
                $txt = '';
                if (is_array($val)) {
                    $txt = (string)($val['title'] ?? $val['text'] ?? $val['label'] ?? '');
                } else {
                    $txt = (string)$val;
                }
                $txt = trim(wp_strip_all_tags($txt));
                if ($txt !== '') return $txt;
            }

            if (array_key_exists($key, $map) && is_array($map[$key])) {
                $arr = $map[$key];
                $txt = (string)($arr['title'] ?? $arr['text'] ?? $arr['label'] ?? '');
                $txt = trim(wp_strip_all_tags($txt));
                if ($txt !== '') return $txt;
            }
            return '';
        };

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($getLabel) {
            $nodes = $xpath->query('//a['
                . '@data-now-label or '
                . 'contains(concat(" ", normalize-space(@class), " "), " now-label- ") or '
                . '@data-now-key or '
                . 'starts-with(@id,"now-link-")'
                . ']');

            if (!$nodes) return;

            foreach ($nodes as $a) {
                /** @var \DOMElement $a */
                $labelKey = strtolower($a->getAttribute('data-now-label'));

                if ($labelKey === '') {
                    $cls = ' ' . $a->getAttribute('class') . ' ';
                    if (preg_match('/\bnow-label-([a-z0-9_-]+)\b/i', $cls, $m)) {
                        $labelKey = strtolower($m[1]);
                    }
                }
                if ($labelKey === '' && $a->hasAttribute('data-now-key')) {
                    $labelKey = strtolower($a->getAttribute('data-now-key'));
                }
                if ($labelKey === '' && $a->hasAttribute('id')) {
                    if (preg_match('/^now-link-([a-z0-9_-]+)$/i', $a->getAttribute('id'), $m)) {
                        $labelKey = strtolower($m[1]);
                    }
                }
                if ($labelKey === '') continue;

                $labelText = $getLabel($labelKey);
                if ($labelText === '') continue;

                $txtNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " elementor-button-text ")]', $a)->item(0);
                if ($txtNode instanceof \DOMElement) {
                    while ($txtNode->firstChild) $txtNode->removeChild($txtNode->firstChild);
                    $txtNode->appendChild($doc->createTextNode($labelText));
                    continue;
                }

                $leftIcons = []; $rightIcons = [];
                $iconNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " elementor-button-icon ")]', $a);
                if ($iconNodes) {
                    foreach ($iconNodes as $ic) {
                        /** @var \DOMElement $ic */
                        $clone = $ic->cloneNode(true);
                        if (strpos(' '.$ic->getAttribute('class').' ', ' elementor-align-icon-right ') !== false) $rightIcons[] = $clone;
                        else $leftIcons[] = $clone;
                    }
                }

                while ($a->firstChild) $a->removeChild($a->firstChild);

                $wrap = $doc->createElement('span');
                $wrap->setAttribute('class', 'elementor-button-content-wrapper');
                foreach ($leftIcons as $ic) $wrap->appendChild($ic);

                $span = $doc->createElement('span');
                $span->setAttribute('class', 'elementor-button-text');
                $span->appendChild($doc->createTextNode($labelText));
                $wrap->appendChild($span);

                foreach ($rightIcons as $ic) $wrap->appendChild($ic);
                $a->appendChild($wrap);
            }
        });
    }

    /**
     * Omskriver kerne-indhold (titel/beskrivelse) hvis placeholders mangler.
     */
    public function rewrite_core_content_dom(string $html, array $fields, array $defs): string
    {
        if (empty($fields)) return $html;

        // Nøglekandidater (lowercase)
        $titleKeys     = ['titel','title','heading','overskrift','headline'];
        $subtitleKeys  = ['undertitel','subtitle','tagline'];
        $descKeys      = ['beskrivelse','description','tekst','text','content','indhold'];

        // Værdier
        $titleText    = $this->first_text_from_fields($fields, $titleKeys, true);
        $subtitleText = $this->first_text_from_fields($fields, $subtitleKeys, true);
        $descHtml     = $this->first_html_from_fields($fields, $descKeys);

        if ($titleText === '' && $subtitleText === '' && $descHtml === '') return $html;

        return self::safeDom($html, function(\DOMDocument $doc, \DOMXPath $xpath, \DOMElement $root) use ($titleText, $subtitleText, $descHtml) {
            // Headings (Elementor)
            if ($titleText !== '') {
                $h1 = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]')->item(0);
                if ($h1 instanceof \DOMElement) {
                    while ($h1->firstChild) $h1->removeChild($h1->firstChild);
                    $h1->appendChild($doc->createTextNode($titleText));
                }
            }
            if ($subtitleText !== '') {
                $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-heading-title ")]');
                if ($nodes && $nodes->length >= 2) {
                    $h2 = $nodes->item(1);
                    if ($h2 instanceof \DOMElement) {
                        while ($h2->firstChild) $h2->removeChild($h2->firstChild);
                        $h2->appendChild($doc->createTextNode($subtitleText));
                    }
                }
            }

            // Tekst-editor (Elementor)
            if ($descHtml !== '') {
                $cont = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-text-editor ")]//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget-container ")]')->item(0);
                if ($cont instanceof \DOMElement) {
                    while ($cont->firstChild) $cont->removeChild($cont->firstChild);
                    // Indsæt HTML-fragment
                    $frag = $doc->createDocumentFragment();
                    if (@$frag->appendXML('<div>'.$descHtml.'</div>')) {
                        $tmp = $frag->firstChild;
                        if ($tmp) {
                            while ($tmp->firstChild) { $cont->appendChild($tmp->firstChild); }
                        }
                    } else {
                        // fallback: som tekst
                        $cont->appendChild($doc->createTextNode(wp_strip_all_tags($descHtml)));
                    }
                }
            }
        });
    }

    /** Hjælper: find første ikke-tomme tekstværdi ud fra en liste af keys */
    private static function first_text_from_fields(array $fields, array $keys, bool $inlineOnly): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $val = $fields[$k];
            $raw = '';
            if (is_array($val)) {
                $raw = (string)($val['title'] ?? $val['text'] ?? $val['label'] ?? '');
            } else {
                $raw = (string)$val;
            }
            // Kalder DataHelper statisk
            $san = DataHelper::sanitize_rich_html($raw, $inlineOnly);
            $san = trim(wp_strip_all_tags($san));
            if ($san !== '') return $san;
        }
        return '';
    }

    /** Hjælper: find første ikke-tomme RICH HTML (bevar markup) ud fra keys */
    private static function first_html_from_fields(array $fields, array $keys): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $val = $fields[$k];
            $raw = is_array($val) ? (string)($val['html'] ?? $val['content'] ?? $val['text'] ?? $val['title'] ?? '') : (string)$val;
            // Kalder DataHelper statisk
            $san = DataHelper::sanitize_rich_html($raw, false);
            $txt = trim(wp_strip_all_tags($san));
            if ($txt !== '') return $san;
        }
        return '';
    }
}