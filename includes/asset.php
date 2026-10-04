<?php
/**
 * Cache-busting URLs for static assets.
 *
 * The plain .js files under assets/ are served with no cache headers. `mod_expires` is not loaded
 * in this Apache and `AllowOverride None` means a .htaccess file would be ignored, so the browser
 * is free to reuse its cached copy without asking the server. An edited script then keeps behaving
 * the old way until a hard refresh, which from the server side looks like the change did nothing.
 *
 * Appending the file's modification time makes the URL change exactly when the file does, so a new
 * copy is always fetched and an unchanged one is still cached normally.
 *
 * This lives in its own file rather than in includes/page_data.php because that file prints a data
 * island at include time; this one only defines a function and is safe to require early, before
 * any output.
 */

if (!function_exists('ocp_asset')) {
    /**
     * @param string $relative_path Path relative to the app root, e.g. assets/js/app.js
     * @return string The path with a ?v=<mtime> suffix, or unchanged if the file is missing.
     */
    function ocp_asset(string $relative_path): string
    {
        $path = __DIR__ . '/../' . ltrim($relative_path, '/');

        if (!is_file($path)) {
            return $relative_path;
        }

        return $relative_path . '?v=' . filemtime($path);
    }
}
