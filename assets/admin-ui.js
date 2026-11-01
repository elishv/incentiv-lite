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

    var PJAX_PATTERN = /admin\.php\?page=(mix-match-discounts|mmd-)/;

    // Screens that ship their own footer-enqueued EXTERNAL JS (flow builder,
    // smart builder, and the special-events editor's admin-events.js + chart).
    // PJAX only swaps .mmd-admin-wrap and cannot pull in external <script src>
    // files, so these must full-load. Editors/wizards whose logic is INLINE
    // (rules, gifts, coupons + their wizards) run fine on swap and stay PJAX.
    var PJAX_EXCLUDE = /(mmd-flows|mmd-smart-builder|mmd-special-events&action|event_id=)/;

    function isPluginUrl(href) {
        return typeof href === 'string'
            && PJAX_PATTERN.test(href)
            && href.indexOf('admin-ajax.php') === -1
            && !PJAX_EXCLUDE.test(href);
    }

    // Interval registry so page-specific timers (autosave, draft) don't leak
    // when the same editor is loaded again via PJAX. Editors register with
    // window.mmdSetInterval; every registered timer is cleared before a swap.
    window.mmdPjaxIntervals = window.mmdPjaxIntervals || [];
    window.mmdSetInterval = function (fn, ms) {
        var id = setInterval(fn, ms);
        window.mmdPjaxIntervals.push(id);
        return id;
    };

    function pjaxTeardown() {
        window.mmdPjaxIntervals.forEach(clearInterval);
        window.mmdPjaxIntervals = [];
        // Drop page-scoped delegated/window handlers; they re-bind on the next
        // page's inline script. Global handlers here are un-namespaced and stay.
        $(document).off('.mmdpjax');
        $(window).off('.mmdpjax');
    }

    function pjaxLoad(url, push) {
        var $wrap = $('.mmd-admin-wrap');
        if (!$wrap.length) {
            window.location.href = url;
            return;
        }

        pjaxTeardown();
        $(document).trigger('mmd:pjax:before');
        $wrap.css({ opacity: .45, pointerEvents: 'none', transition: 'opacity .15s ease' });

        fetch(url, { credentials: 'same-origin', headers: { 'X-MMD-PJAX': '1' } })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector('.mmd-admin-wrap');
                if (!fresh) { throw new Error('no wrap'); }

                // Collect the wrap plus its trailing sibling scripts/styles that
                // templates print after the wrap (list-page inline handlers).
                var parts = [fresh.outerHTML];
                var sib = fresh.nextElementSibling;
                while (sib && (sib.tagName === 'SCRIPT' || sib.tagName === 'STYLE')) {
                    parts.push(sib.outerHTML);
                    sib = sib.nextElementSibling;
                }

                // Drop old trailing scripts/styles between the wrap and #wpfooter
                var old = document.querySelector('.mmd-admin-wrap');
                var next = old.nextElementSibling;
                while (next && (next.tagName === 'SCRIPT' || next.tagName === 'STYLE')) {
                    var toRemove = next;
                    next = next.nextElementSibling;
                    toRemove.remove();
                }

                var $new = $($.parseHTML(parts.join('\n'), document, true));
                $(old).replaceWith($new);

                if (doc.title) { document.title = doc.title; }
                if (push !== false) { history.pushState({ mmdPjax: true }, '', url); }
                window.scrollTo({ top: 0 });
                $(document).trigger('mmd:pjax:after');
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    // Expose a soft reload for action handlers (replaces location.reload())
    window.mmdPjaxReload = function () {
        pjaxLoad(window.location.href, false);
    };

    $(document).on('click', 'a', function (e) {
        if (e.isDefaultPrevented()) { return; }            // confirm() declined etc.
        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var href = this.getAttribute('href') || '';
        if (this.target && this.target !== '_self') { return; }
        if (!isPluginUrl(href)) { return; }
        if (!$('.mmd-admin-wrap').length) { return; }
        e.preventDefault();
        pjaxLoad(this.href);
    });

    window.addEventListener('popstate', function () {
        if ($('.mmd-admin-wrap').length && isPluginUrl(window.location.href)) {
            pjaxLoad(window.location.href, false);
        }
    });

    /* ======================================================================
     * DELEGATED UI BEHAVIORS (survive PJAX swaps)
     * ====================================================================== */

    // Global header search — client-side filter of list rows on the page
    $(document).on('input', '#mmd-global-search', function () {
        var q = $(this).val().toLowerCase().trim();
        var $rows = $('.mmd-tablecard-row, .mmd-rules-table tbody tr, .mmd-admin-wrap .wp-list-table tbody tr, .mmd-rule-card');
        $rows.each(function () {
            var text = $(this).text().toLowerCase();
            $(this).toggle(!q || text.indexOf(q) !== -1);
        });
    });

    // FAQ accordion (docs page)
    $(document).on('click', '.mmd-faq-q', function () {
        $(this).closest('.mmd-faq-item').toggleClass('open');
    });

    // Filter chips (rules list) — filter rows by data-mmd-status
    $(document).on('click', '.mmd-filter-chips .mmd-chip[data-filter]', function () {
        var filter = $(this).data('filter');
        $(this).addClass('mmd-chip-active').siblings().removeClass('mmd-chip-active');
        $('[data-mmd-status]').each(function () {
            var status = String($(this).data('mmd-status'));
            $(this).toggle(filter === 'all' || status === filter);
        });
    });

    // Rules list — play/pause a rule inline (no reload)
    $(document).on('click', '.mmd-toggle-rule', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) { return; }
        var ruleId = $btn.data('rule-id');
        $btn.prop('disabled', true);

        $.post((window.mmdUi && mmdUi.ajaxurl) || ajaxurl, {
            action: 'mmd_toggle_rule',
            nonce: (window.mmdUi && mmdUi.nonce) || '',
            rule_id: ruleId
        }).done(function (resp) {
            $btn.prop('disabled', false);
            if (!resp || !resp.success) { return; }
            var on = resp.data.enabled === 'yes';
            $btn.attr('data-enabled', resp.data.enabled)
                .attr('title', on ? __( 'Pause', 'incentiv' ) : __( 'Activate', 'incentiv' ))
                .toggleClass('mmd-icon-btn-active', on)
                .find('.msr').text(on ? 'pause' : 'play_arrow');

            // Update the status pill + row dimming in the same row
            var $row = $btn.closest('[data-mmd-status]');
            $row.attr('data-mmd-status', on ? 'active' : 'disabled')
                .toggleClass('mmd-row-disabled', !on);
            var $pill = $row.find('.mmd-pill').first();
            if ($pill.length) {
                $pill.attr('class', 'mmd-pill ' + (on ? 'mmd-pill-success' : 'mmd-pill-muted'))
                    .html('<span class="mmd-pill-dot"></span>' + (on ? __( 'Active', 'incentiv' ) : __( 'Disabled', 'incentiv' )));
            }
        }).fail(function () { $btn.prop('disabled', false); });
    });

    // Coupon editor — random code generator
    $(document).on('click', '#mmd-generate-coupon-code', function (e) {
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
        return $('.mmd-admin-container > .mmd-card.is-step-active[data-mmd-step]');
    }
    function cardIsOptional($card) {
        return $card.find('[required]').length === 0;
    }
    function mainNextBtn() { return $('#mmd-step-next:visible, #mmd-gift-step-next:visible').first(); }

    function refreshSubsteps() {
        var $active = substepActiveCards();
        $('.mmd-substep-bar').remove();
        if (!$active.length) { return; }

        // Reset position when the parent step changes (last card first if we
        // arrived by going back a step).
        var stepId = $active.first().attr('data-mmd-step');
        if (stepId !== substepStepId) {
            substepStepId = stepId;
            substepIdx = substepEnterAtLast ? ($active.length - 1) : 0;
        }
        substepEnterAtLast = false;

        if ($active.length < 2) { $active.removeClass('mmd-substep-hidden'); return; }
        if (substepIdx >= $active.length) { substepIdx = $active.length - 1; }
        if (substepIdx < 0) { substepIdx = 0; }

        var pills = '';
        $active.each(function (i) {
            var raw = ($(this).find('.mmd-card-title').first().text() || wp.i18n.sprintf( __( 'Card %d', 'incentiv' ), i + 1 )).trim();
            var state = substepRevealAll ? '' : (i === substepIdx ? ' active' : (i < substepIdx ? ' done' : ''));
            pills += '<button type="button" class="mmd-substep-pill' + state + '" data-i="' + i + '">' +
                '<span class="mmd-substep-num">' + (i + 1) + '</span>' + $('<i>').text(raw).html() + '</button>';
        });

        var optional = !substepRevealAll && cardIsOptional($active.eq(substepIdx));
        var pos = substepRevealAll ?
            /* translators: %d: number of cards in this step. */
            wp.i18n.sprintf( __( '%d cards', 'incentiv' ), $active.length ) :
            /* translators: 1: current card number, 2: number of cards in this step. */
            wp.i18n.sprintf( __( 'Card %1$d of %2$d', 'incentiv' ), substepIdx + 1, $active.length );

        var bar =
            '<div class="mmd-substep-bar">' +
            '<div class="mmd-substep-lead"><span class="msr">list_alt</span>' + pos + '</div>' +
            '<div class="mmd-substep-pills">' + pills + '</div>' +
            '<div class="mmd-substep-actions">' +
            (optional ? ( '<button type="button" class="mmd-substep-skip" title="' + __( 'Skip to the next card', 'incentiv' ) + '">' + __( 'Skip', 'incentiv' ) + ' <span class="msr">skip_next</span></button>' ) : '') +
            ( '<label class="mmd-substep-toggle" title="' + __( 'Show all cards together', 'incentiv' ) + '"><input type="checkbox" class="mmd-substep-revealall"' ) + (substepRevealAll ? ' checked' : '') + ( '> ' + __( 'Show all', 'incentiv' ) + '</label>' ) +
            '</div>' +
            '</div>';
        $active.first().before(bar);

        if (substepRevealAll) {
            $active.removeClass('mmd-substep-hidden');
        } else {
            $active.each(function (i) { $(this).toggleClass('mmd-substep-hidden', i !== substepIdx); });
        }
    }

    $(document).on('mmd:substep', function () { setTimeout(refreshSubsteps, 0); });
    // A required field can live in a sub-hidden card; reveal all on validation
    // failure so the native error is visible and submission isn't silently blocked.
    document.addEventListener('invalid', function () {
        if (!substepRevealAll && substepActiveCards().length > 1) {
            substepRevealAll = true;
            setTimeout(refreshSubsteps, 0);
        }
    }, true);
    $(document).on('click', '.mmd-substep-pill', function () {
        substepRevealAll = false;
        substepIdx = parseInt($(this).data('i'), 10) || 0;
        refreshSubsteps();
    });
    $(document).on('change', '.mmd-substep-revealall', function () {
        substepRevealAll = $(this).is(':checked');
        refreshSubsteps();
    });
    // Skip an optional card — same as pressing Next
    $(document).on('click', '.mmd-substep-skip', function () {
        var b = mainNextBtn();
        if (b.length) { b.trigger('click'); }
    });

    // The main Prev/Next walk the sub-cards first, then change step. Bound
    // before the editors' own handlers (this script loads first) so we can
    // stop the step change while sub-cards remain.
    $(document).on('click', '#mmd-step-next, #mmd-gift-step-next', function (e) {
        var $active = substepActiveCards();
        if (!substepRevealAll && $active.length > 1 && substepIdx < $active.length - 1) {
            e.stopImmediatePropagation();
            e.preventDefault();
            substepIdx++;
            refreshSubsteps();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    $(document).on('click', '#mmd-step-prev, #mmd-gift-step-prev', function (e) {
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
    $(document).on('click', '.mmd-mergetag-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var $open = $btn.siblings('.mmd-mergetag-menu');
        $('.mmd-mergetag-menu').not($open).remove();
        if ($open.length) { $open.remove(); return; }

        var target = $btn.data('target');
        var tags = String($btn.data('tags') || '').split(',');
        var html = '<div class="mmd-mergetag-menu">';
        tags.forEach(function (t) {
            t = t.trim();
            if (!t) { return; }
            html += '<button type="button" class="mmd-mergetag-item" data-tag="' + t + '" data-target="' + target + '">' +
                '<code>{' + t + '}</code><span>' + (MERGE_TAG_LABELS[t] || t) + '</span></button>';
        });
        html += '</div>';
        $btn.after(html);
    });

    // Insert the chosen tag
    $(document).on('click', '.mmd-mergetag-item', function (e) {
        e.preventDefault();
        var $target = $('#' + $(this).data('target'));
        insertAtCursor($target[0], '{' + $(this).data('tag') + '}');
        $target.trigger('input').trigger('change');
        $(this).closest('.mmd-mergetag-menu').remove();
    });

    // Close the menu on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.mmd-mergetag-btn, .mmd-mergetag-menu').length) {
            $('.mmd-mergetag-menu').remove();
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
        var $preview = $('#mmd-live-preview');
        if (!$preview.length) { return; }

        var name = $('#rule_name').val() || __( 'My rule', 'incentiv' );
        $preview.find('.mmd-lp-rule-name').text(name);

        var $tbody = $preview.find('.mmd-lp-tiers-body');
        var rows = [];
        $('#discount_rules_container .mmd-discount-rule').each(function () {
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
                var $row = $('<div class="mmd-preview-tier-row' + (best ? ' mmd-tier-best' : '') + '">')
                    .append($('<span class="mmd-tier-qty">').text(row.qty + ( ' ' + __( 'units', 'incentiv' ) )))
                    .append($('<span class="mmd-tier-val">').text(tierLabel(row.disc, row.type)));
                $tbody.append($row);
            });
            $preview.find('.mmd-lp-tiers-wrap').show();
        } else {
            $preview.find('.mmd-lp-tiers-wrap').hide();
        }

        var badgeOn = $('#badge_enabled').is(':checked');
        var badgeText = $('#badge_text').val() || __( 'Promotion', 'incentiv' );
        var badgeBg = $('#badge_bg_color').val() || '#e53935';
        var badgeColor = $('#badge_text_color').val() || '#ffffff';
        $preview.find('.mmd-preview-badge')
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
        var $box = $preview.find('.mmd-lp-cartmsg-box').css('background', noticeColor).html('');
        if (noticeTitle && noticeContent) {
            $box.append($('<strong style="display:block;margin-bottom:3px;">').text(noticeTitle))
                .append($('<span>').text(noticeContent));
        } else {
            $box.text(noticeText || __( 'Buy more, save more!', 'incentiv' ));
        }
        $preview.find('.mmd-lp-cartmsg').toggle(noticeOn && !!noticeText);
    }

    $(document).on(
        'input change',
        '#rule_name, #notice_title, #notice_content, #notice_color, ' +
        '#badge_enabled, #badge_text, #badge_bg_color, #badge_text_color, ' +
        '#notice_enabled, #discount_rules_container input, #discount_rules_container select',
        refreshPreview
    );
    $(document).on('click', '.mmd-remove-rule, #add_discount_rule, .mmd-preset-btn, .mmd-global-preset-btn, #mmd-wizard-finish', function () {
        setTimeout(refreshPreview, 80);
    });

    // Gift editor — mirror rule name into the preview
    $(document).on('input change', 'input[name="rule_name"]', function () {
        $('#mmd-gift-live-preview .mmd-glp-rule-name').text($(this).val() || '');
    });

    /* ======================================================================
     * INIT (on load + after every PJAX swap)
     * ====================================================================== */
    function init() {
        refreshPreview();
    }

    $(init);
    $(document).on('mmd:pjax:after', init);
})(jQuery);
