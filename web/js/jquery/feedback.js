$(function(){
  var feedback = {
    open: function(){
      $('#feedbackDialogue').dialog({
        autoOpen: true,
        minWidth: 600,
        resizable: true,
        modal: true,
        closeOnEscape: true,
        closeText: 'schließen',
        draggable: true,
        hide: { effect: 'blind', duration: 800 },
        show: { effect: 'blind', duration: 800 }
      });
      feedback.enableForm();
      $('#feedbackInfo').hide();
    },
    
    send: function(e){
      e.preventDefault();

      var formData = $(this).serialize();
      feedback.disableForm();

      $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> Die Daten werden übermittelt …</p>').fadeIn(200, function(){
        $.ajax({
          url: '/feedback',
          data: formData + '&action=send',
          type: 'post',
          cache: false,
          dataType: 'json',
          success: function (data) {
            if(data.success){
              $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> Vielen Dank.</p>');
            } else {
              $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> ' + data.error + '</p>', feedback.enableForm());
            }
          },
          error: function(){$('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> Das Formular konnte nicht gesendet werden. Bitte versuchen Sie es zu einem späteren Zeitpunkt erneut oder benutzen Sie unten stehenden E-Mail-Link.<p>');}
        });        
      });
    },

    email: function(e){
      e.preventDefault();

      var url = $(this).attr('href').replace('dh', 'dieter.hagedorn').replace('jc', 'james.cowey').replace('cl', 'carmen.lanz');

      if($('#feedbackSubject').val()){
        url = url.replace('Rückmeldung zum Webauftritt vom HGV', $('#feedbackSubject').val());
      }

      if($('#feedbackMessage').val()){
        url = url.replace('Ihre Nachricht an uns …', $('#feedbackMessage').val());
      }

      window.location.href = url;
    },
    
    disableForm: function(){
      $('#feedbackDialogue form input, #feedbackDialogue form textarea, #feedbackDialogue form button').attr('disabled', 'disabled');
    },
    
    enableForm: function(){
      $('#feedbackDialogue form input, #feedbackDialogue form textarea, #feedbackDialogue form button').removeAttr('disabled');
    }
  };
  
  // event handler

  $('#feedback').click(feedback.open);
  $('#feedbackDialogue form').submit(feedback.send);
  $('#feedbackEmail').click(feedback.email);
  
  // test & debug

  //$('#feedback').click();

});
