/**
 * Incentiv — shared editor autosave / draft recovery.
 *
 * Any editor form/container tagged with data-mmd-autosave="<scope>" (and an
 * optional data-mmd-record-id) is snapshotted to localStorage as the user
 * works. If an unsaved draft is found on load, a banner offers to restore it.
 * Dynamic tier rows (discount rules, event quantity tiers) are rebuilt
 * positionally so nothing is lost. Drafts clear on save.
 */
(function ($) {
    'use strict';
    var __ = wp.i18n.__;

    // Dynamic repeatable-row groups: rebuilt by count + filled positionally.
    var DYN_GROUPS = [
        { btn: '#add_discount_rule', rowSel: '#discount_rules_container .mmd-discount-rule', prefix: 'discount_rules' },
        { btn: '.add-discount-row', rowSel: '.mmd-discount-tiers tr:has(input[name^="quantity_discounts"])', prefix: 'quantity_discounts' }
    ];

    function debounce(fn, wait) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, wait); };
    }

    function init() {
        var $root = $('[data-mmd-autosave]').first();
        if (!$root.length || $root.data('mmdAutosaveBound')) { return; }
        $root.data('mmdAutosaveBound', true);

        var scope = String($root.data('mmd-autosave') || 'form');
        var recId = String($root.data('mmd-record-id') || '').trim() || 'new';
        var KEY = 'mmd_autosave_' + scope + '_' + recId;
        var INTERVAL = 4000;
        var submitting = false;

        function $fields() {
            return $root.find('input, select, textarea').filter(function () {
                return this.name && this.type !== 'file' && this.type !== 'password';
            });
        }

        function serialize() {
            var data = {};
            $fields().each(function () {
                var $el = $(this), name = this.name;
                if (($el.is(':checkbox') || $el.is(':radio')) && !$el.is(':checked')) { return; }
                var val = $el.is('select[multiple]') ? ($el.val() || []) : $el.val();
                if (Object.prototype.hasOwnProperty.call(data, name)) {
                    if (!Array.isArray(data[name])) { data[name] = [data[name]]; }
                    data[name].push(val);
                } else {
                    data[name] = val;
                }
            });
            return { t: Date.now(), url: location.href, fields: data };
        }

        // Group flat field map into ordered rows for a repeatable prefix.
        function draftRows(data, prefix) {
            var re = new RegExp('^' + prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[(\\d+)\\]\\[(.+)\\]$');
            var byIdx = {};
            Object.keys(data).forEach(function (name) {
                var m = name.match(re);
                if (!m) { return; }
                var idx = parseInt(m[1], 10);
                (byIdx[idx] = byIdx[idx] || {})[m[2]] = data[name];
            });
            return Object.keys(byIdx).sort(function (a, b) { return a - b; }).map(function (k) { return byIdx[k]; });
        }

        function restore(snapshot) {
            var data = (snapshot && snapshot.fields) || {};
            var handledPrefixes = [];

            // 1) Rebuild + fill dynamic groups positionally
            DYN_GROUPS.forEach(function (g) {
                if (!$(g.btn).length) { return; }
                var rows = draftRows(data, g.prefix);
                if (!rows.length) { return; }
                handledPrefixes.push(g.prefix);
                var guard = 0;
                while ($(g.rowSel).length < rows.length && guard++ < 40) { $(g.btn).trigger('click'); }
                var $rows = $(g.rowSel);
                rows.forEach(function (row, i) {
                    var $row = $rows.eq(i);
                    Object.keys(row).forEach(function (sub) {
                        var $f = $row.find('[name$="[' + sub + ']"]').first();
                        if (!$f.length) { return; }
                        if ($f.is(':checkbox')) { $f.prop('checked', !!row[sub]); }
                        else { $f.val(row[sub]); }
                        $f.trigger('change');
                    });
                });
            });

            // 2) Restore the remaining scalar fields
            $fields().each(function () {
                var $el = $(this), name = this.name;
                if (!Object.prototype.hasOwnProperty.call(data, name)) { return; }
                if (handledPrefixes.some(function (p) { return name.indexOf(p + '[') === 0; })) { return; }
                var val = data[name];
                if ($el.is(':checkbox')) {
                    $el.prop('checked', Array.isArray(val) ? val.indexOf($el.val()) !== -1 : val === $el.val());
                } else if ($el.is(':radio')) {
                    if ($el.val() === (Array.isArray(val) ? val[0] : val)) { $el.prop('checked', true); }
                } else if ($el.is('select[multiple]')) {
                    $el.val(Array.isArray(val) ? val : [val]);
                } else {
                    $el.val(Array.isArray(val) ? val[0] : val);
                }
            });

            // 3) Nudge dependent UI (conditional fields, previews, select2)
            $fields().filter('select, :checkbox, :radio').trigger('change');
            $fields().filter('input:not(:checkbox):not(:radio), textarea').trigger('input');
        }

        function save() {
            if (submitting) { return; }
            // When an editor persists real server-side drafts it sets this flag,
            // so the localStorage safety net stands down to avoid a competing draft.
            if ($root.data('mmdServerDraftActive')) { return; }
            try { localStorage.setItem(KEY, JSON.stringify(serialize())); } catch (e) { /* full/unavailable */ }
        }
        function clear() { try { localStorage.removeItem(KEY); } catch (e) {} }
        window.mmdClearAutosave = clear;

        function showBanner(snapshot) {
            var age = Date.now() - (snapshot.t || 0);
            if (age > 86400000) { clear(); return; }               // ignore >24h
            var mins = Math.round(age / 60000);
            var ago = mins < 1 ? __( 'less than a minute', 'incentiv' ) : (mins === 1 ? __( 'a minute', 'incentiv' ) : mins + ( ' ' + __( 'minutes', 'incentiv' ) ));

            var $b = $(
                '<div class="mmd-autosave-banner">' +
                '<span class="msr">history</span>' +
                ( '<span class="mmd-autosave-text">' + __( 'An unsaved draft was found (from', 'incentiv' ) + ' ' ) + ago + ( __( 'ago). Restore your work?', 'incentiv' ) + '</span>' ) +
                ( '<button type="button" class="mmd-btn mmd-btn-brand mmd-restore-draft"><span class="msr">restore</span>' + __( 'Restore', 'incentiv' ) + '</button>' ) +
                ( '<button type="button" class="mmd-btn mmd-btn-outline mmd-discard-draft">' + __( 'Dismiss', 'incentiv' ) + '</button>' ) +
                '</div>'
            );
            $root.before($b);

            $b.on('click', '.mmd-restore-draft', function () {
                restore(snapshot);
                $b.slideUp(180, function () { $b.remove(); });
            });
            $b.on('click', '.mmd-discard-draft', function () {
                clear();
                $b.slideUp(180, function () { $b.remove(); });
            });
        }

        // Check for a draft on load (slight delay so the editor JS can init rows)
        setTimeout(function () {
            var raw;
            try { raw = localStorage.getItem(KEY); } catch (e) { return; }
            if (!raw) { return; }
            try {
                var snap = JSON.parse(raw);
                if (snap && snap.t) { showBanner(snap); }
            } catch (e) { clear(); }
        }, 550);

        // Save triggers: debounced on edit + steady interval (registered so it
        // is cleared before a PJAX swap instead of leaking).
        $root.on('input change', 'input, select, textarea', debounce(save, 700));
        var timer = (window.mmdSetInterval || setInterval)(save, INTERVAL);

        // Clear on save. Native submit (rule/event) fires submit; AJAX saves
        // (gift) clear on the save click — the interval re-saves within a few
        // seconds if the save actually failed, so nothing is lost.
        $root.on('submit', function () { submitting = true; clearInterval(timer); clear(); });
        $(document).on('click.mmdpjax', '#save-gift-rule, #save-rule-set', function () { setTimeout(clear, 1200); });
    }

    $(init);
    $(document).on('mmd:pjax:after', init);
})(jQuery);
