/**
 * Incentiv - front-end close buttons (previously inline onclick handlers).
 * [data-incv-hide="parent"] hides the button's parent element;
 * any other value is used as a selector for the element to hide.
 */
(function () {
    'use strict';
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-incv-hide]') : null;
        if (!button) {
            return;
        }
        var target = button.getAttribute('data-incv-hide');
        var el = 'parent' === target ? button.parentElement : document.querySelector(target);
        if (el) {
            el.style.display = 'none';
        }
    });
})();
