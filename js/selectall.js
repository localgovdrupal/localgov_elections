(function ($, Drupal) {

  /**
   * Check/Uncheck all checkboxes.
   */
  Drupal.behaviors.selectAll = {
    attach: function (context, settings) {
      $(".form-checkboxes").prepend($('<input type="checkbox" class="form-checkbox shield-select-all"/><label class="option shield-select-all-label"> Select / Deselect all </label>'));
      $(".shield-select-all").on('click', function () {
        let $isChecked = (!$(this).attr('checked'));
        $(this).parent().find('.form-type-checkbox input[type=checkbox]').attr('checked', $isChecked);
        $(this).attr('checked', $isChecked)
      })
    }
  };

})(jQuery, Drupal);