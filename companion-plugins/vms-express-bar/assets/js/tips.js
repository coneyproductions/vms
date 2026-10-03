(function($){
  'use strict';

  const updateTip = (selection) => {
    const $field = $('[data-vmseb-tip-selection]');
    if (!$field.length) return;
    $field.val(selection);
    $(document.body).trigger('update_checkout');
  };

  $(document.body).on('click', '[data-vmseb-tip-option]', function(){
    updateTip(String($(this).attr('data-vmseb-tip-option') || 'none'));
  });

  $(document.body).on('click', '[data-vmseb-tip-change]', function(){
    const $box = $(this).closest('[data-vmseb-tip-box]');
    $box.find('[data-vmseb-tip-confirmation]').attr('hidden', true);
    $box.find('[data-vmseb-tip-picker]').removeAttr('hidden');
  });

  $(document.body).on('click', '[data-vmseb-tip-custom-toggle]', function(){
    const $box = $(this).closest('[data-vmseb-tip-box]');
    const $custom = $box.find('[data-vmseb-tip-custom]');
    const opening = $custom.is('[hidden]');
    if (opening) $custom.removeAttr('hidden'); else $custom.attr('hidden', true);
    $(this).attr('aria-expanded', opening ? 'true' : 'false');
    if (opening) window.setTimeout(() => $custom.find('[data-vmseb-tip-custom-amount]').trigger('focus'), 0);
  });

  $(document.body).on('click', '[data-vmseb-tip-custom-apply]', function(){
    const $box = $(this).closest('[data-vmseb-tip-box]');
    const $input = $box.find('[data-vmseb-tip-custom-amount]');
    let value = parseFloat($input.val());
    const max = parseFloat($input.attr('max')) || 100;
    if (!Number.isFinite(value) || value <= 0) {
      updateTip('none');
      return;
    }
    value = Math.min(value, max);
    updateTip(`custom:${value.toFixed(2)}`);
  });

  $(document.body).on('keydown', '[data-vmseb-tip-custom-amount]', function(event){
    if (event.key !== 'Enter') return;
    event.preventDefault();
    $(this).closest('[data-vmseb-tip-box]').find('[data-vmseb-tip-custom-apply]').trigger('click');
  });
})(jQuery);
