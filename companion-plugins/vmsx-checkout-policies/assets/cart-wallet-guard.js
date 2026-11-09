(function () {
    'use strict';

    var selector = [
        '.wc-block-components-express-payment--cart',
        '.wc-block-components-express-payment',
        '.wp-block-woocommerce-cart-express-payment-block',
        '#wc-square-digital-wallet',
        '.wc-square-wallet-buttons'
    ].join(',');

    function suppressExpressPayments() {
        document.querySelectorAll(selector).forEach(function (node) {
            node.hidden = true;
            node.style.setProperty('display', 'none', 'important');
            node.setAttribute('aria-hidden', 'true');
            node.setAttribute('data-vmsx-event-cart-suppressed', '1');
        });
    }

    function start() {
        suppressExpressPayments();
        if (!document.body || typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(suppressExpressPayments);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
