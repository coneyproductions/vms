(function () {
    'use strict';

    // Also protect against an old cached enqueue on an existing-order pay page.
    if (document.querySelector('form#order_review') || (document.body && document.body.classList.contains('woocommerce-order-pay'))) {
        return;
    }

    var cfg = window.VMSXCheckoutPolicies || {};
    if (!cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    var state = {
        accepted: !!cfg.accepted,
        policyHash: cfg.policyHash || '',
        scopeHash: cfg.scopeHash || '',
        busy: false,
        processing: false,
        blockPayload: '',
        revision: 0,
        desiredAcceptance: false
    };
    var processingTimer = null;
    var requestQueue = Promise.resolve();

    function policyPanel() {
        return document.getElementById('vmsx-checkout-policies');
    }

    function policyCheckbox() {
        return document.getElementById('vmsx-checkout-policies-accept');
    }

    function setBodyState(accepted) {
        var body = document.body;
        if (!body) return;
        body.classList.add('vmsx-policy-event-checkout');
        body.classList.toggle('vmsx-policy-locked', !accepted);
    }

    function setPanelState(accepted) {
        var panel = policyPanel();
        if (!panel) return;
        panel.classList.toggle('is-required', !accepted);
        panel.classList.toggle('is-accepted', !!accepted);
        if (accepted) {
            panel.classList.remove('is-attention');
        }
    }

    function setClassicMirror(accepted) {
        var mirror = document.getElementById('vmsx-checkout-policies-form-accept');
        if (mirror) {
            mirror.value = accepted ? '1' : '0';
        }
    }

    function setBlockExtensionData(accepted) {
        try {
            if (!window.wp || !wp.data || !wp.data.dispatch) return;
            var dispatch = wp.data.dispatch('wc/store/checkout');
            if (!dispatch || typeof dispatch.setExtensionData !== 'function') return;
            var payload = {
                accepted: !!accepted,
                policy_hash: state.policyHash,
                scope_hash: state.scopeHash
            };
            var signature = JSON.stringify(payload);
            if (signature === state.blockPayload) return;
            state.blockPayload = signature;
            dispatch.setExtensionData(cfg.namespace || 'vmsx-checkout-policies', payload);
        } catch (e) {
            // Server validation remains authoritative if the Blocks store is
            // unavailable or changes; do not unlock express payment here.
        }
    }

    function setStatus(message, isError) {
        var el = document.getElementById('vmsx-checkout-policies-status');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('is-error', !!isError);
    }

    function setCheckbox(accepted) {
        var box = policyCheckbox();
        if (box) {
            box.checked = !!accepted;
            box.disabled = !!state.busy || !!state.processing;
        }
    }

    // Explain this existing policy prerequisite without deciding whether billing,
    // payment or another checkout owner is ready. Never change a button lock here.
    function setClassicPolicyGuidance() {
        var button = document.querySelector('form.checkout #place_order');
        var box = policyCheckbox();
        if (!button || !box) return;

        var note = document.getElementById('vmsx-checkout-policy-guidance');
        if (!note) {
            note = document.createElement('div');
            note.id = 'vmsx-checkout-policy-guidance';
            note.className = 'vmsx-checkout-policy-guidance';
            note.setAttribute('role', 'status');
            note.setAttribute('aria-live', 'polite');
            var messageText = document.createElement('span');
            messageText.appendChild(document.createTextNode(''));
            note.appendChild(messageText);
            var link = document.createElement('a');
            link.href = '#vmsx-checkout-policies-accept';
            link.textContent = cfg.reviewPoliciesMessage || 'Review and accept Event Policies';
            link.addEventListener('click', function (event) {
                var checkbox = policyCheckbox();
                if (!checkbox || checkbox.disabled) return;
                event.preventDefault();
                checkbox.focus({ preventScroll: true });
                checkbox.scrollIntoView({ block: 'center' });
            });
            note.appendChild(link);
        }
        if (note.nextElementSibling !== button) {
            button.parentNode.insertBefore(note, button);
        }

        var visible = !state.accepted || state.busy;
        var message = state.busy
            ? (cfg.savingPoliciesMessage || 'Saving Event Policies acceptance. Please wait.')
            : (cfg.policyPrerequisiteMessage || 'Please agree to the Event Policies before placing your order. Required checkout details and payment must also be complete.');
        var text = note.querySelector('span');
        // The existing checkout watcher observes childList changes and rebinds
        // acceptance. Update the text node without triggering that watcher.
        if (text.firstChild.nodeValue !== (visible ? message : '')) text.firstChild.nodeValue = visible ? message : '';
        note.querySelector('a').hidden = !!state.busy;
        note.hidden = !visible;

        var descriptions = (button.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (id) {
            return id && id !== note.id;
        });
        if (visible) descriptions.push(note.id);
        if (descriptions.length) button.setAttribute('aria-describedby', descriptions.join(' '));
        else button.removeAttribute('aria-describedby');
    }

    function setPlaceOrderState() {
        setClassicPolicyGuidance();
        var locked = !state.accepted || state.busy;
        document.querySelectorAll('#place_order, .wc-block-components-checkout-place-order-button').forEach(function (button) {
            button.classList.toggle('vmsx-policy-disabled', locked);
            if (locked) {
                button.setAttribute('aria-disabled', 'true');
                button.setAttribute('data-vmsx-policy-locked', '1');
            } else if (button.getAttribute('data-vmsx-policy-locked') === '1') {
                button.removeAttribute('data-vmsx-policy-locked');
                button.removeAttribute('aria-disabled');
            }

            // During an actual classic checkout submission, hard-disable the
            // button as a second line of defense against duplicate orders.
            if (state.processing) {
                button.disabled = true;
                button.setAttribute('data-vmsx-processing-disabled', '1');
            } else if (button.getAttribute('data-vmsx-processing-disabled') === '1') {
                button.disabled = false;
                button.removeAttribute('data-vmsx-processing-disabled');
            }
        });
    }

    function applyState(accepted) {
        state.accepted = !!accepted;
        setBodyState(state.accepted);
        setPanelState(state.accepted);
        setClassicMirror(state.accepted);
        setBlockExtensionData(state.accepted);
        setCheckbox(state.busy ? state.desiredAcceptance : state.accepted);
        setPlaceOrderState();
    }

    function postState(intent, accepted) {
        var data = new URLSearchParams();
        data.set('action', 'vmsx_checkout_policy_toggle');
        data.set('nonce', cfg.nonce);
        data.set('intent', intent || 'set');
        data.set('accepted', accepted ? '1' : '0');

        // Serialize our session reads/writes so an older status request cannot
        // overwrite a later explicit acceptance in the Woo session.
        var request = requestQueue.then(function () {
            return fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: data.toString()
            }).then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok || !json || !json.success) {
                        var message = json && json.data && json.data.message ? json.data.message : cfg.errorMessage;
                        throw new Error(message || 'Unable to save policy acceptance.');
                    }
                    return json.data || {};
                });
            });
        });
        requestQueue = request.catch(function () {});
        return request;
    }

    function syncStatus() {
        if (state.busy) return Promise.resolve();
        var revision = state.revision;
        return postState('status', false).then(function (data) {
            // A user action made after this read began owns the visible state.
            if (revision !== state.revision || state.busy) return data;
            if (data.policy_hash) state.policyHash = data.policy_hash;
            if (data.scope_hash !== undefined) state.scopeHash = data.scope_hash;
            applyState(!!data.accepted);
            return data;
        }).catch(function () {
            // Ignore superseded reads; current failures still fail closed.
            if (revision === state.revision && !state.busy) applyState(false);
        });
    }

    function onToggle(event) {
        var desired = !!event.target.checked;
        state.revision++;
        state.desiredAcceptance = desired;
        state.busy = true;
        setCheckbox(desired);
        setStatus('');

        // Revocation is immediate in the UI; acceptance unlocks only after the
        // server confirms the Woo session state.
        if (!desired) {
            applyState(false);
        } else {
            setBodyState(false);
            setClassicMirror(true);
            setPanelState(false);
            setPlaceOrderState();
        }

        postState('set', desired).then(function (data) {
            if (data.policy_hash) state.policyHash = data.policy_hash;
            if (data.scope_hash !== undefined) state.scopeHash = data.scope_hash;
            state.busy = false;
            applyState(!!data.accepted);
            if (desired && !data.accepted) {
                throw new Error(cfg.errorMessage || 'Policy acceptance was not saved.');
            }
        }).catch(function (error) {
            state.busy = false;
            applyState(false);
            setStatus(error && error.message ? error.message : cfg.errorMessage, true);
        });
    }

    function policyMarkup() {
        var section = document.createElement('section');
        section.id = 'vmsx-checkout-policies';
        section.className = 'vmsx-checkout-policies vmsx-checkout-policies--blocks ' + (state.accepted ? 'is-accepted' : 'is-required');

        var details = document.createElement('details');
        details.className = 'vmsx-checkout-policies__details';
        details.open = !!cfg.openByDefault;

        var summary = document.createElement('summary');
        summary.textContent = cfg.summaryLabel || 'Event policies';
        details.appendChild(summary);

        var content = document.createElement('div');
        content.className = 'vmsx-checkout-policies__content';
        if (cfg.policyTitle) {
            var title = document.createElement('strong');
            title.className = 'vmsx-checkout-policies__title';
            title.textContent = cfg.policyTitle;
            content.appendChild(title);
        }
        var body = document.createElement('div');
        body.className = 'vmsx-checkout-policies__body';
        String(cfg.policyBody || '').split(/\n+/).forEach(function (line) {
            if (!line.trim()) return;
            var p = document.createElement('p');
            p.textContent = line;
            body.appendChild(p);
        });
        content.appendChild(body);
        if (cfg.policyVersion) {
            var version = document.createElement('small');
            version.className = 'vmsx-checkout-policies__version';
            version.textContent = 'Policy version ' + cfg.policyVersion;
            content.appendChild(version);
        }
        details.appendChild(content);
        section.appendChild(details);

        var label = document.createElement('label');
        label.className = 'vmsx-checkout-policies__accept';
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = 'vmsx-checkout-policies-accept';
        checkbox.value = '1';
        checkbox.checked = !!state.accepted;
        var text = document.createElement('span');
        text.textContent = cfg.checkboxLabel || 'I have read and agree to the event policies.';
        label.appendChild(checkbox);
        label.appendChild(text);
        section.appendChild(label);

        var status = document.createElement('div');
        status.id = 'vmsx-checkout-policies-status';
        status.className = 'vmsx-checkout-policies__status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        section.appendChild(status);
        return section;
    }

    function ensureBlocksPanel() {
        if (policyPanel()) return;
        var checkout = document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout');
        if (!checkout) return;

        var panel = policyMarkup();
        var express = checkout.querySelector('.wc-block-components-express-payment, .wp-block-woocommerce-checkout-express-payment-block');
        if (express && express.parentNode) {
            express.parentNode.insertBefore(panel, express);
            return;
        }

        var main = checkout.querySelector('.wc-block-checkout__main') || checkout;
        main.insertBefore(panel, main.firstChild);
    }

    function scrollToPolicyProblem(message) {
        state.processing = false;
        hideProcessingOverlay();
        var panel = policyPanel();
        var box = policyCheckbox();
        if (panel) {
            panel.classList.add('is-attention');
            var details = panel.querySelector('details');
            if (details) details.open = true;
        }
        setStatus(message || cfg.requiredMessage || cfg.errorMessage || 'Please accept the Event Policies before placing your order.', true);

        if (panel && typeof panel.scrollIntoView === 'function') {
            var behavior = 'smooth';
            try {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    behavior = 'auto';
                }
            } catch (e) {}
            panel.scrollIntoView({ behavior: behavior, block: 'center' });
        }
        if (box) {
            window.setTimeout(function () {
                try { box.focus({ preventScroll: true }); } catch (e) { box.focus(); }
            }, 350);
        }
    }

    function ensureProcessingOverlay() {
        var overlay = document.getElementById('vmsx-checkout-processing-overlay');
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.id = 'vmsx-checkout-processing-overlay';
        overlay.className = 'vmsx-checkout-processing-overlay';
        overlay.hidden = true;
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'assertive');
        overlay.innerHTML = '<div class="vmsx-checkout-processing-overlay__card">' +
            '<span class="vmsx-checkout-processing-overlay__spinner" aria-hidden="true"></span>' +
            '<strong class="vmsx-checkout-processing-overlay__title"></strong>' +
            '<span class="vmsx-checkout-processing-overlay__message"></span>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function showProcessingOverlay() {
        if (state.processing) return;
        state.processing = true;
        var overlay = ensureProcessingOverlay();
        var title = overlay.querySelector('.vmsx-checkout-processing-overlay__title');
        var message = overlay.querySelector('.vmsx-checkout-processing-overlay__message');
        if (title) title.textContent = cfg.processingTitle || 'Processing your order…';
        if (message) message.textContent = cfg.processingMessage || 'Please do not refresh or submit the order again.';
        overlay.hidden = false;
        document.body.classList.add('vmsx-checkout-is-processing');
        setCheckbox(state.accepted);
        setPlaceOrderState();

        window.clearTimeout(processingTimer);
        processingTimer = window.setTimeout(function () {
            // Safety escape if a third-party gateway fails without emitting the
            // normal Woo checkout_error event. Server-side duplicate defenses
            // remain outside this UI safeguard.
            hideProcessingOverlay();
        }, 60000);
    }

    function hideProcessingOverlay() {
        state.processing = false;
        window.clearTimeout(processingTimer);
        processingTimer = null;
        var overlay = document.getElementById('vmsx-checkout-processing-overlay');
        if (overlay) overlay.hidden = true;
        if (document.body) document.body.classList.remove('vmsx-checkout-is-processing');
        setCheckbox(state.accepted);
        setPlaceOrderState();
    }

    function isPlaceOrderButton(target) {
        return target && target.closest && target.closest('#place_order, .wc-block-components-checkout-place-order-button');
    }

    function bindCheckoutGuards() {
        if (document.documentElement.dataset.vmsxCheckoutGuardsBound) return;
        document.documentElement.dataset.vmsxCheckoutGuardsBound = '1';

        // Intercept before WooCommerce starts checkout. This keeps an unchecked
        // policy from wasting a checkout AJAX request just to be rejected by
        // the server validator. The server validator remains the backstop.
        document.addEventListener('click', function (event) {
            var button = isPlaceOrderButton(event.target);
            if (!button) return;
            if (!state.accepted || state.busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                scrollToPolicyProblem();
                return;
            }
            if (state.processing) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || !form.matches || !form.matches('form.checkout')) return;
            if (!state.accepted || state.busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                scrollToPolicyProblem();
                return;
            }
            if (state.processing) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            showProcessingOverlay();
        }, true);
    }

    function bind() {
        ensureBlocksPanel();
        var box = policyCheckbox();
        if (box && !box.dataset.vmsxBound) {
            box.dataset.vmsxBound = '1';
            box.addEventListener('change', onToggle);
        }
        bindCheckoutGuards();
        applyState(state.accepted);
    }

    document.addEventListener('DOMContentLoaded', function () {
        bind();
        syncStatus().then(bind);

        var observer = new MutationObserver(function () {
            ensureBlocksPanel();
            bind();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Classic checkout refreshes totals asynchronously. Re-check the
        // server scope after those updates so a changed event cart re-locks.
        if (window.jQuery) {
            window.jQuery(document.body)
                .on('updated_checkout', function () {
                    syncStatus().then(bind);
                })
                .on('checkout_error', function () {
                    hideProcessingOverlay();
                    if (!state.accepted) {
                        scrollToPolicyProblem();
                    }
                });
        }
    });
})();
