$(function(){

  $('ul#volume li').click(function(event){
    $('ul#number').html('');
    $('ul#result').html('');
    $('ul#number').load($(this).attr('data-url'));
    $('ul#volume li').removeClass('active');
    $(this).addClass('active');
  });

});
