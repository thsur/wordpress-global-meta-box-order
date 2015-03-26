jQuery(document).ready( function($) {

  // Lock meta boxes in position by
  // disabling sorting.
  //
  // Credits go to Chris Van Patten:
  // http://wordpress.stackexchange.com/a/44539

  $('.meta-box-sortables').sortable({ disabled: true });
  $('.postbox .hndle').css('cursor', 'pointer');
});