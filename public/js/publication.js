$(function(){

  // Column 1: click volume → load numbers into column 2, clear column 3
  $('#volume').on('click', 'li:not(.placeholder)', function() {
    var $li = $(this);
    $('#number').html('<li class="placeholder">' + ((window.HGV_TRANS || {}).loading || 'Loading\u2026') + '</li>');
    $('#result').html('');
    $('#number').load($li.attr('data-url'));
    $('#volume li').removeClass('active');
    $li.addClass('active');
  });

  // Column 2: click number → load datasheets into column 3
  $('#number').on('click', 'li:not(.placeholder)', function() {
    var $li = $(this);
    $('#result').html('<p class="placeholder">' + ((window.HGV_TRANS || {}).loading || 'Loading\u2026') + '</p>');
    $('#result').load($li.attr('data-url'));
    $('#number li').removeClass('active');
    $li.addClass('active');
  });

});
