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

  // toggle keyword translations (HGV keyword fr/en/es/it variants)

  $(document).on('click', 'tr.keywordsHidden span.show', function(){
    $(this).closest('tr').removeClass('keywordsHidden').addClass('keywords');
  });

  $(document).on('click', 'tr.keywords span.hide', function(){
    $(this).closest('tr').removeClass('keywords').addClass('keywordsHidden');
  });

});