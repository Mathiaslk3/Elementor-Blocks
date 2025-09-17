<?php
// File: src/Repository/TemplatesRepo.php
namespace NowOnline\EltBlocks\Repository;

if (!defined('ABSPATH')) { exit; }

final class TemplatesRepo
{
    /** Allow-list af Elementor template IDs (vises i editoren) */
    public const OPT_ALLOW_LIST      = 'nowonline_elt_allowed_ids';
    /** Evt. manuelle thumbnail-overrides: [ template_id => attachment_id ] */
    public const OPT_THUMB_OVERRIDES = 'nowonline_elt_thumb_overrides';

    public function ensure_thumbnails(): void
    {
        if (function_exists('add_post_type_support')) {
            add_post_type_support('elementor_library', 'thumbnail');
        }
    }

    /** @return int[] */
    public function get_allowed_ids(): array
    {
        $ids = get_option(self::OPT_ALLOW_LIST, []);
        if (!is_array($ids)) return [];
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn($v) => $v > 0);
        return array_values($ids);
    }

    /**
     * Map til editor-variationer (begrænset til allow-list).
     * @return array<int, array{id:int,title:string,thumb:string,preview:string}>
     */
    public function get_templates_map(): array
    {
        $allowed = $this->get_allowed_ids();
        if (empty($allowed)) return [];

        // ⬇️ Header er nu TILLADT (udeluk kun footer + kit som default)
        $exclude_types = apply_filters('nowonline_elt_excluded_types', ['footer','kit']);

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

        $overrides = get_option(self::OPT_THUMB_OVERRIDES, []);
        if (!is_array($overrides)) $overrides = [];

        $posts = get_posts($args);
        $out = [];
        foreach ($posts as $p){
            $title = get_the_title($p) ?: ('#' . $p->ID);
            $t     = strtolower($title);

            // ⬇️ Fjern kun “default kit” og rene footere baseret på titel. Header filtreres ikke.
            if (strpos($t, 'default kit') !== false || $t === 'footer') continue;

            $override_att = isset($overrides[$p->ID]) ? (int) $overrides[$p->ID] : 0;
            [$thumb, $preview] = $this->build_image_urls_for_post((int) $p->ID, $override_att);

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
     * Scanner placeholders for udvalgte templates og normaliserer felttyper.
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
                $key   = (string) $k;
                $type  = strtolower(trim((string) $t));

                // --- alias-normalisering ---
                if (in_array($type, ['richtext','wysiwyg','rte','editor','html'], true)) {
                    $type = 'rich';
                } elseif (in_array($type, ['longtext','multiline','text_area'], true)) {
                    $type = 'textarea';
                } elseif (in_array($type, ['link','href'], true)) {
                    $type = 'url';
                } elseif (in_array($type, ['image','picture','photo','graphic'], true)) {
                    $type = 'img';
                } elseif (in_array($type, ['background','background_image'], true)) {
                    $type = 'bg';
                } elseif ($type === 'paragraph') {
                    $type = 'p';
                }
                // h1..h6 lader vi passere som er (matcher i editoren)

                // Label baseret på NORMALISERET type
                $label = ucwords(str_replace(['_','-'], ' ', $key));
                if     ($type === 'p')                   { $label .= ''; }
                elseif (preg_match('/^h[1-6]$/', $type)) { $label .= ' (' . strtoupper($type) . ')'; }
                elseif ($type === 'img')                 { $label .= ''; }
                elseif ($type === 'bg')                  { $label .= ''; }
                elseif ($type === 'url')                 { $label .= ''; }
                elseif ($type === 'textarea')            { $label .= ''; }
                elseif ($type === 'rich')                { $label .= ''; }
                else                                     { $label .= ''; }

                $list[] = [
                    'key'   => $key,
                    'type'  => $type,
                    'label' => $label,
                ];
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
        // ⬇️ Header er også tilladt her; udeluk kun footer + kit
        $exclude_types = ['footer','kit'];

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

        $overrides = get_option(self::OPT_THUMB_OVERRIDES, []);
        if (!is_array($overrides)) $overrides = [];

        $posts = get_posts($args);
        $out = [];
        foreach ($posts as $p){
            $title = get_the_title($p) ?: ('#' . $p->ID);
            $t     = strtolower($title);

            // ⬇️ Udeluk kun “default kit” og eksplcite footere pr. titel
            if (strpos($t, 'default kit') !== false || $t === 'footer') continue;

            $override_att = isset($overrides[$p->ID]) ? (int) $overrides[$p->ID] : 0;
            [$thumb, $preview] = $this->build_image_urls_for_post((int) $p->ID, $override_att);

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
     * @param int $post_id
     * @param int $override_att_id
     * @return array{0:string,1:string}
     */
    private function build_image_urls_for_post(int $post_id, int $override_att_id = 0): array
    {
        $att_id = $override_att_id > 0
            ? $override_att_id
            : (function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($post_id) : 0);

        if ($att_id <= 0) {
            return ['', ''];
        }

        $thumb = wp_get_attachment_image_url($att_id, 'medium')
              ?: wp_get_attachment_image_url($att_id, 'thumbnail')
              ?: wp_get_attachment_url($att_id)
              ?: '';

        $preview = wp_get_attachment_image_url($att_id, '1536x1536')
                ?: wp_get_attachment_image_url($att_id, 'large')
                ?: wp_get_attachment_image_url($att_id, '2048x2048')
                ?: (function_exists('image_get_intermediate_size')
                    ? wp_get_attachment_image_url($att_id, 'medium_large')
                    : null)
                ?: wp_get_attachment_url($att_id)
                ?: $thumb;

        return [ (string) $thumb, (string) $preview ];
    }
}
