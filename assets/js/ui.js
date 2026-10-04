/* assets/js/ui.js
 *
 * The interactive layer: modals, dropdowns and tooltips, replacing Bootstrap's JavaScript bundle.
 *
 * It deliberately keeps Bootstrap's API. The app calls new bootstrap.Modal(el).show() in 78
 * places, jQuery's .modal('show') in 8, and listens for shown.bs.modal / hidden.bs.modal events
 * in about 30 more. Reimplementing the API means not one of those call sites has to change, and
 * the behaviour stays identical while the markup and styling move to Tailwind underneath it.
 *
 * Markup contract (unchanged from Bootstrap, so markup can be migrated a page at a time):
 *   <div class="modal" id="x">            the overlay; is-open shows it
 *   <button data-bs-dismiss="modal">      closes the nearest modal
 *   <button data-bs-toggle="modal" data-bs-target="#x">   opens it
 *   <button data-bs-toggle="dropdown" ...>  toggles the next .dropdown-menu
 *   [data-bs-toggle="tooltip"]            becomes a tooltip on hover/focus
 *
 * Events emitted on the modal element, mirroring Bootstrap, so existing listeners keep working:
 *   show.bs.modal / shown.bs.modal / hide.bs.modal / hidden.bs.modal
 */
(function () {
    'use strict';

    var instances = new WeakMap();
    var overlayCount = 0;

    function element(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.querySelector(target);
        // jQuery objects
        if (target.jquery) return target[0] || null;
        return target;
    }

    // Modal events are delivered through BOTH systems, because neither reaches the other.
    //
    // jQuery keeps its own handler list and does not receive a native CustomEvent dispatched
    // under the same name. Measured on a page with jQuery loaded:
    //
    //   native CustomEvent   -> jQuery handler: 0 calls, native listener: 1 call
    //   $(el).trigger(...)   -> jQuery handler: 1 call,  native listener: 0 calls
    //
    // The app needs both: the page scripts bind with $(...).on/.one - that is how the signature
    // pads set themselves up, and it was silently never running - while some listeners are
    // plain addEventListener.
    //
    // Both dispatches bubble, so a handler bound at BOTH levels for the same name could see the
    // event twice. Nothing in the app does that: the modal events are handled either through
    // jQuery (the page scripts) or natively (this file, bound straight onto the element, which
    // the jQuery dispatch does not reach). If that ever changes, this is the place to notice.
    function fire(target, name, relatedTarget) {
        var $ = window.jQuery;
        var cancelable = name === 'show.bs.modal' || name === 'hide.bs.modal';
        var prevented = false;

        // 1. jQuery's system, which native listeners never see.
        if ($ && $.Event) {
            var jqEvent = $.Event(name, { relatedTarget: relatedTarget || null });
            jqEvent.detail = { relatedTarget: relatedTarget || null };
            jqEvent.cancelable = cancelable;
            $(target).trigger(jqEvent);
            prevented = !!(jqEvent.isDefaultPrevented && jqEvent.isDefaultPrevented());
        } else {
            // No jQuery at all: the native path below is the only one.
            prevented = false;
        }

        // 2. The DOM's own system.
        var event = new CustomEvent(name, {
            bubbles: true,
            cancelable: cancelable,
            detail: { relatedTarget: relatedTarget || null }
        });
        // Bootstrap exposes the control that opened the modal as event.relatedTarget, and nine
        // handlers across the app read it that way - the Edit dialogs on items_categories,
        // item_names, expenses_type, spare_parts_categories, spare_parts_suppliers and
        // spare_parts all pull their row's data-id / data-name off it. CustomEvent only carries
        // it in .detail, so it is copied onto the event object to match.
        event.relatedTarget = relatedTarget || null;
        target.dispatchEvent(event);

        if (event.defaultPrevented) { prevented = true; }
        return { defaultPrevented: prevented };
    }

    /* ------------------------------------------------------------------ Modal */
    function Modal(target, options) {
        var el = element(target);
        if (!el) {
            // A page whose script builds a modal id at runtime can hand us one that is not on
            // the page. Bootstrap threw here too, and that single throw aborted the rest of the
            // page's script - every listener after it never got attached. Warn and stay quiet
            // instead, so one missing dialog cannot disable the whole page.
            if (window.console && console.warn) {
                console.warn('Modal: element not found; ignoring.');
            }
            this._element = null;
            this._options = options || {};
            this._isOpen = false;
            return;
        }
        var existing = instances.get(el);
        if (existing) {
            return existing;
        }
        this._element = el;
        this._options = options || {};
        this._isOpen = el.classList.contains('is-open');
        instances.set(el, this);
    }

    Modal.prototype.show = function (relatedTarget) {
        if (!this._element || this._isOpen) return;
        if (fire(this._element, 'show.bs.modal', relatedTarget).defaultPrevented) return;

        this._isOpen = true;
        this._element.classList.add('is-open');
        this._element.setAttribute('aria-modal', 'true');
        this._element.removeAttribute('aria-hidden');
        this._element.style.display = 'block';

        // Scroll is locked while a modal is up, and restored only when the last one closes.
        overlayCount++;
        document.body.style.overflow = 'hidden';

        var self = this;
        // The class change is what animates; report "shown" once the frame has painted. A
        // timeout rather than requestAnimationFrame so it still fires when the tab is hidden.
        window.setTimeout(function () {
            if (self._isOpen) {
                fire(self._element, 'shown.bs.modal', relatedTarget);
            }
        }, 0);

        // Move focus into the dialog so keyboard and screen-reader users land in the right place.
        var focusable = this._element.querySelector(
            'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), ' +
            'button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
        );
        if (focusable) {
            focusable.focus({ preventScroll: true });
        }
    };

    Modal.prototype.hide = function () {
        if (!this._isOpen) return;
        if (fire(this._element, 'hide.bs.modal').defaultPrevented) return;

        this._isOpen = false;
        this._element.classList.remove('is-open');
        this._element.style.display = '';
        this._element.removeAttribute('aria-modal');
        this._element.setAttribute('aria-hidden', 'true');

        overlayCount = Math.max(0, overlayCount - 1);
        if (overlayCount === 0) {
            document.body.style.overflow = '';
        }

        fire(this._element, 'hidden.bs.modal');
    };

    Modal.prototype.toggle = function (relatedTarget) {
        this._isOpen ? this.hide() : this.show(relatedTarget);
    };

    Modal.prototype.dispose = function () {
        this.hide();
        instances.delete(this._element);
    };

    Modal.getInstance = function (target) {
        var el = element(target);
        return el ? (instances.get(el) || null) : null;
    };

    Modal.getOrCreateInstance = function (target, options) {
        return Modal.getInstance(target) || new Modal(target, options);
    };

    /* ------------------------------------------------------------------ Tooltip */
    function Tooltip(target, options) {
        var el = element(target);
        if (!el) return;
        this._element = el;
        this._options = options || {};
        this._title = el.getAttribute('data-bs-title') || el.getAttribute('title') || '';
        if (el.getAttribute('title')) {
            // Keep the browser from drawing its own tooltip as well.
            el.setAttribute('data-bs-title', el.getAttribute('title'));
            el.removeAttribute('title');
        }
        this._element.setAttribute('data-tooltip-bound', '1');
    }

    Tooltip.prototype.dispose = function () {
        this._element.removeAttribute('data-tooltip-bound');
    };

    Tooltip.getInstance = function (target) {
        return element(target) ? {} : null;
    };

    Tooltip.getOrCreateInstance = function (target, options) {
        return new Tooltip(target, options);
    };

    /* ------------------------------------------------------------------ Dropdown */
    function Dropdown(target, options) {
        var el = element(target);
        if (!el) return;
        this._element = el;
        this._options = options || {};
        this._menu = el.parentElement ? el.parentElement.querySelector('.dropdown-menu') : null;
        // Positioning is bound once per instance so the menu follows the button on scroll or
        // resize while it is open, instead of drifting away from it.
        this._reposition = null;
        instances.set(el, this);
    }

    // Drops `position: fixed` coordinates on the menu.
    //
    // The menus in the tables live inside .table-responsive, which is overflow-x: auto, and an
    // absolutely positioned child of a scroll container is CLIPPED by it. That is why the
    // Generate PDF menu on the last rows of the PR page could not be seen: it opened, and was
    // cut off at the bottom edge of the table. Giving the open menu position: fixed with
    // viewport coordinates takes it out of the scroll container entirely.
    //
    // `position: fixed` brings its own trap: a transform, filter or backdrop-filter on ANY
    // ancestor makes the menu position against that ancestor instead of the viewport. The
    // offsets are therefore corrected using the offsetParent's rect, which handles both cases.
    Dropdown.prototype._place = function () {
        var menu = this._menu, el = this._element;
        if (!menu || !el) return;

        // Measure without any positioning in the way.
        menu.style.position = 'fixed';
        menu.style.top = '0px';
        menu.style.left = '0px';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.marginTop = '0px';
        menu.style.visibility = 'hidden';

        var btn = el.getBoundingClientRect();
        var mw = menu.offsetWidth;
        var mh = menu.offsetHeight;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var gap = 6;

        // Prefer below the button; flip above when there is not enough room.
        var below = vh - btn.bottom;
        var openUp = below < mh + gap && btn.top > below;
        var top = openUp ? btn.top - mh - gap : btn.bottom + gap;

        // Right-align with the button, then keep it inside the viewport.
        var left = btn.right - mw;
        if (left < 8) left = Math.min(btn.left, vw - mw - 8);
        if (left < 8) left = 8;

        // Correct for a transformed offsetParent.
        var op = menu.offsetParent;
        if (op && op !== document.body && op !== document.documentElement) {
            var or = op.getBoundingClientRect();
            left -= or.left;
            top -= or.top;
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
        menu.style.visibility = '';
        menu.classList.toggle('app-dropdown-up', openUp);
    };

    Dropdown.prototype.show = function () {
        if (!this._menu) return;
        // Only one menu open at a time. Opened via the button here rather than by a document
        // listener, so that opening one from a script closes the others too.
        document.querySelectorAll('.dropdown-menu.is-open').forEach(function (menu) {
            if (menu === this._menu) return;
            menu.classList.remove('is-open');
            menu.style.position = '';
            menu.style.top = '';
            menu.style.left = '';
            menu.style.right = '';
            menu.style.visibility = '';
            var owner = menu.parentElement ? menu.parentElement.querySelector('[data-bs-toggle="dropdown"]') : null;
            if (owner) owner.setAttribute('aria-expanded', 'false');
        }, this);

        this._menu.classList.add('is-open');
        this._place();
        this._element.setAttribute('aria-expanded', 'true');

        var self = this;
        if (!this._reposition) {
            this._reposition = function () {
                if (self._menu.classList.contains('is-open')) self._place();
            };
            window.addEventListener('resize', this._reposition);
            window.addEventListener('scroll', this._reposition, true);
        }
    };

    Dropdown.prototype.hide = function () {
        if (!this._menu) return;
        this._menu.classList.remove('is-open');
        // Hand positioning back to the stylesheet so a closed menu cannot affect layout.
        this._menu.style.position = '';
        this._menu.style.top = '';
        this._menu.style.left = '';
        this._menu.style.right = '';
        this._menu.style.bottom = '';
        this._menu.style.marginTop = '';
        this._menu.style.visibility = '';
        this._element.setAttribute('aria-expanded', 'false');
        if (this._reposition) {
            window.removeEventListener('resize', this._reposition);
            window.removeEventListener('scroll', this._reposition, true);
            this._reposition = null;
        }
    };

    Dropdown.prototype.toggle = function () {
        this._menu && this._menu.classList.contains('is-open') ? this.hide() : this.show();
    };

    Dropdown.prototype.dispose = function () {
        this.hide();
        instances.delete(this._element);
    };

    Dropdown.getInstance = function (target) {
        var el = element(target);
        return el ? (instances.get(el) || null) : null;
    };

    Dropdown.getOrCreateInstance = function (target, options) {
        return Dropdown.getInstance(target) || new Dropdown(target, options);
    };

    /* ------------------------------------------------------------------ global wiring */

    // Any control that declares a role is wired once, here - the same delegation Bootstrap used,
    // so markup can be added or replaced later (the notification list is rebuilt on every SSE
    // frame) without re-binding anything.
    document.addEventListener('click', function (event) {
        var dismiss = event.target.closest('[data-bs-dismiss]');
        if (dismiss) {
            var kind = dismiss.getAttribute('data-bs-dismiss');
            if (kind === 'modal') {
                event.preventDefault();
                var host = dismiss.closest('.modal');
                var shown = host ? Modal.getInstance(host) : null;
                if (shown) shown.hide();
            } else if (kind === 'dropdown') {
                event.preventDefault();
                var dd = Dropdown.getInstance(dismiss.closest('[data-bs-toggle="dropdown"]'));
                if (dd) dd.hide();
            } else if (kind === 'alert') {
                // Removes the alert the button sits in, which is what the pages expect from
                // Bootstrap's alert plugin. The pages keep data-bs-dismiss="alert" on the X.
                event.preventDefault();
                var alert = dismiss.closest('.alert');
                if (alert && alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }
        }

        var toggle = event.target.closest('[data-bs-toggle]');
        if (!toggle) {
            // A click anywhere else closes any open dropdown.
            document.querySelectorAll('.dropdown-menu.is-open').forEach(function (menu) {
                var trigger = menu.parentElement ? menu.parentElement.querySelector('[data-bs-toggle="dropdown"]') : null;
                if (trigger) {
                    var inst = Dropdown.getInstance(trigger);
                    if (inst) inst.hide();
                } else {
                    menu.classList.remove('is-open');
                }
            });
            return;
        }

        var what = toggle.getAttribute('data-bs-toggle');

        if (what === 'modal') {
            event.preventDefault();
            var targetSel = toggle.getAttribute('data-bs-target') ||
                            toggle.getAttribute('data-bs-target') ||
                            toggle.getAttribute('href');
            if (targetSel && targetSel.charAt(0) === '#') {
                var modalEl = document.querySelector(targetSel);
                if (modalEl) {
                    Modal.getOrCreateInstance(modalEl).show(toggle);
                }
            }
        } else if (what === 'dropdown') {
            event.preventDefault();
            event.stopPropagation();
            Dropdown.getOrCreateInstance(toggle).toggle();
        } else if (what === 'tooltip') {
            // A tooltip must NOT cancel the click. These are attached to submit buttons and
            // links - the View button on projects.php is a submit button carrying
            // data-bs-toggle="tooltip" - and calling preventDefault() here stopped the form
            // from submitting, so the button appeared to do nothing at all.
            Tooltip.getOrCreateInstance(toggle);
        }
    });

    // Clicking the dark area outside a dialog closes it.
    document.addEventListener('mousedown', function (event) {
        if (event.target.classList && event.target.classList.contains('modal')) {
            var inst = Modal.getInstance(event.target);
            if (inst) inst.hide();
        }
    });

    // Escape closes the topmost open modal, then any open dropdown.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var open = document.querySelectorAll('.modal.is-open');
        if (open.length) {
            var top = open[open.length - 1];
            var inst = Modal.getInstance(top);
            if (inst) inst.hide();
            return;
        }
        document.querySelectorAll('.dropdown-menu.is-open').forEach(function (menu) {
            var trigger = menu.parentElement ? menu.parentElement.querySelector('[data-bs-toggle="dropdown"]') : null;
            var inst = trigger ? Dropdown.getInstance(trigger) : null;
            if (inst) {
                inst.hide();
            } else {
                menu.classList.remove('is-open');
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.visibility = '';
            }
        });
    });

    // Tooltips: one shared bubble, following the element under the pointer.
    var tip = null;
    function hideTip() {
        if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
        tip = null;
    }
    document.addEventListener('mouseover', function (event) {
        var el = event.target.closest ? event.target.closest('[data-bs-toggle="tooltip"]') : null;
        if (!el || tip) return;
        var text = el.getAttribute('data-bs-title') || el.getAttribute('title') || '';
        if (!text) return;
        tip = document.createElement('div');
        tip.className = 'app-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.textContent = text;
        document.body.appendChild(tip);
        var r = el.getBoundingClientRect();
        var t = tip.getBoundingClientRect();
        var top = r.top - t.height - 8;
        if (top < 4) top = r.bottom + 8;
        var left = r.left + (r.width / 2) - (t.width / 2);
        left = Math.max(4, Math.min(left, window.innerWidth - t.width - 4));
        tip.style.top = top + 'px';
        tip.style.left = left + 'px';
    });
    document.addEventListener('mouseout', function (event) {
        if (event.target.closest && event.target.closest('[data-bs-toggle="tooltip"]')) hideTip();
    });
    window.addEventListener('scroll', hideTip, true);

    /* ------------------------------------------------------------------ expose */
    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = Modal;
    window.bootstrap.Tooltip = Tooltip;
    window.bootstrap.Dropdown = Dropdown;

    // jQuery bridge, for the pages that drive modals through $().modal('show').
    //
    // This cannot be a one-time `if (window.jQuery)` at load. jQuery is a classic script tag in
    // the page body, while this file is loaded from the shared top bar - so on
    // gasoline_purchase_order.php, inventory.php and spare_parts_inventory.php jQuery does not
    // exist yet when this runs, the check failed, and the bridge was never installed.
    // `$('#viewPOModal').modal('show')` then threw "modal is not a function" and the dialog never
    // opened. So the attach is deferred and re-attempted until jQuery shows up.
    function attachJQueryBridge() {
        var $ = window.jQuery;
        if (!$ || !$.fn || $.fn.modal) {
            return !!$;
        }
        $.fn.modal = function (action, relatedTarget) {
            var arg = action;
            return this.each(function () {
                var inst = Modal.getOrCreateInstance(this);
                if (arg === 'show') inst.show(relatedTarget);
                else if (arg === 'hide') inst.hide();
                else if (arg === 'toggle') inst.toggle(relatedTarget);
                else if (arg === 'dispose') inst.dispose();
                // No argument: jQuery semantics are to return the instance, but inside .each()
                // that is not expressible, so a no-arg call is treated as "show" for the
                // bootstrap-modal usage this app has.
                else inst.show(relatedTarget);
            });
        };
        $.fn.tooltip = function () {
            return this.each(function () { new Tooltip(this); });
        };
        $.fn.dropdown = function (action) {
            return this.each(function () {
                var inst = Dropdown.getOrCreateInstance(this);
                if (action === 'show') inst.show();
                else if (action === 'hide') inst.hide();
                else inst.toggle();
            });
        };
        $.fn.collapse = function (action) {
            // The sidebar collapses through app.js; this only exists so a stray call is harmless.
            return this.each(function () {
                if (action === 'hide') this.classList.remove('show');
                else if (action === 'show') this.classList.add('show');
                else this.classList.toggle('show');
            });
        };
        return true;
    }

    // Attach now if jQuery is already here, and otherwise keep looking.
    //
    // The poll alone is not reliable enough. jQuery is a classic script tag in the page body and
    // the page's own script runs immediately after it, so a handler registered on
    // $(document).ready() - which fires at once, the readyState already being interactive - can
    // call $('#modal').modal('show') BEFORE the first 50ms tick. That call finds no bridge and
    // throws, and the dialog never opens. So the interval stays as a backstop, and a page that
    // needs the bridge earlier calls window.ocpAttachJQueryBridge() first.
    window.ocpAttachJQueryBridge = attachJQueryBridge;
    if (!attachJQueryBridge()) {
        var bridgeTries = 0;
        var bridgeTimer = window.setInterval(function () {
            bridgeTries += 1;
            if (attachJQueryBridge() || bridgeTries > 200) {
                window.clearInterval(bridgeTimer);
            }
        }, 50);
    }

    // Collapse panels: Bootstrap's data-bs-toggle="collapse" drives the sidebar submenus.
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-bs-toggle="collapse"]');
        if (!toggle) return;
        event.preventDefault();
        var sel = toggle.getAttribute('data-bs-target') || toggle.getAttribute('href');
        if (!sel || sel.charAt(0) !== '#') return;
        var panel = document.querySelector(sel);
        if (!panel) return;
        var opening = !panel.classList.contains('show');
        panel.classList.toggle('show', opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        panel.style.display = opening ? 'block' : '';
        var arrow = toggle.querySelector('.collapse-arrow');
        if (arrow) arrow.style.transform = opening ? 'rotate(180deg)' : '';
    });
}());
