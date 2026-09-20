(function () {
  'use strict';

  function safeStorage() {
    try {
      return window.localStorage;
    } catch (error) {
      try {
        return window.sessionStorage;
      } catch (sessionError) {
        return null;
      }
    }
  }

  function text(value, fallback) {
    var normalized = String(value || '').trim();
    return normalized || fallback;
  }

  function focusableElements(root) {
    return Array.prototype.slice.call(root.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'));
  }

  function handle(payload, state) {
    var cfg = window.BVMGR_TICKETING_FRONT || {};
    var offer = cfg.postCartOffer || {};
    if (!offer || !offer.enabled || !offer.primaryUrl) {
      return false;
    }

    var storage = safeStorage();
    var storageKey = 'bvmgr-post-cart-offer:v2:' + String(cfg.eventPlanId || 0) + ':' + text(offer.id, 'offer');
    var suppressionMs = 60 * 60 * 1000;
    if (storage) {
      var shownAt = Number(storage.getItem(storageKey) || 0);
      if (shownAt > 0 && Date.now() - shownAt < suppressionMs) {
        return false;
      }
      if (shownAt > 0) {
        storage.removeItem(storageKey);
      }
    }

    if (document.getElementById('bvmgr-post-cart-offer')) {
      return true;
    }

    var checkoutUrl = text(offer.secondaryUrl, text(cfg.checkoutUrl, (payload && payload.data && payload.data.cart_url) || cfg.cartUrl || '/checkout/'));
    var suppress = function () {
      if (storage) {
        storage.setItem(storageKey, String(Date.now()));
      }
    };

    var present = function () {
      if (state && state.isSubmitting) {
        window.setTimeout(present, 16);
        return;
      }

      var overlay = document.createElement('div');
      overlay.id = 'bvmgr-post-cart-offer';
      overlay.className = 'bvmgr-post-cart-offer';

      var backdrop = document.createElement('div');
      backdrop.className = 'bvmgr-post-cart-offer__backdrop';
      backdrop.setAttribute('aria-hidden', 'true');

      var dialog = document.createElement('section');
      dialog.className = 'bvmgr-post-cart-offer__dialog';
      dialog.setAttribute('role', 'dialog');
      dialog.setAttribute('aria-modal', 'true');
      dialog.setAttribute('aria-labelledby', 'bvmgr-post-cart-offer-title');
      dialog.setAttribute('aria-describedby', 'bvmgr-post-cart-offer-message');

      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'bvmgr-post-cart-offer__close';
      close.setAttribute('aria-label', 'No thanks — continue to checkout');
      close.textContent = '×';

      var copy = document.createElement('div');
      copy.className = 'bvmgr-post-cart-offer__copy';
      var eyebrow = document.createElement('p');
      eyebrow.className = 'bvmgr-post-cart-offer__eyebrow';
      eyebrow.textContent = 'Express Bar';
      var heading = document.createElement('h2');
      heading.id = 'bvmgr-post-cart-offer-title';
      heading.textContent = text(offer.title, 'Your tickets are in your cart.');
      var message = document.createElement('p');
      message.id = 'bvmgr-post-cart-offer-message';
      message.textContent = text(offer.message, 'Order drinks now, or continue directly to checkout.');
      copy.appendChild(eyebrow);
      copy.appendChild(heading);
      copy.appendChild(message);

      var actions = document.createElement('div');
      actions.className = 'bvmgr-post-cart-offer__actions';
      var primary = document.createElement('a');
      primary.className = 'button bvmgr-primary-button bvmgr-post-cart-offer__primary';
      primary.href = String(offer.primaryUrl);
      primary.textContent = text(offer.primaryLabel, 'ORDER DRINKS');
      var secondary = document.createElement('a');
      secondary.className = 'button bvmgr-post-cart-offer__checkout';
      secondary.href = checkoutUrl;
      secondary.textContent = text(offer.secondaryLabel, 'NO THANKS — CONTINUE TO CHECKOUT');
      actions.appendChild(primary);
      actions.appendChild(secondary);

      dialog.appendChild(close);
      dialog.appendChild(copy);
      dialog.appendChild(actions);
      overlay.appendChild(backdrop);
      overlay.appendChild(dialog);
      document.body.appendChild(overlay);
      document.body.classList.add('bvmgr-post-cart-offer-open');

      var continueToCheckout = function () {
        suppress();
        window.location.assign(checkoutUrl);
      };
      primary.addEventListener('click', suppress);
      secondary.addEventListener('click', suppress);
      close.addEventListener('click', continueToCheckout);
      backdrop.addEventListener('click', continueToCheckout);
      overlay.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          continueToCheckout();
          return;
        }
        if (event.key !== 'Tab') {
          return;
        }
        var focusable = focusableElements(dialog);
        if (!focusable.length) {
          event.preventDefault();
          return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      });

      window.requestAnimationFrame(function () {
        overlay.classList.add('is-open');
        primary.focus({ preventScroll: true });
      });
    };

    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(present);
    });
    return true;
  }

  window.BVMGR_TICKETING_POST_CART_OFFER = { handle: handle };
}());
