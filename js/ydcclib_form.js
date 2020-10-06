var language_field = "";
var completion_field = "";

(function(){

   var theLanguages = [];
	var ajaxService = "YDCCLIB_AJAX_URL";
   var project_id = YDCCLIB_PROJECT_ID;
   var event_id = YDCCLIB_EVENT_ID;
   var record_id = "YDCCLIB_RECORD";
   var username = "YDCCLIB_USERNAME";
   var form_name = "YDCCLIB_FORM_NAME";

	function initialize() {
      $.ajax({
         method: 'POST',
         url: ajaxService,
         dataType: 'json',
         data: { project_id: project_id, username: username, form_name: form_name, request: 'get-initialization-data'  }
      }).done(function(data) {
         console.log(data);
         data.languages.forEach(function(item){
            theLanguages.push(item);
         });
         language_field = data.language_field;
         completion_field = data.completion_field;
         insertLanguageButtonSection();
         updateLanguageField();

         if ( language_field ){

            var language = $('input[name='+language_field+']').val();
            var completion = $('select[name='+completion_field+']').val();
            var selected_language = $('div.yale-second-language-button.yale-second-language-button-selected').attr('language');

            if ( language && language !== selected_language && completion !== "2") {
               $('div.yale-second-language-button[language='+language+']').trigger('click');
            }

         }

      }).fail(function(jqXHR, textStatus, errorThrown) {
         console.log(jqXHR);
         alert('AJAX error: '+errorThrown);
      }).always(function(){

      });
   }

   function updateLanguageField( force ){
      var completion = $('select[name='+completion_field+']').val() || '0';
      force = force || false;
      if ( language_field && completion !== '2' ) {
         var selected_language = $('div.yale-second-language-button.yale-second-language-button-selected').attr('language');
         var input_language_field = $('input[name='+language_field+']');
         if ( !input_language_field.val() || force ) {
            input_language_field.val(selected_language).triggerHandler('change');
         }
      }
   }

   function insertLanguageButtonSection() {

      var langBtnHtml = "<div class='yale-second-language-button-section'>";

      //langBtnHtml += "<div class='yale-second-language-button' onclick='displayLanguage(\"primary\");'>primary</div>";
      langBtnHtml += "<div class='yale-second-language-button yale-second-language-button-selected' language='primary'>primary</div>";

      for ( var i=0; i<theLanguages.length; i++ ) {
         //langBtnHtml += "<div class='yale-second-language-button' onclick='displayLanguage(\""+theLanguages[i]+"\");'>"+theLanguages[i]+"</div>";
         langBtnHtml += "<div class='yale-second-language-button' language='"+theLanguages[i]+"'>"+theLanguages[i]+"</div>";
      }

      langBtnHtml += "</div>";

      $('div#dataEntryTopOptionsButtons').after( langBtnHtml );

      $('div.yale-second-language-button').click(function () {
         var language = $(this).attr('language');

         $('.yale-second-language-button-selected').removeClass('yale-second-language-button-selected');
         $(this).addClass('yale-second-language-button-selected');

         //console.log('click!', language, form_name, project_id);
         $.ajax({
            method: 'POST',
            url: ajaxService,
            dataType: 'json',
            data: { project_id: project_id, event_id: event_id, record: record_id, form_name: form_name, username: username, language:language, request: 'get-form-metadata'  }
         }).done(function(data) {
            console.log(data);
            var m, f, c;

            updateLanguageField( true );

            // matrices
            for ( m=0; m<data.matrices.length; m++ ){

               // header
               $('tr#'+data.matrices[m].matrix_fields[0].field_name+'-sh-tr > td').html( data.matrices[m].matrix_header );

               // column labels
               for ( c=0; c < data.matrices[m].matrix_choices.length; c++ ){
                  $('td#matrixheader-'+data.matrices[m].matrix_name+'-'+data.matrices[m].matrix_choices[c].value).html( data.matrices[m].matrix_choices[c].label );
               }

               // dang floater
               if ( $('div.floatMtxHdr') ) {
                  $('div.floatMtxHdr table.headermatrix tr:first')
                     .children()
                     .each(function (index) {
                        if (index > 0) {
                           if ( data.matrices[m].matrix_choices[index - 1] ) {
                              $(this).html(data.matrices[m].matrix_choices[index - 1].label)
                           }
                        }
                     });
               }

               // row labels
               for ( f=0; f < data.matrices[m].matrix_fields.length; f++ ){
                  $('label#label-'+data.matrices[m].matrix_fields[f].field_name+' > table > tbody > tr:first > td:first').html( data.matrices[m].matrix_fields[f].field_label );
               }

            }

            // other fields
            for ( f=0; f < data.fields.length; f++ ){
               // labels
               if ( data.fields[f].field_type == "descriptive" ) {
                  $('tr#'+data.fields[f].field_name+'-tr > td.labelrc').html(data.fields[f].field_label);
               } else {
                  $('label#label-' + data.fields[f].field_name + ' > table > tbody > tr:first > td:first').html(data.fields[f].field_label);
                  // value labels
                  for ( c=0; c < data.fields[f].field_choices.length; c++ ){
                     if ( data.fields[f].field_type == "select" ) {
                        $('select[name=' + data.fields[f].field_name + '] > option[value=' + data.fields[f].field_choices[c].value+']').html(data.fields[f].field_choices[c].label);
                     } else {
                        $('label#label-' + data.fields[f].field_name + '-' + data.fields[f].field_choices[c].value).html(data.fields[f].field_choices[c].label);
                     }
                  }
               }
            }


         }).fail(function(jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            alert('AJAX error: '+errorThrown);
         }).always(function(){

         });

      });

   }

	$( document ).ready(function(){
		initialize();
	});

})();
