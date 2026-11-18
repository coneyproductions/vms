(function ($) {
  'use strict';

  $(function () {
    var $field = $('#vms_ticket_ui_addons_heading_background');
    if ($field.length && $.fn.wpColorPicker) {
      $field.wpColorPicker();
    }
  });
})(jQuery);
