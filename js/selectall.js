/**
 * @file Check/Uncheck all checkboxes.
 * */

function lgdElectionsSelectAllIIFE(Drupal, once) {
  Drupal.behaviors.lgdElectionsSelectAll = {
    attach: function selectAllAttach(context) {
      const checkboxes = once('allCheckboxes', '.form-checkboxes', context);
      const selectAll = once('allSelectAlls', '.shield-select-all', context);

      function handleSelectAllClick(select) {
        const isChecked = !select.getAttribute('checked');
        const allCheckboxes = select
          .closest('.form-checkboxes')
          .querySelectorAll('input[type=checkbox]');
        allCheckboxes.forEach(function setChecked(checkbox) {
          checkbox.checked = isChecked;
        });
        select.setAttribute('checked', isChecked);
      }

      if (checkboxes) {
        checkboxes.forEach(function insertCheckbox(checkbox) {
          checkbox.insertAdjacentHTML(
            'afterbegin',
            `<input type="checkbox" class="form-checkbox shield-select-all"/><label class="option shield-select-all-label"> ${Drupal.t('Select / Deselect all')} </label>`,
          );
        });
      }

      if (selectAll) {
        selectAll.forEach(function addSelectAllListener(select) {
          select.addEventListener('click', function selectAllClick() {
            handleSelectAllClick(select);
          });
        });
      }
    },
  };
}(Drupal, once);
