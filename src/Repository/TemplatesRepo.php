<?php
// File: src/Repository/TemplatesRepo.php
namespace NowOnline\EltBlocks\Repository;

if (!defined('ABSPATH')) { exit; }

final class TemplatesRepo
{
    public const OPT_ALLOW_LIST = 'nowonline_elt_allowed_ids';

    public function ensure_thumbnails(): void
    {
        // why: Elementor library needs thumbnail support for our previews
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
     * Map for editor variations: [ {id,title,thumb}, ... ] limited to allow-list.
     * @return array<int, array{id:int,title:string,thumb:string}>
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

            $thumb_url = '';
            $thumb_id  = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($p->ID) : 0;
            if ($thumb_id){
                $thumb_url = wp_get_attachment_image_url($thumb_id, 'thumbnail')
                          ?: wp_get_attachment_image_url($thumb_id, 'medium')
                          ?: wp_get_attachment_url($thumb_id);
            }
            $out[] = [ 'id' => (int) $p->ID, 'title' => (string) $title, 'thumb' => (string) $thumb_url ];
        }
        return $out;
    }

    /**
     * Build field map: templateId => [ {key,type,label}, ... ]
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
                if     ($t === 'p')                 { $label .= ' (P)'; }
                elseif (preg_match('/^h[1-6]$/', $t)) { $label .= ' (' . strtoupper($t) . ')'; }
                elseif ($t === 'img')              { $label .= ' (Image)'; }
                elseif ($t === 'bg')               { $label .= ' (Background)'; }
                elseif ($t === 'url')              { $label .= ' (URL)'; }
                elseif ($t === 'textarea')         { $label .= ' (Textarea)'; }
                elseif ($t === 'rich' || $t === 'wysiwyg') { $label .= ' (Rich)'; }
                else                                { $label .= ' (Text)'; }
                $list[] = [ 'key' => (string) $k, 'type' => (string) $t, 'label' => (string) $label ];
            }
            $res[$id] = $list;
        }
        return $res;
    }

    /** Admin list helper (ignores allow-list; full browse) */
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

            $thumb_url = '';
            $thumb_id  = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($p->ID) : 0;
            if ($thumb_id){
                $thumb_url = wp_get_attachment_image_url($thumb_id, 'medium')
                          ?: wp_get_attachment_image_url($thumb_id, 'thumbnail')
                          ?: wp_get_attachment_url($thumb_id);
            }
            $out[] = [ 'id' => (int) $p->ID, 'title' => (string) $title, 'thumb' => (string) $thumb_url ];
        }
        return $out;
    }
}
