/* includes/top_bar.js
 * The bell in includes/top_bar.php.
 *
 * The badge and the dropdown are driven by a Server-Sent Events stream
 * (api/routing_notifications-stream.php). The browser opens ONE connection and the server
 * writes to it only when the list of requests waiting on this user actually changes - there is
 * no timer here polling the server and no page refresh. The server ends each connection after
 * ~25 seconds and this reconnects, which is what keeps the stream inside Apache's timeout.
 *
 * The server sends the finished markup for the whole list, so this only has to swap it in.
 * That markup comes from the same partial the page rendered, so a live update is identical to
 * a fresh load.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Logout functionality
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Perform logout actions here (clear session, redirect, etc.)
                    window.location.href = 'actions/logout.php';
                }
            });
        });
    }

    // ---- Real-time "action required" notifications -------------------------------------
    const bell = document.getElementById('notificationDropdown');
    const list = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');

    if (!bell || !list || !badge) {
        return;
    }

    const streamUrl = bell.getAttribute('data-notification-stream');
    if (!streamUrl || typeof window.EventSource === 'undefined') {
        // No stream support: leave the server-rendered list exactly as it is. It is still
        // correct as of page load, it just will not update by itself.
        return;
    }

    let lastSignature = null;
    let source = null;
    let retryDelay = 3000;
    let closed = false;

    // Bootstrap anchors the panel to the bell and Popper keeps re-positioning it while it is
    // open. On a phone the CSS pins the panel under the navbar instead, so Popper is pointed at
    // a fixed strategy that does not move the panel, and the 2px offset goes with it. The
    // pinned top comes from the navbar's real height. Above the breakpoint none of the overrides
    // apply and Popper positions the panel exactly as it did before.
    const pinnedOnSmallScreens = function () {
        return window.matchMedia('(max-width: 767.98px)').matches;
    };

    function preparePanelPosition() {
        const nav = bell.closest('.sb-topnav') || document.querySelector('.sb-topnav');
        if (!nav) {
            return;
        }
        if (pinnedOnSmallScreens()) {
            const height = Math.round(nav.getBoundingClientRect().height);
            if (height > 0) {
                document.documentElement.style.setProperty('--ocp-notification-top', height + 'px');
            }
        } else {
            document.documentElement.style.removeProperty('--ocp-notification-top');
        }
    }

    // The notification panel is positioned by the Dropdown class in assets/js/ui.js, which
    // places it against the viewport and flips it above the bell when there is no room below.
    // On a narrow screen .dropdown-notifications caps its own height instead, so no separate
    // pinned layout is needed.
    //
    // This used to configure Popper through `bootstrap.Dropdown..._config.popperConfig` to pin
    // the panel under the navbar on phones. Popper went out with Bootstrap, and `_config` is not
    // part of the replacement, so that line threw "Cannot set properties of undefined (setting
    // 'popperConfig')" on every click of the bell. Removed.
    bell.addEventListener('click', function () {
        preparePanelPosition();
    });
    window.addEventListener('resize', preparePanelPosition);
    preparePanelPosition();

    function applyNotifications(payload) {
        if (!payload || typeof payload.html !== 'string') {
            return;
        }
        // The server only writes on a change, but guard anyway so a duplicate frame cannot
        // make the open dropdown flicker.
        if (payload.signature && payload.signature === lastSignature) {
            return;
        }
        lastSignature = payload.signature || null;

        list.innerHTML = payload.html;

        const count = parseInt(payload.count, 10) || 0;
        badge.textContent = count;
        badge.classList.toggle('d-none', count === 0);

        // A new request arriving while the page is open deserves more than a badge, but only
        // when the list is not already in front of the user.
        if (count > 0 && !bell.parentElement.classList.contains('show')) {
            bell.classList.add('notification-pulse');
            window.setTimeout(function() {
                bell.classList.remove('notification-pulse');
            }, 1600);
        }
    }

    function connect() {
        if (closed) {
            return;
        }
        source = new EventSource(streamUrl);

        source.addEventListener('notifications', function(event) {
            // A successful frame means the connection is healthy again.
            retryDelay = 3000;
            let payload = null;
            try {
                payload = JSON.parse(event.data);
            } catch (e) {
                return;
            }
            applyNotifications(payload);
        });

        source.onopen = function() {
            retryDelay = 3000;
        };

        source.onerror = function() {
            // EventSource reconnects on its own, but the browser's back-off is fixed. Closing
            // and reconnecting here applies a small growing delay instead of hammering the
            // server when it is genuinely unavailable (for example while the user is signed
            // out, or Apache is restarting).
            if (source) {
                source.close();
                source = null;
            }
            if (closed) {
                return;
            }
            window.setTimeout(connect, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 30000);
        };
    }

    connect();

    // Leaving the page should not leave the stream open: without this, a browser navigating
    // away keeps the server writing into a socket nobody is reading until it times out.
    window.addEventListener('pagehide', function() {
        closed = true;
        if (source) {
            source.close();
            source = null;
        }
    });
});
