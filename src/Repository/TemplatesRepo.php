<?php
// File: src/Repository/TemplatesRepo.php
namespace NowOnline\EltBlocks\Repository;

if (!defined('ABSPATH')) { exit; }

final class TemplatesRepo
{
    public const OPT_ALLOW_LIST = 'nowonline_elt_allowed_ids';

    public function ensure_thumbnails(): void
    {
        // Elementor library skal have thumbnail-support til vores previews
        if (function_exists('add_post_type_support')) {
            add_post_type_support('elementor_library', 'thumbnail');
        }
    }

    /**
     * @return int[]
     */
    public function get_allowed_ids(): array
    {
        $ids = get_option(self::OPT_ALLOW_LIST, []);
        if (!is_array($ids)) return [];
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn($v) => $v > 0);
        return array_values($ids);
    }

    /**
     * Map til editor-variationer.
     * Returnerer poster begrænset til allow-list.
     *
     * @return array<int, array{id:int,title:string,thumb:string,preview:string}>
     */
    public function get_templates_map(): array
    {
        $allowed = $this->get_allowed_ids();
        if (empty($allowed)) return [];

        $exclude_types = apply_filters('nowonline_elt_excluded_types', ['header','footer','kit']);
        $args = [
            'post_type'      => 'elementor_library',
            'post_status'    => ['publish','private'],
            'posts_per_page' => -1,
            'orderby'        => 'post__in',
            'order'          => 'ASC',
            'post__in'       => $allowed,
        ];
        if (function_exists('taxonomy_exists') && taxonomy_exists('elementor_library_type')){
            $args['tax_query'] = [[
                'taxonomy' => 'elementor_library_type',
                'field'    => 'slug',
                'terms'    => $exclude_types,
                'operator' => 'NOT IN',
            ]];
        }

        $posts = get_posts($args);
        $out = [];
        foreach ($posts as $p){
            $title = get_the_title($p) ?: ('#' . $p->ID);
            $t     = strtolower($title);
            if (strpos($t, 'default kit') !== false || $t === 'header' || $t === 'footer') continue;

            [$thumb, $preview] = $this->build_image_urls_for_post($p->ID);

            $out[] = [
                'id'      => (int) $p->ID,
                'title'   => (string) $title,
                'thumb'   => (string) $thumb,    // lille, må gerne være crop (ok til ikon)
                'preview' => (string) $preview,  // ikke-croppet (large/medium_large/full)
            ];
        }
        return $out;
    }

    /**
     * Scanner placeholders for udvalgte templates.
     * @param int[] $ids
     * @return array<int,array<int,array{key:string,type:string,label:string}>>
     */
    public function get_templates_fieldmap(array $ids, \NowOnline\EltBlocks\Services\PlaceholderScanner $scanner): array
    {
        $res = [];
        foreach ($ids as $id){
            $id = (int) $id;
            if ($id <= 0) continue;

            $defs = $scanner->scan($id); // key => type
            $list = [];
            foreach ($defs as $k => $t){
                $label = ucwords(str_replace(['_','-'], ' ', (string) $k));
                if     ($t === 'p')                   { $label .= ' (P)'; }
                elseif (preg_match('/^h[1-6]$/', $t)) { $label .= ' (' . strtoupper($t) . ')'; }
                elseif ($t === 'img')                 { $label .= ' (Image)'; }
                elseif ($t === 'bg')                  { $label .= ' (Background)'; }
                elseif ($t === 'url')                 { $label .= ' (URL)'; }
                elseif ($t === 'textarea')            { $label .= ' (Textarea)'; }
                elseif ($t === 'rich' || $t === 'wysiwyg') { $label .= ' (Rich)'; }
                else                                  { $label .= ' (Text)'; }
                $list[] = [ 'key' => (string) $k, 'type' => (string) $t, 'label' => (string) $label ];
            }
            $res[$id] = $list;
        }
        return $res;
    }

    /**
     * Admin-liste (ignorerer allow-list; fuld browse)
     * @return array<int, array{id:int,title:string,thumb:string,preview:string}>
     */
    public function get_all_for_admin(): array
    {
        $exclude_types = ['header','footer','kit'];
        $args = [
            'post_type'      => 'elementor_library',
            'post_status'    => ['publish','private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if (function_exists('taxonomy_exists') && taxonomy_exists('elementor_library_type')){
            $args['tax_query'] = [[
                'taxonomy' => 'elementor_library_type',
                'field'    => 'slug',
                'terms'    => $exclude_types,
                'operator' => 'NOT IN',
            ]];
        }

        $posts = get_posts($args);
        $out = [];
        foreach ($posts as $p){
            $title = get_the_title($p) ?: ('#' . $p->ID);
            $t     = strtolower($title);
            if (strpos($t, 'default kit') !== false || $t === 'header' || $t === 'footer') continue;

            [$thumb, $preview] = $this->build_image_urls_for_post($p->ID);

            $out[] = [
                'id'      => (int) $p->ID,
                'title'   => (string) $title,
                'thumb'   => (string) $thumb,
                'preview' => (string) $preview,
            ];
        }
        return $out;
    }

    /**
     * Returnerer [thumb, preview] for et elementor_library-indlæg.
     * - thumb: egnet til små ikoner (foretræk 'medium', ellers 'thumbnail', ellers 'full')
     * - preview: bredt/ikke-croppet (foretræk 'large'/'medium_large', ellers 'full')
     *
     * @return array{0:string,1:string}
     */
    private function build_image_urls_for_post(int $post_id): array
    {
        $thumb = '';
        $preview = '';

        $thumb_id = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($post_id) : 0;
        if ($thumb_id) {
            // lille thumbnail til ikon
            $thumb = wp_get_attachment_image_url($thumb_id, 'medium')
                  ?: wp_get_attachment_image_url($thumb_id, 'thumbnail')
                  ?: wp_get_attachment_url($thumb_id);

            // stort, ikke-croppet preview
            // brug large/medium_large først (typisk ikke hard-croppet), fald tilbage til full
            $preview = wp_get_attachment_image_url($thumb_id, 'large')
                    ?: ( function_exists('image_get_intermediate_size')
                         ? wp_get_attachment_image_url($thumb_id, 'medium_large')
                         : null )
                    ?: wp_get_attachment_url($thumb_id);
        }

        return [ (string) ($thumb ?: ''), (string) ($preview ?: $thumb) ];
    }
}
