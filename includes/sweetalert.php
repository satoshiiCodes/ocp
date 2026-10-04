<?php
/**
 * includes/sweetalert.php
 *
 * Loads SweetAlert2, once per page, whichever way the page gets it.
 *
 * The shared chrome needs it: includes/top_bar.php wires the Logout link with Swal.fire, and
 * assets/js/app.js does the same for pages without the top bar. Eight pages included one of
 * those without ever loading the library, so on them `Swal` was not defined and clicking
 * Logout threw before it could redirect - the button simply did nothing.
 *
 * Pages that already link SweetAlert2 themselves keep doing so; this only loads it for the
 * ones that do not. The guard is window.SweetAlert, which is the name the CDN build defines,
 * because at this point in the document no script has run yet and a plain check for `Swal`
 * would not be reliable. The loader waits for DOMContentLoaded before pulling the script in,
 * so it cannot win a race against a page's own later link.
 */
if (!defined('OCP_SWEETALERT_LINKED')) {
    define('OCP_SWEETALERT_LINKED', true);
    ?>
    <script>
    (function () {
        'use strict';
        var CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
        var CSS = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
        function ensure() {
            if (window.SweetAlert || window.Swal) return;
            if (!document.querySelector('link[href*="sweetalert2"]')) {
                var css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = CSS;
                document.head.appendChild(css);
            }
            var s = document.createElement('script');
            s.src = CDN;
            s.async = false;
            document.head.appendChild(s);
        }
        ensure();
        document.addEventListener('DOMContentLoaded', ensure);
    })();
    </script>
    <?php
}
