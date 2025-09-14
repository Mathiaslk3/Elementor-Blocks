<?php
/**
 * Plugin Name: NowOnline – Elementor Blocks
 * Description: Allow-list Elementor templates as Gutenberg block variations, with typed placeholders ([[text]], [[rich]], [[img]], [[bg]], [[url]], [[p]], [[h1]]..[[h6]]). Includes thumbnails, field mapping and diagnostics.
 * Version: 2.12.7
 * Author: NowOnline
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('NOWONLINE_ELT_FILE')) {
    define('NOWONLINE_ELT_FILE', __FILE__);
}

/** PSR-4 autoloader for NowOnline\EltBlocks\* */
spl_autoload_register(function($class){
    $prefix = 'NowOnline\\EltBlocks\\';
    if (strpos($class, $prefix) !== 0) return;
    $rel  = substr($class, strlen($prefix));                 // e.g. "Assets\Assets"
    $path = __DIR__ . '/src/' . str_replace('\\','/',$rel) . '.php';
    if (is_readable($path)) require_once $path;
});

/** Fallback: indlæs Plugin.php direkte hvis autoloader ikke har gjort det */
if (!class_exists('NowOnline\\EltBlocks\\Plugin')) {
    $fallback = __DIR__ . '/src/Plugin.php';
    if (is_readable($fallback)) require_once $fallback;
}

/** Boot plugin */
\NowOnline\EltBlocks\Plugin::boot();
