(function ($) {
    'use strict';

    var config = window.VMS_DISCOUNTS_TIPS || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var i18n = config.i18n || {};
    var requestInFlight = false;

    function getMessage(key, fallback) {
        return i18n[key] || fallback;
    }

    function normalizeAmount(raw) {
        var amount = parseFloat(String(raw || '').replace(/[^0-9.]/g, ''));
        if (!isFinite(amount) || amount < 0) {
            return 0;
        }
        return Math.round(amount * 100) / 100;
    }

    function setStatus($box, message, state) {
        var $status = $box.find('.vms-discounts-tip-status').first();
        $status.text(message || '');
        $box.removeClass('is-saving is-error is-success');
        if (state) {
            $box.addClass('is-' + state);
        }
    }

    function setSelectedButton($box, amount) {
        $box.find('.vms-discounts-tip-button').each(function () {
            var $button = $(this);
            var buttonAmount = normalizeAmount($button.data('tipAmount'));
            var selected = Math.abs(buttonAmount - amount) < 0.0001;
            $button.toggleClass('is-selected', selected).attr('aria-pressed', selected ? 'true' : 'false');
        });
    }

    function refreshWooTotals() {
        if ($('form.checkout').length) {
            $(document.body).trigger('update_checkout');
            return;
        }

        $(document.body).trigger('wc_fragment_refresh');
    }

    function saveTip($box, amount) {
        if (!ajaxUrl || requestInFlight) {
            return;
        }

        amount = normalizeAmount(amount);
        requestInFlight = true;
        $box.addClass('is-saving');
        setStatus($box, getMessage('saving', 'Updating tip…'), 'saving');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'vms_discounts_set_tip',
                nonce: nonce,
                amount: amount
            }
        }).done(function (response) {
            if (!response || !response.success) {
                setStatus($box, getMessage('error', 'The tip could not be updated. Please try again.'), 'error');
                return;
            }

            var savedAmount = normalizeAmount(response.data && response.data.amount);
            $box.attr('data-selected-amount', String(savedAmount));
            setSelectedButton($box, savedAmount);

            if (savedAmount === 0) {
                $box.find('.vms-discounts-tip-custom-input').val('');
            }

            setStatus($box, getMessage('saved', 'Tip updated.'), 'success');
            refreshWooTotals();
        }).fail(function () {
            setStatus($box, getMessage('error', 'The tip could not be updated. Please try again.'), 'error');
        }).always(function () {
            requestInFlight = false;
            $box.removeClass('is-saving');
        });
    }

    $(document).on('click', '.vms-discounts-tip-button', function () {
        var $button = $(this);
        var $box = $button.closest('[data-vms-tip-box]');
        var amount = normalizeAmount($button.data('tipAmount'));
        saveTip($box, amount);
    });

    $(document).on('click', '.vms-discounts-tip-custom-apply', function () {
        var $box = $(this).closest('[data-vms-tip-box]');
        var amount = normalizeAmount($box.find('.vms-discounts-tip-custom-input').val());
        saveTip($box, amount);
    });

    $(document).on('keydown', '.vms-discounts-tip-custom-input', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        var $box = $(this).closest('[data-vms-tip-box]');
        saveTip($box, normalizeAmount($(this).val()));
    });
})(jQuery);
