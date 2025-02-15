(function($) {

  $(document).ready(function() {
    $('.lark-yaml-row').hide();
    $('.lark-toggle-row').on('click', function() {
      let uuid = $(this).data('uuid');
      let row = $(this).closest('table').find('.lark-yaml-row--' + uuid);
      row.toggle();
    });
  });

})(jQuery)
