/* app.js
 * Shared behaviour for every page of the OCP system.
 *
 * Loaded by index.php and by every page that includes includes/top_bar.php,
 * before the page specific assets/js/<page>.js file.
 *
 * Exposes:
 *   ocpRaw(dataIsland, key)  - reads a value out of the page data island
 *                              rendered by includes/page_data.php
 *   $, $$                    - document.querySelector / querySelectorAll
 *   ocpOnReady(fn)           - DOM ready helper
 *   ocpFetch(url, options)   - fetch wrapper that always returns JSON
 *
 * Wires up the shared chrome: the sidebar toggle (#sidebarToggle) and the
 * profile dropdown logout link (#logoutLink).
 */
(function () {
    'use strict';

    /**
     * Reads one member of a page data island.
     *
     * PHP renders the island as a JSON object, so every member already has the
     * right JavaScript type: strings stay strings, arrays stay arrays, booleans
     * stay booleans. Raw HTML/text fragments captured from PHP are plain
     * strings as well - the OCP_RAW marker is only used for the values that PHP
     * itself declares as raw JSON.
     */
    window.ocpRaw = function ocpRaw(island, key) {
        if (!island || typeof island !== 'object') return null;
        return Object.prototype.hasOwnProperty.call(island, key) ? island[key] : null;
    };

    /**
     * querySelector / querySelectorAll helpers.
     *
     * window.$ is only taken when nothing else has it. Several pages load jQuery and their
     * own scripts call jQuery methods - $(...).select2(), $(...).DataTable(), $(...).modal().
     * This file is loaded after jQuery on those pages, so assigning window.$ here replaced
     * jQuery with this helper: the page script then threw "$(...).select2 is not a function"
     * at the top of its DOMContentLoaded handler, and everything after that line in the same
     * handler never ran - no SweetAlerts, no DataTables, no listeners on any button.
     *
     * So $ is left alone when it is already defined, and the helper is always available as
     * window.ocp$ for anything that wants it by a name of its own.
     */
    window.ocp$ = function ocpQuery(selector, scope) {
        return (scope || document).querySelector(selector);
    };
    if (typeof window.$ === 'undefined') {
        window.$ = window.ocp$;
    }

    window.$$ = function (selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    };

    window.ocpOnReady = function ocpOnReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    };

    window.ocpFetch = function ocpFetch(url, options) {
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Invalid server response.' };
            });
        });
    };

    /* ------------------------------------------------------------ shared chrome */

    /*
     * Sidebar drawer.
     *
     * One class on <body> is the whole state machine: .sb-sidenav-toggled opens the drawer on
     * phones and tablets and collapses it on desktop (the CSS decides which, at the 1024px
     * breakpoint). Everything that can change it goes through setSidebar() so the class, the
     * stored preference and the backdrop can never disagree.
     *
     * This is the only place that toggles it. A second listener anywhere else toggles the same
     * class twice per click, which cancels itself out and makes the button look dead.
     */
    var SIDEBAR_KEY = 'sb|sidebar-toggle';

    function setSidebar(open) {
        document.body.classList.toggle('sb-sidenav-toggled', !!open);
        try {
            localStorage.setItem(SIDEBAR_KEY, String(!!open));
        } catch (e) { /* private mode: ignore */ }
    }

    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (event) {
            event.preventDefault();
            setSidebar(!document.body.classList.contains('sb-sidenav-toggled'));
        });

        // Restore the state the user left behind.
        try {
            if (localStorage.getItem(SIDEBAR_KEY) === 'true') {
                setSidebar(true);
            }
        } catch (e) { /* private mode: ignore */ }

        // Tapping the dimmed page behind the drawer closes it - expected on touch, where the
        // drawer covers most of the screen.
        var backdrop = document.querySelector('.sb-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function () { setSidebar(false); });
        }

        // Escape closes the drawer too.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('sb-sidenav-toggled')) {
                setSidebar(false);
            }
        });

        // A drawer left open while the window grows to desktop would keep the collapsed
        // desktop layout. Clear it once the drawer stops being a drawer.
        var wide = window.matchMedia('(min-width: 1024px)');
        var onBreakpoint = function (e) {
            if (e.matches) setSidebar(false);
        };
        if (wide.addEventListener) wide.addEventListener('change', onBreakpoint);
        else if (wide.addListener) wide.addListener(onBreakpoint);
    }

    // Logout from the profile dropdown, with a confirmation step when SweetAlert
    // is available on the page.
    var logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', function (event) {
            event.preventDefault();

            var go = function () {
                window.location.href = 'actions/logout.php';
            };

            if (window.Swal) {
                window.Swal.fire({
                    title: 'Logout Confirmation',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then(function (result) {
                    if (result.isConfirmed) go();
                });
            } else if (window.confirm('Are you sure you want to logout?')) {
                go();
            }
        });
    }
})();
