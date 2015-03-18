$(function(){

  // copy ddb text to clipboard
  
  $('div.text').each(function(){
    var furl = '/hgv.dev/js/lmc/lmcbutton.swf';
    var text = $(this).html().replace(/<h6>(.|\n)+<\/h6>/, '')
                             .replace(/\n/g, ' ')
                             .replace(/<br[^>]*>/g, '\n')
                             .replace(/<\/div>/g, '\n')
                             .replace(/<[^>]+>/g, '').replace(/^\s+|\s+$/g, '')
                             .replace(/ +/g, ' ').replace(/\n+/g, '\n');
    var params = 'txt=' + encodeURIComponent(text) + '&capt=Kopie';
    var html = '<object width="40" height="20" title="Text in die Zwischenablage kopieren"><param name="movie" value="' + furl + '"><PARAM NAME=FlashVars VALUE="' + params + '"> <embed src="' + furl + '" flashvars="' + params + '"  width="40" height="20"></embed> </object>';

    $(this).children('h6').append(html);
  });

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