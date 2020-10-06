<?php
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/
if ( isset($_POST['count_only']) ) {
   $count_only = (int)$_POST['count_only'];
} else {
   $count_only = 1;
}

$module = new YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage();

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

print "<h4>Second Language translations copy summary</h4>";

$projectType        = $module->getProjectSetting('projecttype');
$devOnSameHost      = $module->getProjectSetting('devonsamehost');
$devProjectHost     = $module->getProjectSetting('devprojecthost');
$devProjectApiToken = $module->getProjectSetting('devprojectapitoken');
$devProjectId       = $module->getProjectSetting('devprojectid');

$response = null;

if ( $projectType==="P" && $devOnSameHost==="N" && $devProjectHost && $devProjectApiToken ) {

   $response = $module->getTranslationsViaAPI( $devProjectHost, $devProjectApiToken, $count_only );
   $devProjectId = $response['project_id'];
}
elseif ( $projectType==="P" && $devOnSameHost==="Y" && $devProjectId ) {

   if ( $devProjectId == $module->project_id ){
      exit( "<p>ERROR: The same REDCap project is specified for development and production.</p>" );
   }

   $response = $module->getTranslationsViaModule( $devProjectId, $count_only );

}

if ( !$response ){
   exit( "<p>Could not copy translations from dev. Check Second Language external module configuration.</p>" );
}

if ( $response['message'] !== "success" ) {
   exit( "<p>Could not copy translations from dev. The following message was returned:<br /><em>{$response['message']}</em></p>" );
}

$matching_field_count = get_matching_field_count($response['fields']);

if ( !$matching_field_count ) {
   exit( "<p>There are no translations to copy. Make sure you have the correct dev project specified.</p>" );
}

$tgtHost = "this REDCap instance";

if ( $devOnSameHost==="Y" ){
   $srcHost = $tgtHost;
} else {
   $srcHost = $devProjectHost;
}

?>

<script>

   var YDCCLIB = {};

   YDCCLIB.executeCopyRequest = function() {
      $.ajax({
         method: 'POST',
         url: "<?= $module->getUrl('ajax/ydcclib_services.php?pid='.$module->project_id ) ?>",
         dataType: 'html',
         data: {
            request: 'copy-translations'
         }
      }).done(function(response) {

         $('#ydcclib-ajax-response').html(response);

      }).fail(function(jqXHR, textStatus, errorThrown) {
         console.log('Second Language Ajax error!', jqXHR);
         alert('AJAX error: '+errorThrown);
      }).always(function(){

      });
   }

</script>

<style>

   div.ydcclib-row {
      width:100%;
      margin-top: 2rem;
   }

   table.ydcclib-xlat-parms {
      width: 100%;
      max-width: 800px;
      table-layout: fixed;
   }

   table.ydcclib-xlat-parms td {
      padding: 6px;
      vertical-align: top;
      text-align: left;
   }

   table.ydcclib-xlat-parms th {
      padding: 6px;
      vertical-align: bottom;
      text-align: left;
   }

   .ydcclib-stub-cell {
      width: 160px;
   }

   .ydcclib-data-cell {

   }

   table.ydcclib-xlat-parms tr.ydcclib-odd {
      background-color: #efefef;
   }

   table.ydcclib-xlat-parms tr.ydcclib-top-row {
      border-top: 1px solid slategray;
      border-bottom: 1px solid slategray;
   }

   table.ydcclib-xlat-parms tr.ydcclib-bottom-row {
      border-bottom: 1px solid slategray;
   }

</style>

<div class="ydcclib-row">
   <table class="ydcclib-xlat-parms">
      <tr class="ydcclib-top-row">
         <th class="ydcclib-stub-cell">&nbsp;</th>
         <th class="ydcclib-data-cell">copy translations FROM</th>
         <th class="ydcclib-data-cell">copy translations TO</th>
      </tr>

      <tr>
         <td>host</td>
         <td><?= $srcHost ?></td>
         <td><?= $tgtHost ?></td>
      </tr>

      <tr class="ydcclib-odd">
         <td>project</td>
         <td><?= "#" . $devProjectId . ". " . $response['project_name'] ?></td>
         <td><?= "#" . $module->project_id . ". " . $module->getProjectName($module->project_id) ?></td>
      </tr>

      <tr class="ydcclib-bottom-row">
         <td>translated field count</td>
         <td colspan="2"><?= $matching_field_count ?></td>
      </tr>

   </table>
</div>

<div class="ydcclib-row" id="ydcclib-ajax-response">
   <input type="button" value="PROCEED" onclick="YDCCLIB.executeCopyRequest()" />
</div>

<?php

function get_matching_field_count($fields){
   global $module;

   $project_id_sql = (int)$module->project_id;

   $field_count = count($fields);

   if ( !$field_count ){
      return 0;
   } else {
      $field_list = "";
      foreach ($fields as $field) {
         if ($field_list) $field_list .= ",";
         $field_list .= "'" . $field['field_name'] . "'";
      }
      return
         $module->fetchRecord("
SELECT COUNT(*) AS matching_field_count 
FROM redcap_metadata
WHERE project_id = {$project_id_sql}
  AND field_name IN({$field_list})
        ")['matching_field_count'];
   }

}