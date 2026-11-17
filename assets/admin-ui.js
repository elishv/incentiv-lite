/**
 * Incentiv — shared admin UI behaviors
 * PJAX navigation (no full page loads inside the plugin), header search,
 * FAQ accordion, filter chips, live previews, coupon code generator.
 */
(function ($) {
    'use strict';
    var __ = wp.i18n.__;

    /* ======================================================================
     * PJAX NAVIGATION
     * Any link between Incentiv admin screens swaps the content area
     * in-place (with the design's rise animation) instead of reloading.
     * ====================================================================== */

    var PJAX_PATTERN = /admin\.php\?page=(incv-discount-rules|incv-)/;

    // Screens that ship their own footer-enqueued EXTERNAL JS (flow builder,
    // smart builder, and the special-events editor's admin-events.js + chart).
    // PJAX only swaps .incv-admin-wrap and cannot pull in external <script src>
    // files, so these must full-load. Editors/wizards whose logic is INLINE
    // (rules, gifts, coupons + their wizards) run fine on swap and stay PJAX.
    var PJAX_EXCLUDE = /(incv-flows|incv-smart-builder|incv-special-events&action|event_id=)/;

    function isPluginUrl(href) {
        return typeof href === 'string'
            && PJAX_PATTERN.test(href)
            && href.indexOf('admin-ajax.php') === -1
            && !PJAX_EXCLUDE.test(href);
    }

    // Interval registry so page-specific timers (autosave, draft) don't leak
    // when the same editor is loaded again via PJAX. Editors register with
    // window.incvSetInterval; every registered timer is cleared before a swap.
    window.incvPjaxIntervals = window.incvPjaxIntervals || [];
    window.incvSetInterval = function (fn, ms) {
        var id = setInterval(fn, ms);
        window.incvPjaxIntervals.push(id);
        return id;
    };

    function pjaxTeardown() {
        window.incvPjaxIntervals.forEach(clearInterval);
        window.incvPjaxIntervals = [];
        // Drop page-scoped delegated/window handlers; they re-bind on the next
        // page's inline script. Global handlers here are un-namespaced and stay.
        $(document).off('.incvpjax');
        $(window).off('.incvpjax');
    }

    function pjaxLoad(url, push) {
        var $wrap = $('.incv-admin-wrap');
        if (!$wrap.length) {
            window.location.href = url;
            return;
        }

        pjaxTeardown();
        $(document).trigger('incv:pjax:before');
        $wrap.css({ opacity: .45, pointerEvents: 'none', transition: 'opacity .15s ease' });

        fetch(url, { credentials: 'same-origin', headers: { 'X-INCV-PJAX': '1' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector('.incv-admin-wrap');
                if (!fresh) { throw new Error('no wrap'); }

                // Collect the wrap plus any trailing sibling scripts/styles.
                var parts = [fresh.outerHTML];
                var sib = fresh.nextElementSibling;
                while (sib && (sib.tagName === 'SCRIPT' || sib.tagName === 'STYLE')) {
                    parts.push(sib.outerHTML);
                    sib = sib.nextElementSibling;
                }

                // Drop old trailing scripts/styles between the wrap and #wpfooter
                var old = document.querySelector('.incv-admin-wrap');
                var next = old.nextElementSibling;
                while (next && (next.tagName === 'SCRIPT' || next.tagName === 'STYLE')) {
                    var toRemove = next;
                    next = next.nextElementSibling;
                    toRemove.remove();
                }

                var $new = $($.parseHTML(parts.join('\n'), document, true));
                $(old).replaceWith($new);

                // Page-specific CSS/JS is queued through wp_add_inline_style() /
                // wp_add_inline_script() (handles incv-inline-css-* / incv-inline-N),
                // so WordPress prints it in <head>/footer rather than inside the wrap.
                // Swap those styles and run the new page's inline scripts.
                $('style[id^="incv-inline-css-"]').remove();
                doc.querySelectorAll('style[id^="incv-inline-css-"]').forEach(function (st) {
                    document.head.appendChild(document.importNode(st, true));
                });
                $('script[id^="incv-inline-"][id$="-js-after"]').remove();
                doc.querySelectorAll('script[id^="incv-inline-"][id$="-js-after"]').forEach(function (sc) {
                    var run = document.createElement('script');
                    run.id = sc.id;
                    run.text = sc.textContent;
                    document.body.appendChild(run);
                });

                if (doc.title) { document.title = doc.title; }
                if (push !== false) { history.pushState({ incvPjax: true }, '', url); }
                window.scrollTo({ top: 0 });
                $(document).trigger('incv:pjax:after');
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    // Expose a soft reload for action handlers (replaces location.reload())
    window.incvPjaxReload = function () {
        pjaxLoad(window.location.href, false);
    };

    $(document).on('click', 'a', function (e) {
        if (e.isDefaultPrevented()) { return; }            // confirm() declined etc.
        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var href = this.getAttribute('href') || '';
        if (this.target && this.target !== '_self') { return; }
        if (!isPluginUrl(href)) { return; }
        if (!$('.incv-admin-wrap').length) { return; }
        e.preventDefault();
        pjaxLoad(this.href);
    });

    window.addEventListener('popstate', function () {
        if ($('.incv-admin-wrap').length && isPluginUrl(window.location.href)) {
            pjaxLoad(window.location.href, false);
        }
    });

    /* ======================================================================
     * DELEGATED UI BEHAVIORS (survive PJAX swaps)
     * ====================================================================== */

    // Global header search — client-side filter of list rows on the page
    $(document).on('input', '#incv-global-search', function () {
        var q = $(this).val().toLowerCase().trim();
        var $rows = $('.incv-tablecard-row, .incv-rules-table tbody tr, .incv-admin-wrap .wp-list-table tbody tr, .incv-rule-card');
        $rows.each(function () {
            var text = $(this).text().toLowerCase();
            $(this).toggle(!q || text.indexOf(q) !== -1);
        });
    });

    // FAQ accordion (docs page)
    $(document).on('click', '.incv-faq-q', function () {
        $(this).closest('.incv-faq-item').toggleClass('open');
    });

    // Filter chips (rules list) — filter rows by data-incv-status
    $(document).on('click', '.incv-filter-chips .incv-chip[data-filter]', function () {
        var filter = $(this).data('filter');
        $(this).addClass('incv-chip-active').siblings().removeClass('incv-chip-active');
        $('[data-incv-status]').each(function () {
            var status = String($(this).data('incv-status'));
            $(this).toggle(filter === 'all' || status === filter);
        });
    });

    // Rules list — play/pause a rule inline (no reload)
    $(document).on('click', '.incv-toggle-rule', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) { return; }
        var ruleId = $btn.data('rule-id');
        $btn.prop('disabled', true);

        $.post((window.incvUi && incvUi.ajaxurl) || ajaxurl, {
            action: 'incv_toggle_rule',
            nonce: (window.incvUi && incvUi.nonce) || '',
            rule_id: ruleId
        }).done(function (resp) {
            $btn.prop('disabled', false);
            if (!resp || !resp.success) { return; }
            var on = resp.data.enabled === 'yes';
            $btn.attr('data-enabled', resp.data.enabled)
                .attr('title', on ? __( 'Pause', 'incentiv' ) : __( 'Activate', 'incentiv' ))
                .toggleClass('incv-icon-btn-active', on)
                .find('.msr').text(on ? 'pause' : 'play_arrow');

            // Update the status pill + row dimming in the same row
            var $row = $btn.closest('[data-incv-status]');
            $row.attr('data-incv-status', on ? 'active' : 'disabled')
                .toggleClass('incv-row-disabled', !on);
            var $pill = $row.find('.incv-pill').first();
            if ($pill.length) {
                $pill.attr('class', 'incv-pill ' + (on ? 'incv-pill-success' : 'incv-pill-muted'))
                    .html('<span class="incv-pill-dot"></span>' + (on ? __( 'Active', 'incentiv' ) : __( 'Disabled', 'incentiv' )));
            }
        }).fail(function () { $btn.prop('disabled', false); });
    });

    // Coupon editor — random code generator
    $(document).on('click', '#incv-generate-coupon-code', function (e) {
        e.preventDefault();
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var code = '';
        for (var i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#coupon_code').val(code).trigger('change');
    });

    /* ======================================================================
     * SUB-STEPS — when a stepper step holds several cards, walk them one at a
     * time with the main Prev/Next, or reveal them all at once for power users.
     * ====================================================================== */
    var substepRevealAll = false;
    var substepIdx = 0;
    var substepStepId = null;
    var substepEnterAtLast = false;

    function substepActiveCards() {
        return $('.incv-admin-container > .incv-card.is-step-active[data-incv-step]');
    }
    function cardIsOptional($card) {
        return $card.find('[required]').length === 0;
    }
    function mainNextBtn() { return $('#incv-step-next:visible, #incv-gift-step-next:visible').first(); }

    function refreshSubsteps() {
        var $active = substepActiveCards();
        $('.incv-substep-bar').remove();
        if (!$active.length) { return; }

        // Reset position when the parent step changes (last card first if we
        // arrived by going back a step).
        var stepId = $active.first().attr('data-incv-step');
        if (stepId !== substepStepId) {
            substepStepId = stepId;
            substepIdx = substepEnterAtLast ? ($active.length - 1) : 0;
        }
        substepEnterAtLast = false;

        if ($active.length < 2) { $active.removeClass('incv-substep-hidden'); return; }
        if (substepIdx >= $active.length) { substepIdx = $active.length - 1; }
        if (substepIdx < 0) { substepIdx = 0; }

        var pills = '';
        $active.each(function (i) {
            var raw = ($(this).find('.incv-card-title').first().text() || wp.i18n.sprintf( __( 'Card %d', 'incentiv' ), i + 1 )).trim();
            var state = substepRevealAll ? '' : (i === substepIdx ? ' active' : (i < substepIdx ? ' done' : ''));
            pills += '<button type="button" class="incv-substep-pill' + state + '" data-i="' + i + '">' +
                '<span class="incv-substep-num">' + (i + 1) + '</span>' + $('<i>').text(raw).html() + '</button>';
        });

        var optional = !substepRevealAll && cardIsOptional($active.eq(substepIdx));
        var pos = substepRevealAll ?
            /* translators: %d: number of cards in this step. */
            wp.i18n.sprintf( __( '%d cards', 'incentiv' ), $active.length ) :
            /* translators: 1: current card number, 2: number of cards in this step. */
            wp.i18n.sprintf( __( 'Card %1$d of %2$d', 'incentiv' ), substepIdx + 1, $active.length );

        var bar =
            '<div class="incv-substep-bar">' +
            '<div class="incv-substep-lead"><span class="msr">list_alt</span>' + pos + '</div>' +
            '<div class="incv-substep-pills">' + pills + '</div>' +
            '<div class="incv-substep-actions">' +
            (optional ? ( '<button type="button" class="incv-substep-skip" title="' + __( 'Skip to the next card', 'incentiv' ) + '">' + __( 'Skip', 'incentiv' ) + ' <span class="msr">skip_next</span></button>' ) : '') +
            ( '<label class="incv-substep-toggle" title="' + __( 'Show all cards together', 'incentiv' ) + '"><input type="checkbox" class="incv-substep-revealall"' ) + (substepRevealAll ? ' checked' : '') + ( '> ' + __( 'Show all', 'incentiv' ) + '</label>' ) +
            '</div>' +
            '</div>';
        $active.first().before(bar);

        if (substepRevealAll) {
            $active.removeClass('incv-substep-hidden');
        } else {
            $active.each(function (i) { $(this).toggleClass('incv-substep-hidden', i !== substepIdx); });
        }
    }

    $(document).on('incv:substep', function () { setTimeout(refreshSubsteps, 0); });
    // A required field can live in a sub-hidden card; reveal all on validation
    // failure so the native error is visible and submission isn't silently blocked.
    document.addEventListener('invalid', function () {
        if (!substepRevealAll && substepActiveCards().length > 1) {
            substepRevealAll = true;
            setTimeout(refreshSubsteps, 0);
        }
    }, true);
    $(document).on('click', '.incv-substep-pill', function () {
        substepRevealAll = false;
        substepIdx = parseInt($(this).data('i'), 10) || 0;
        refreshSubsteps();
    });
    $(document).on('change', '.incv-substep-revealall', function () {
        substepRevealAll = $(this).is(':checked');
        refreshSubsteps();
    });
    // Skip an optional card — same as pressing Next
    $(document).on('click', '.incv-substep-skip', function () {
        var b = mainNextBtn();
        if (b.length) { b.trigger('click'); }
    });

    // The main Prev/Next walk the sub-cards first, then change step. Bound
    // before the editors' own handlers (this script loads first) so we can
    // stop the step change while sub-cards remain.
    $(document).on('click', '#incv-step-next, #incv-gift-step-next', function (e) {
        var $active = substepActiveCards();
        if (!substepRevealAll && $active.length > 1 && substepIdx < $active.length - 1) {
            e.stopImmediatePropagation();
            e.preventDefault();
            substepIdx++;
            refreshSubsteps();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    $(document).on('click', '#incv-step-prev, #incv-gift-step-prev', function (e) {
        var $active = substepActiveCards();
        if (!substepRevealAll && $active.length > 1 && substepIdx > 0) {
            e.stopImmediatePropagation();
            e.preventDefault();
            substepIdx--;
            refreshSubsteps();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else if (!substepRevealAll) {
            // Falling back to the previous step — land on its last card.
            substepEnterAtLast = true;
        }
    });

    /* ======================================================================
     * MERGE TAGS — insert {tokens} into supported text fields at the cursor
     * ====================================================================== */
    var MERGE_TAG_LABELS = {
        gift_type: __( 'Gift type', 'incentiv' ),
        selection_type: __( 'Selection type (product/variation)', 'incentiv' ),
        rule_name: __( 'Rule name', 'incentiv' )
    };

    function insertAtCursor(el, text) {
        if (!el) { return; }
        el.focus();
        if (typeof el.selectionStart === 'number') {
            var s = el.selectionStart, e = el.selectionEnd;
            el.value = el.value.slice(0, s) + text + el.value.slice(e);
            el.selectionStart = el.selectionEnd = s + text.length;
        } else {
            el.value += text;
        }
    }

    // Toggle the tag menu
    $(document).on('click', '.incv-mergetag-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var $open = $btn.siblings('.incv-mergetag-menu');
        $('.incv-mergetag-menu').not($open).remove();
        if ($open.length) { $open.remove(); return; }

        var target = $btn.data('target');
        var tags = String($btn.data('tags') || '').split(',');
        var html = '<div class="incv-mergetag-menu">';
        tags.forEach(function (t) {
            t = t.trim();
            if (!t) { return; }
            html += '<button type="button" class="incv-mergetag-item" data-tag="' + t + '" data-target="' + target + '">' +
                '<code>{' + t + '}</code><span>' + (MERGE_TAG_LABELS[t] || t) + '</span></button>';
        });
        html += '</div>';
        $btn.after(html);
    });

    // Insert the chosen tag
    $(document).on('click', '.incv-mergetag-item', function (e) {
        e.preventDefault();
        var $target = $('#' + $(this).data('target'));
        insertAtCursor($target[0], '{' + $(this).data('tag') + '}');
        $target.trigger('input').trigger('change');
        $(this).closest('.incv-mergetag-menu').remove();
    });

    // Close the menu on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.incv-mergetag-btn, .incv-mergetag-menu').length) {
            $('.incv-mergetag-menu').remove();
        }
    });

    /* ======================================================================
     * RULE EDITOR — sticky live preview
     * ====================================================================== */

    function tierLabel(discount, type) {
        var d = parseFloat(discount);
        if (isNaN(d)) { return ''; }
        if (type === 'fixed') { return __( 'A discount of ₪', 'incentiv' ) + d + ( ' ' + __( 'on the cheapest', 'incentiv' ) ); }
        if (d >= 100) { return __( 'Cheapest is free! 🎉', 'incentiv' ); }
        /* translators: %s: discount percentage. */
        return wp.i18n.sprintf( __( 'the cheapest at %s%% off', 'incentiv' ), d );
    }

    function refreshPreview() {
        var $preview = $('#incv-live-preview');
        if (!$preview.length) { return; }

        var name = $('#rule_name').val() || __( 'My rule', 'incentiv' );
        $preview.find('.incv-lp-rule-name').text(name);

        var $tbody = $preview.find('.incv-lp-tiers-body');
        var rows = [];
        $('#discount_rules_container .incv-discount-rule').each(function () {
            var qty = $(this).find('input[name*="[quantity]"]').val();
            var disc = $(this).find('input[name*="[discount]"]').val();
            var type = $(this).find('select[name*="[type]"]').val() || 'percentage';
            if (qty && disc !== '') {
                rows.push({ qty: parseInt(qty, 10), disc: parseFloat(disc), type: type });
            }
        });
        rows.sort(function (a, b) { return a.qty - b.qty; });

        if (rows.length) {
            $tbody.empty();
            $.each(rows, function (i, row) {
                var best = (i === rows.length - 1);
                var $row = $('<div class="incv-preview-tier-row' + (best ? ' incv-tier-best' : '') + '">')
                    .append($('<span class="incv-tier-qty">').text(row.qty + ( ' ' + __( 'units', 'incentiv' ) )))
                    .append($('<span class="incv-tier-val">').text(tierLabel(row.disc, row.type)));
                $tbody.append($row);
            });
            $preview.find('.incv-lp-tiers-wrap').show();
        } else {
            $preview.find('.incv-lp-tiers-wrap').hide();
        }

        var badgeOn = $('#badge_enabled').is(':checked');
        var badgeText = $('#badge_text').val() || __( 'Promotion', 'incentiv' );
        var badgeBg = $('#badge_bg_color').val() || '#e53935';
        var badgeColor = $('#badge_text_color').val() || '#ffffff';
        $preview.find('.incv-preview-badge')
            .toggle(badgeOn)
            .text(badgeText)
            .css({ background: badgeBg, color: badgeColor });

        var noticeOn = $('#notice_enabled').is(':checked');
        var noticeTitle = $('#notice_title').val() || '';
        var noticeContent = $('#notice_content').val() || '';
        var noticeColor = $('#notice_color').val() || '#a16a3f';
        var noticeText = noticeContent || noticeTitle;
        if (!noticeText && rows.length) {
            noticeText = ( __( 'Buy', 'incentiv' ) + ' ' ) + rows[0].qty + ( ' ' + __( 'units and get', 'incentiv' ) + ' ' ) + tierLabel(rows[0].disc, rows[0].type);
        }
        var $box = $preview.find('.incv-lp-cartmsg-box').css('background', noticeColor).html('');
        if (noticeTitle && noticeContent) {
            $box.append($('<strong style="display:block;margin-bottom:3px;">').text(noticeTitle))
                .append($('<span>').text(noticeContent));
        } else {
            $box.text(noticeText || __( 'Buy more, save more!', 'incentiv' ));
        }
        $preview.find('.incv-lp-cartmsg').toggle(noticeOn && !!noticeText);
    }

    $(document).on(
        'input change',
        '#rule_name, #notice_title, #notice_content, #notice_color, ' +
        '#badge_enabled, #badge_text, #badge_bg_color, #badge_text_color, ' +
        '#notice_enabled, #discount_rules_container input, #discount_rules_container select',
        refreshPreview
    );
    $(document).on('click', '.incv-remove-rule, #add_discount_rule, .incv-preset-btn, .incv-global-preset-btn, #incv-wizard-finish', function () {
        setTimeout(refreshPreview, 80);
    });

    // Gift editor — mirror rule name into the preview
    $(document).on('input change', 'input[name="rule_name"]', function () {
        $('#incv-gift-live-preview .incv-glp-rule-name').text($(this).val() || '');
    });

    /* ======================================================================
     * INIT (on load + after every PJAX swap)
     * ====================================================================== */
    function init() {
        refreshPreview();
    }

    $(init);
    $(document).on('incv:pjax:after', init);
})(jQuery);
