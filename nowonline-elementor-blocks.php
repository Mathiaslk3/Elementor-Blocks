<?php
/**
 * Plugin Name: NowOnline – Elementor Blocks
 * Plugin URI:  https://github.com/Mathiaslk3/Elementor-Blocks
 * Description: Allow-list Elementor templates as Gutenberg block variations, with typed placeholders ([[text]], [[rich]], [[img]], [[bg]], [[url]], [[p]], [[h1]]..[[h6]]).
 * Version:     2.13.32
 * Author:      NowOnline
 * Author URI:  https://nowonline.dk/
 * License:     GPL-2.0-or-later
 * Update URI:  https://github.com/Mathiaslk3/Elementor-Blocks
 * GitHub Plugin URI: Mathiaslk3/Elementor-Blocks
 * Primary Branch:    main
 */

if (!defined('ABSPATH')) { exit; }
if (!defined('NOWONLINE_ELT_FILE')) { define('NOWONLINE_ELT_FILE', __FILE__); }

/** PSR-4 autoloader */
spl_autoload_register(function($class){
    $prefix = 'NowOnline\\EltBlocks\\';
    if (strpos($class, $prefix) !== 0) return;
    $rel  = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\','/',$rel) . '.php';
    if (is_readable($path)) require_once $path;
});

/** Fallback boot */
if (!class_exists('NowOnline\\EltBlocks\\Plugin')) {
    $fallback = __DIR__ . '/src/Plugin.php';
    if (is_readable($fallback)) require_once $fallback;
}

/** Boot the plugin */
\NowOnline\EltBlocks\Plugin::boot();