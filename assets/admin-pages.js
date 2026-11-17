/**
 * Incentiv - small admin page behaviors (delegated, so they survive PJAX swaps).
 * Previously inline <script> blocks in the docs, gift rule and rule set templates.
 */
jQuery(function ($) {
    'use strict';

    // Links and buttons that need a confirmation (e.g. delete rule). Runs in the
    // capture phase so a declined confirm stops the click before any other
    // handler (such as the PJAX link loader) sees it, as the old inline onclick did.
    document.addEventListener('click', function (event) {
        var el = event.target && event.target.closest ? event.target.closest('a[data-incv-confirm], button[data-incv-confirm]') : null;
        if (el && !window.confirm(el.getAttribute('data-incv-confirm'))) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    // Documentation: language switch.
    $(document).on('click', '.incv-docs-lang-btn', function () {
        var lang = $(this).data('lang');
        $('.incv-docs-lang-btn').removeClass('active');
        $(this).addClass('active');
        $('.incv-docs-content').hide();
        $('#incv-docs-' + lang).show();
    });

    // Gift rule editor: coupon codes row follows the "requires coupon" checkbox.
    $(document).on('change', '#coupon_required', function () {
        $('#required_coupon_codes_row').toggle($(this).is(':checked'));
    });

    // Rule set editor: sale price settings follow the "show sale price" checkbox.
    $(document).on('change', '#show_sale_price', function () {
        $('.incv-sale-price-setting').toggle($(this).is(':checked'));
    });
});
