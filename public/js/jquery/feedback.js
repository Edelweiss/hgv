$(function(){
  var fbT = (window.HGV_TRANS || {}).feedback || {};
  var feedback = {
    open: function(){
      $('#feedbackDialogue').dialog({
        autoOpen: true,
        minWidth: 600,
        resizable: true,
        modal: true,
        closeOnEscape: true,
        closeText: fbT.close || 'close',
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

      $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> ' + (fbT.sending || 'Submitting data \u2026') + '</p>').fadeIn(200, function(){
        $.ajax({
          url: '/feedback',
          data: formData + '&action=send',
          type: 'post',
          cache: false,
          dataType: 'json',
          success: function (data) {
            if(data.success){
              $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> ' + (fbT.thanks || 'Thank you.') + '</p>');
            } else {
              $('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> ' + data.error + '</p>', feedback.enableForm());
            }
          },
          error: function(){$('#feedbackInfo').html('<p><span class="ui-icon ui-icon-info"></span> ' + (fbT.error || 'The form could not be sent.') + '<p>');}
        });        
      });
    },

    email: function(e){
      e.preventDefault();

      var url = $(this).attr('href').replace('dh', 'dieter.hagedorn').replace('jc', 'james.cowey').replace('cl', 'carmen.lanz');

      if($('#feedbackSubject').val()){
        url = url.replace(fbT.default_subject || 'Feedback on HGV Website', $('#feedbackSubject').val());
      }

      if($('#feedbackMessage').val()){
        url = url.replace(fbT.default_body || 'Your message to us \u2026', $('#feedbackMessage').val().replace(/\n/g, '%0A'));
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
