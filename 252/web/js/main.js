$(function(){

  $('li.inactive').mouseenter(
    function(){
      $(this).attr('class', 'active');
    }
  );
  
  $('li.inactive').mouseleave(
    function(){
      $(this).attr('class', 'inactive');
    }
  );

});
