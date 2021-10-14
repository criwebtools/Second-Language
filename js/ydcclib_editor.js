(function(){

	var url   = {
		'ajaxService':'YDCCLIB_AJAX_URL'
		, 'closeButtonImage':'YDCCLIB_CLOSE_BUTTON_IMAGE_URL'
		, 'css':'YDCCLIB_CSS_URL'
   }

   var project_id = YDCCLIB_PROJECT_ID;
	var username = 'YDCCLIB_USERNAME';

	var languages = [];

   var fieldEditorIsVisible = false;
   var matrixEditorIsVisible = false;
   var fieldEditorSaveHandlerBound = false;
   var matrixEditorSaveHandlerBound = false;

   var xlatQueue = [];

   var monitorTimerHandle = 0;

	function getLanguagesAndStartMonitoring() {
      $.ajax({
         method: 'POST',
         url: url.ajaxService,
         dataType: 'json',
         data: { project_id: project_id, username: username, request: 'get-languages'  }
      }).done(function(response) {
         response.forEach(function(item){
            languages.push(item);
         });
         startMonitoring(); // detect open field editor
      }).fail(function(jqXHR, textStatus, errorThrown) {
         /*
          * Fatal errors are thrown if editor is open when timeout occurs, and user subsequently logs back in.
          * The jqXHR response appears to be the login page itself, not
          * the response returned by the SecondLanguage service.
          * For now I am just letting this fail silently with a console report.
          * This causes the login screen to be redisplayed,
          * and the user must login a second time.
         */
         console.log('Second Language Ajax error!', jqXHR);
          //alert('AJAX error: '+errorThrown);
      }).always(function(){

      });
   }

   function insertFieldEditorExtensions() {
      cloneFieldLabel();
      cloneElementEnum();
      bindFieldEditorSaveHandler();
      getXlatRecords();
   }

   function insertMatrixEditorExtensions() {
      cloneMatrixHeaders();
      cloneMatrixLabels();
      cloneMatrixElementEnum();
      bindMatrixEditorSaveHandler();
      getXlatMatrixRecords();
   }

   function removeEditorExtensions() {
	   $(".ydcclib-field-editor-extension").remove();
   }

   function monitorEditors() {
      monitorFieldEditor();
      monitorMatrixEditor();
   }

   function startMonitoring(){
      monitorTimerHandle = setInterval( monitorEditors, 1000 ); // detect open / close of field editor
   }

   function stopMonitoring(){
      if ( monitorTimerHandle ) clearInterval( monitorTimerHandle );
   }

   function monitorFieldEditor() {
      // is the editor visible?
      if ( $(".ui-dialog-title span:contains('Edit Field'):visible").length ) {
         // if newly visible then do stuff
         if ( !fieldEditorIsVisible ) {
            fieldEditorIsVisible = true;
            //console.log("Field editor just popped up");
            setTimeout(insertFieldEditorExtensions, 500);
         }
      } else {
         if ( fieldEditorIsVisible ) {
            fieldEditorIsVisible = false;
            fieldEditorSaveHandlerBound = false; // the binding appears to be removed when ui dialog is reopened, even if save button object persists
            removeEditorExtensions();
            //console.log("Field editor just closed");
         }
      }
      return( fieldEditorIsVisible );
   }

   function monitorMatrixEditor() {
      // is the editor visible?
      if ( $(".ui-dialog-title span:contains('Edit Matrix of Fields'):visible").length ) {
         // if newly visible then do stuff
         if ( !matrixEditorIsVisible ) {
            matrixEditorIsVisible = true;
           //console.log("Matrix editor just popped up");
            setTimeout(insertMatrixEditorExtensions, 500);
         }
      } else {
         if ( matrixEditorIsVisible ) {
            matrixEditorIsVisible = false;
            removeEditorExtensions();
            //console.log("Matrix editor just closed");
         }
      }
      return( fieldEditorIsVisible );
   }

   function cloneFieldLabel() {
      var theOriginal = $('#field_label').parent().parent();
      for (var i = languages.length-1; i >= 0; i-- ){
         var theClone = theOriginal.clone();
         theClone.find('#field_label')
            .prop("id", "field_label_" + languages[i])
            .prop("name", "field_label_" + languages[i])
            .addClass(languages[i])
            .addClass('ydcclib-cloned-field_label')
            .addClass('ydcclib-field-editor-extension')
            .removeClass("x-form-textarea")
            .removeClass("x-form-field");
         theClone.children(":first")
            .html('Field Label: '+languages[i])
            .addClass('ydcclib-language-element-title')
         ;
         theClone.children().addClass('ydcclib-field-editor-extension');
         theOriginal
            .after(theClone)
         ;
      }
      // remove the rich text editor checkbox and label elements, if they exist
      $('div.ydcclib-field-editor-extension label:contains("Rich Text Editor")').remove();
   }

   function cloneElementEnum() {
      var theOriginal = $('#div_element_enum');

      /*
       * first, clean up some CSS.
       * The enum wrapper has a fixed height, and some elements have top margins
       * that create unnecessary white space, especially when the textareas are resized.
       */
      theOriginal.children().css({'height':'auto', 'margin-top':'0'});

      for (var i = languages.length-1; i >= 0; i-- ){
         var theClone = theOriginal.clone();
         theClone
            .prop("id", "div_element_enum_"+languages[i])
            .prop("name", "div_element_enum_"+languages[i])
            .addClass('ydcclib-field-editor-extension')
         ;
         theClone.find('#LSC_id_element_enum').remove();
         theClone.find('#test-calc-parent').remove();
         theClone.find('#div_autocomplete').remove();
         theClone.find('#div_manual_code').remove();
         theClone.find('.manualcode-label').remove();
         theClone.children(":first").html('Choices: '+languages[i]).addClass('ydcclib-language-element-title').show();
         theClone.find('#element_enum')
            .prop("id", "element_enum_" + languages[i])
            .prop("name", "element_enum_" + languages[i])
            .addClass(languages[i])
            .removeAttr("onblur")
            .removeAttr("onkeydown")
            .addClass("ydcclib-cloned-element_enum")
            .removeClass("x-form-textarea")
            .removeClass("x-form-field")
            .addClass('ydcclib-cloned-element_enum')
            .children().addClass('ydcclib-field-editor-extension')
         ;
         theOriginal
            .after(theClone)
         ;
      }
   }

   function cloneMatrixElementEnum() {
      var theOriginal = $('#element_enum_matrix');
      for (var i = languages.length-1; i >= 0; i-- ){
         var theClone = theOriginal.clone();
         theClone
            .prop("id", "element_enum_matrix_"+languages[i])
            .prop("name", "element_enum_matrix_"+languages[i])
            .val('')
            .addClass('ydcclib-field-editor-extension')
            .addClass("ydcclib-cloned-element_enum_matrix")
            .addClass(languages[i])
            .removeAttr("onblur")
            .removeAttr("onkeydown")
            .removeClass("x-form-textarea")
            .removeClass("x-form-field")
         ;
         theOriginal
            .after(theClone)
            .after("<div class='ydcclib-language-element-title ydcclib-field-editor-extension'>Choices: "+languages[i]+"</div>")
         ;
      }
   }

   function cloneMatrixHeaders() {
      var theOriginal = $('#section_header_matrix');
      for (var i = languages.length-1; i >= 0; i-- ){
         var theClone = theOriginal.clone();
         theClone
            .prop("id", "section_header_matrix_" + languages[i])
            .prop("name", "section_header_matrix_" + languages[i])
            .val('')
            .removeClass("x-form-textarea")
            .removeClass("x-form-field")
            .addClass('ydcclib-cloned-section_header_matrix')
            .addClass(languages[i])
            .addClass('ydcclib-field-editor-extension')
            .children().addClass('ydcclib-field-editor-extension')
         ;
         $('#section_header_matrix-expand')
            .after(theClone)
            .after("<div class='ydcclib-language-element-title ydcclib-field-editor-extension'>Matrix Header Text: "+languages[i]+"</div>")
         ;
      }
   }

   function cloneMatrixLabels() {
      $(".field_labelmatrix").each(function( index ) {
         var theClone;
         //console.log( "cloneMatrixLabels", index, $(this).val() );
         for (var i = languages.length-1; i >= 0; i-- ) {
            theClone = $(this).clone();
            theClone
               .val('')
               .css({'width':'95%'})
               .removeClass("field_labelmatrix")
               .removeClass("x-form-text")
               .removeClass("x-form-field")
               .addClass('ydcclib-cloned-matrix-label')
               .addClass(languages[i])
               .addClass('ydcclib-field-editor-extension')
               .children().addClass('ydcclib-field-editor-extension')
            ;
            $(this)
               .after(theClone)
               .after("<div class='ydcclib-language-element-title ydcclib-field-editor-extension' class='95%' style='font-weight: 500'>"+languages[i]+"</div>")
            ;
         }
      });
   }

   function bindMatrixEditorSaveHandler() {

      // this is a pretty wild selector, but we're trying to avoid binding to anything but the save button on the jQ-UI field editor dialog

      $("div[role='dialog'][aria-describedby='addMatrixPopup'] .ui-dialog-buttonset button:contains('Save')").bind( "click", function() {
         var field_name;
         var field_label;
         var matrix_header = $("#section_header_matrix").val();
         var choices = $('#element_enum_matrix').val();
         var grid_name = $('#grid_name').val();

         xlatQueue = []; // request queue, used to minimize ajax traffic

         // headers and choices
         queueXlatRecord("primary", "", "matrix", grid_name, matrix_header, choices);
         for (var i=0; i<languages.length; i++ ) {
            matrix_header = $("#section_header_matrix_"+languages[i]).val();
            choices = $("#element_enum_matrix_"+languages[i]).val();
            queueXlatRecord(languages[i], "", "matrix", grid_name, matrix_header, choices);
         }

         // labels and choices
         $(".addFieldMatrixRow").each(function () {
            var field_name = $(this).find(".field_name_matrix:first").val();
            var field_label = $(this).find(".field_labelmatrix:first").val();
            var choices = $('#element_enum_matrix').val();
            queueXlatRecord("primary", grid_name, "field", field_name, field_label, choices);
            for (var i=0; i<languages.length; i++ ) {
               field_label = $(this).find(".ydcclib-cloned-matrix-label."+languages[i]).val();
               choices = $("#element_enum_matrix_"+languages[i]).val();
               queueXlatRecord(languages[i], grid_name, "field", field_name, field_label, choices);
            }
         });

         saveXlatRecord();

      }); // Save button click handler

      matrixEditorSaveHandlerBound = true;
   }

   function bindFieldEditorSaveHandler() {

      $("div[role='dialog'][aria-describedby='div_add_field'] .ui-dialog-buttonset button:contains('Save')").bind( "click", function() {
         var field_name = $('#field_name').val();
         var field_label = $('#field_label').val();
         var matrix_header = $("#section_header_matrix").val();
         var choices = "";

         xlatQueue = []; // request queue, used to minimize ajax traffic

         // labels and choices
         if ( $('#element_enum').is(':visible') ) choices = $('#element_enum').val();
         queueXlatRecord("primary", "", "field", field_name, field_label, choices);
         for (var i=0; i<languages.length; i++ ) {
            field_label = $("#field_label_"+languages[i]).val();
            if ( $("#element_enum_" + languages[i]).is(":visible") ) choices = $("#element_enum_" + languages[i]).val();
            else choices = "";
            queueXlatRecord(languages[i], "", "field", field_name, field_label, choices);
         }

         saveXlatRecord();

      }); // Save button click handler

      fieldEditorSaveHandlerBound = true;
   }

   function queueXlatRecord(xlat_language, xlat_parent, xlat_entity_type, xlat_entity_name, xlat_label, xlat_choices) {
      var parms = {
         xlat_language: xlat_language,
         xlat_parent: xlat_parent,
         xlat_entity_type: xlat_entity_type,
         xlat_entity_name: xlat_entity_name,
         xlat_label: xlat_label,
         xlat_choices: xlat_choices
      };
      xlatQueue.push(parms);
   }

   function saveXlatRecord() {
	   var parms = {request:'save-xlat-record', project_id:project_id, queue:xlatQueue};

	   //console.log('saveXlatRecord queue:', xlatQueue);

      $.ajax({
         method: 'POST',
         url: url.ajaxService,
         dataType: 'json',
         data: parms,
         cache: false
      }).done(function(data) {
         //console.log(data); // the rc are the success and error counts
      }).fail(function(jqXHR, textStatus, errorThrown) {
         //alert('AJAX error: '+errorThrown);
         console.log(jqXHR);
      }).always(function(){

      });
   }

   function getXlatRecords() {
      var field_name = $('#field_name').val();
      var parms = {request:'get-xlat-records', project_id:project_id, field_name:field_name};

      $.ajax({
         method: 'POST',
         url: url.ajaxService,
         dataType: 'json',
         data: parms,
         cache: false
      }).done(function(data) {
         //console.log(data); // the rc are the success and error counts
         var x = {};
         var elemFieldNames;
         var elemFieldName;
         var j;
         for (var i=0; i<data.length; i++){
            x = data[i];
            $("#field_label_"+x.xlat_language).val(x.xlat_label);
            if ( x.xlat_choices ) $("#element_enum_"+x.xlat_language).val(x.xlat_choices);
         }
      }).fail(function(jqXHR, textStatus, errorThrown) {
         //alert('AJAX error: '+errorThrown);
         console.log(jqXHR);
      }).always(function(){

      });
   }

   function getXlatMatrixRecords() {
      var grid_name = $('#grid_name').val();
      var parms = {request:'get-xlat-matrix-records', project_id:project_id, grid_name:grid_name};

      $.ajax({
         method: 'POST',
         url: url.ajaxService,
         dataType: 'json',
         data: parms,
         cache: false
      }).done(function(data) {
         //console.log(data); // the rc are the success and error counts
         var x = {};
         var elemFieldNames;
         var elemFieldName;
         var j;
         for (var i=0; i<data.length; i++){
            x = data[i];
            if ( x.xlat_entity_type == 'matrix' ) {
               $('#section_header_matrix_'+x.xlat_language).val(x.xlat_label);
               $('#element_enum_matrix_'+x.xlat_language).val(x.xlat_choices);
            } else {
               elemFieldNames = $("input.field_name_matrix");
               for ( j=0; j<elemFieldNames.length; j++ ) {
                  if ( elemFieldNames[j].value == x.xlat_entity_name ) {
                     $(elemFieldNames[j]).parent().parent().find(".ydcclib-cloned-matrix-label."+x.xlat_language+":first").val(x.xlat_label);
                     break;
                  }
               }
            }
         }
      }).fail(function(jqXHR, textStatus, errorThrown) {
         //alert('AJAX error: '+errorThrown);
         console.log(jqXHR);
      }).always(function(){

      });
   }

	$( document ).ready(function(){
      //console.log('Second Language READY', project_id, username, url, languages);
		getLanguagesAndStartMonitoring();
	});

})();
