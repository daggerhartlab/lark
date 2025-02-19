(function($) {

  $(document).ready(function() {
    $('.toggle-row-table--details, .toggle-row-table--toggle-handle--handle-close').hide();

    $('.toggle-row-table--toggle-row .toggle-row-table--toggle-handle').on('click', function() {
      let $toggleRow = $(this).closest('tr');
      let uuid = $toggleRow.data('uuid');
      let $detailsRow = $(this).closest('table').find('.toggle-row-table--details--' + uuid);

      $detailsRow.toggle();
      $toggleRow.find('.toggle-row-table--toggle-handle').toggle();
    });
  });

})(jQuery)
