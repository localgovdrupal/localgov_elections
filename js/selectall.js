/**
 * @file Check/Uncheck all checkboxes.
 **/

(function (Drupal, once) {
  Drupal.behaviors.selectAll = {
    attach: function (context, settings) {

      const checkboxes = once('allCheckboxes', '.form-checkboxes', context);
      const selectAll = once('allSelectAlls', '.shield-select-all', context);

      if (checkboxes) {
        checkboxes.forEach(checkbox => {
         checkbox.insertAdjacentHTML('afterbegin', `<input type="checkbox" class="form-checkbox shield-select-all"/><label class="option shield-select-all-label"> ${Drupal.t('Select / Deselect all')} </label>`);
        });
      }

      if (selectAll) {
        selectAll.forEach(select => {
          select.addEventListener('click', function () {
            let isChecked = (!select.getAttribute('checked'));
            select.closest('.form-checkboxes').querySelectorAll('input[type=checkbox]').forEach(checkbox => {
              checkbox.checked = isChecked;
            });
            select.setAttribute('checked', isChecked);
          });
        });
      }

    }
  };

})(Drupal, once);