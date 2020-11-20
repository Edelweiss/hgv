$(function(){

  // copy ddb text to clipboard
  new ClipboardJS('.clipboard');

  // toggle digital images
  
  $('h6 span.hide').click(function(){
    $(this).parents('.dashboard').attr('class', 'dashboardHidden');
    console.log(1);
  });

  $('h6 span.show').click(function(){
    $(this).parents('.dashboardHidden').attr('class', 'dashboard');
    console.log(2);
  });
  
  // toggle translation bibliography
  
  $('tr.translation span.show').click(function(){
    $(this).parents('.translationHidden').attr('class', 'translation');
    console.log(3);
  });
  
  $('tr.translation span.hide').click(function(){
    $(this).parents('.translation').attr('class', 'translationHidden');
    console.log(4);
  });

});