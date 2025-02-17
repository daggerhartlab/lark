(function($) {

  $(document).ready(function() {
    console.log("HERERERE")
    $('.lark-toggle-details-row').hide();
    $('.lark-toggle-row .lark-toggle-handle').on('click', function() {
      let uuid = $(this).data('uuid');
      let row = $(this).closest('table').find('.lark-toggle-details-row--' + uuid);
      row.toggle();
    });
  });

})(jQuery)
