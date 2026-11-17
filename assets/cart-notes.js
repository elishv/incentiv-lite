/**
 * Cart Notes JavaScript
 * Handles auto-save of cart notes
 */

(function($) {
    'use strict';

    var cartNoteTimeout;

    $(document).ready(function() {
        // Auto-save cart note on change
        $('#incv-cart-note').on('input', function() {
            clearTimeout(cartNoteTimeout);

            cartNoteTimeout = setTimeout(function() {
                saveCartNote();
            }, 1000); // Save after 1 second of no typing
        });

        // Save on blur (when field loses focus)
        $('#incv-cart-note').on('blur', function() {
            clearTimeout(cartNoteTimeout);
            saveCartNote();
        });
    });

    function saveCartNote() {
        var cartNote = $('#incv-cart-note').val();

        $.ajax({
            url: incvCartNotes.ajaxurl,
            type: 'POST',
            data: {
                action: 'incv_save_cart_note',
                nonce: incvCartNotes.nonce,
                cart_note: cartNote
            },
            success: function(response) {
            }
        });
    }

})(jQuery);
