<?php
// Fil: src/Rendering/DynamicCss.php
namespace NowOnline\EltBlocks\Rendering;

use NowOnline\EltBlocks\Services\DataHelper;

if (!defined('ABSPATH')) { exit; }

/**
 * Bygger de dynamiske CSS-strenge (wrapper styles, klasser og responsive style tag)
 * baseret på blok-attributter.
 * Logik flyttet fra Renderer.php for SRP.
 */
final class DynamicCss
{
    /**
     * @return array{style_attr: string, class_attr: string, style_tag: string, uid_attr: string}
     */
    public function build_wrapper_styles(array $attrs): array
    {
        $vars = [];
        if (!empty($attrs['containerBg']))   $vars['--now-bg-color']  = DataHelper::sanitize_color((string)$attrs['containerBg']);
        if (!empty($attrs['btnTextColor']))  $vars['--now-btn-color'] = DataHelper::sanitize_color((string)$attrs['btnTextColor']);
        if (!empty($attrs['btnBorderColor']))$vars['--now-btn-bdc']   = DataHelper::sanitize_color((string)$attrs['btnBorderColor']);
        if (!empty($attrs['btnBorderWidth']))$vars['--now-btn-bdw']   = DataHelper::sanitize_length((string)$attrs['btnBorderWidth']);
        if (!empty($attrs['btnBorderRadius']))$vars['--now-btn-rad']   = DataHelper::sanitize_length((string)$attrs['btnBorderRadius']);

        $styleAttr = '';
        if ($vars) {
            $styleAttr = ' style="' . esc_attr(implode(' ', array_map(
                static function($k,$v){ return $k.':'.$v.';'; },
                array_keys($vars), $vars
            ))) . '"';
        }

        // === Desktop FS-variabler + wrapper-klasser pr. level
        $desktopVars = '';
        $fsClasses   = [];
        $hasHeadingFs = false;

        $fsMap = [
            'fsH1' => '--now-fs-h1', 'fsH2' => '--now-fs-h2', 'fsH3' => '--now-fs-h3',
            'fsH4' => '--now-fs-h4', 'fsH5' => '--now-fs-h5', 'fsH6' => '--now-fs-h6',
            'fsBody' => '--now-fs-body', 'fsBtn' => '--now-fs-btn',
        ];

        foreach ($fsMap as $attrKey => $varName) {
            if (!empty($attrs[$attrKey])) {
                $val = DataHelper::sanitize_length($attrs[$attrKey]);
                if ($val) {
                    $desktopVars .= $varName . ':' . $val . ';';
                    $fsClasses[] = 'nowelt-fs-' . str_replace('fs', '', strtolower($attrKey));
                    if (strpos($varName, 'h') !== false) {
                        $hasHeadingFs = true;
                    }
                }
            }
        }
        
        if ($hasHeadingFs) $fsClasses[] = 'nowelt-fs-headings';
        $classAttr = $fsClasses ? ' '.implode(' ', $fsClasses) : '';

        // === Responsive CSS: hide/padding pr. device
        $uid = 'nowblk-' . uniqid();
        $sel = '[data-nowblk-id="'.$uid.'"]';
        $respCss = '';

        $padMap = [
            'Desktop' => [$attrs['padTopDesktop'] ?? '', $attrs['padBottomDesktop'] ?? ''],
            'Laptop'  => [$attrs['padTopLaptop']  ?? '', $attrs['padBottomLaptop']  ?? ''],
            'Tablet'  => [$attrs['padTopTablet']  ?? '', $attrs['padBottomTablet']  ?? ''],
            'Mobile'  => [$attrs['padTopMobile']  ?? '', $attrs['padBottomMobile']  ?? ''],
        ];

        foreach ($padMap as $device => $pads) {
            $top = DataHelper::sanitize_spacing($pads[0]);
            $bottom = DataHelper::sanitize_spacing($pads[1]);
            if ($top || $bottom) {
                $css = $sel.'{'
                    . ($top    ? 'padding-top:'.$top.';' : '')
                    . ($bottom ? 'padding-bottom:'.$bottom.';' : '')
                    . '}';
                if ($device === 'Laptop')  $respCss .= '@media (max-width:1440px){'.$css.'}';
                elseif ($device === 'Tablet')  $respCss .= '@media (max-width:1024px){'.$css.'}';
                elseif ($device === 'Mobile')  $respCss .= '@media (max-width:767px){'.$css.'}';
                else $respCss .= $css; // Desktop
            }
        }

        if (!empty($attrs['hideDesktop'])) $respCss .= '@media (min-width:1025px){'.$sel.'{display:none!important}}';
        if (!empty($attrs['hideTablet']))  $respCss .= '@media (min-width:768px) and (max-width:1024px){'.$sel.'{display:none!important}}';
        if (!empty($attrs['hideMobile']))  $respCss .= '@media (max-width:767px){'.$sel.'{display:none!important}}';

        if ($desktopVars !== '') {
            $respCss .= '@media (min-width:1025px){'.$sel.'{'.$desktopVars.'}}';
        }

        $styleTag = $respCss ? '<style>'.$respCss.'</style>' : '';

        return [
            'style_attr' => $styleAttr,
            'class_attr' => $classAttr,
            'style_tag'  => $styleTag,
            'uid_attr'   => ' data-nowblk-id="'.$uid.'"',
        ];
    }
}