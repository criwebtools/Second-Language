<?php
/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
*/
$project_id = $_POST['project_id'];
$request = $_POST['request'];

if ( class_exists('YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage') ) {
   $module = new YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage();
} else {
   exit("Could not instantiate the Second Language class!");
}

use Yale\Yes3\Yes3Fn;

$username = $module->login_user['username'];

if ( $request == "get-languages" ) exit( get_languages() );
elseif ( $request == "save-xlat-record" ) exit( save_xlat_records() );
elseif ( $request == "get-xlat-matrix-records" ) exit( get_xlat_matrix_records() );
elseif ( $request == "get-xlat-records" ) exit( get_xlat_records() );
elseif ( $request == "get-form-metadata" ) exit( get_form_metadata() );
elseif ( $request == "get-initialization-data" ) exit( get_initialization_data() );
elseif ( $request == "copy-translations" ) exit( copy_translations() );
//else exit("bad request");

function copy_translations(){
   global $module;

   $projectType        = $module->getProjectSetting('projecttype');
   $devOnSameHost      = $module->getProjectSetting('devonsamehost');
   $devProjectHost     = $module->getProjectSetting('devprojecthost');
   $devProjectApiToken = $module->getProjectSetting('devprojectapitoken');
   $devProjectId       = $module->getProjectSetting('devprojectid');

   $project_id_sql = Yes3Fn::sql_string($module->project_id);

   $response = [];

   if ( $projectType==="P" && $devOnSameHost==="N" && $devProjectHost && $devProjectApiToken ) {

      $response = $module->getTranslationsViaAPI( $devProjectHost, $devProjectApiToken, 0 );
   }
   elseif ( $projectType==="P" && $devOnSameHost==="Y" && $devProjectId ) {

      if ( $devProjectId == $module->project_id ){
         return( "<p>ERROR: The same REDCap project is specified for development and production.</p>" );
      }

      $response = $module->getTranslationsViaModule( $devProjectId, 0 );

   }

   $xlat_backup_id = $module->get_unique_backup_id();

   $result = db_query("UPDATE ydcclib_translations SET `deleted`=1, `xlat_backup_id`='{$xlat_backup_id}' WHERE project_id={$project_id_sql} AND `deleted`=0");

   if ( !$result ) return "<br />Error: The UPDATE query failed.";

   $rows_archived = db_affected_rows();

   $message = "-- Translations copy begins --";

   $message .= "<br />Note: the backup id for this transfer is <strong>{$xlat_backup_id}</strong>.";
   $message .= "<br />Note: <strong>{$rows_archived}</strong> existing translations were archived.";

   $vSql = "";
   $translated = 0;
   foreach( $response['translations'] as $t ) {

      if ( $translated ) $vSql .= ",";

      $vSql .= "(";
      $vSql .= $project_id_sql;
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_language']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_parent']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_entity_type']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_entity_name']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_label']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_choices']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_created_by']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_created_on']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_modified_by']);
      $vSql .= "," . Yes3Fn::sql_string($t['xlat_modified_on']);
      $vSql .= ")";

      $translated++;

   }

   if ( !$translated ) {

      $rows_inserted = 0;

   } else {

      $iSql = "
insert into `ydcclib_translations`(
  `project_id`
  ,`xlat_language`
  ,`xlat_parent`
  ,`xlat_entity_type`
  ,`xlat_entity_name`
  ,`xlat_label`
  ,`xlat_choices`
  ,`xlat_created_by`
  ,`xlat_created_on`
  ,`xlat_modified_by`
  ,`xlat_modified_on`
) values {$vSql}";

      $result = db_query($iSql);

      if ( $result ){
         $rows_inserted = db_affected_rows();
      } else {
         $message .= "<br />Error: The INSERT query failed.";
         $rows_inserted = 0;
      }
   }

   $message .= "<br />Note: <strong>{$rows_inserted}</strong> translations were transferred.";

   return $message;
}

function get_form_metadata() {
   global $module;

   $language = Yes3Fn::sql_string( $_POST['language'] );
   $form_name = Yes3Fn::sql_string( $_POST['form_name'] );
   $project_id = Yes3Fn::sql_string( $_POST['project_id'] );

   if ( isset($_POST['event_id']) ) $event_id = (int)$_POST['event_id']; else $event_id = 0;
   if ( isset($_POST['record']) ) $record = $_POST['record']; else $record = "";

   $matrices = [];
   $matrix_fields = [];
   $fields = [];

   //$scripts = [];

   $matrixSql = "
SELECT m.xlat_entity_name AS `matrix_name`, m.`xlat_label` AS `matrix_header`, m.`xlat_choices` AS `matrix_choices`
  , f.`xlat_entity_name` AS `field_name`, f.`xlat_label` AS `field_label`
  , r.`field_order`, r.`element_type`
FROM ydcclib_translations m
  INNER JOIN ydcclib_translations f ON f.`project_id`=m.`project_id` AND f.`xlat_language`=m.`xlat_language` AND f.`xlat_entity_type`='field' AND f.`xlat_parent`=m.`xlat_entity_name` AND f.`deleted`=0
  INNER JOIN redcap_metadata r ON r.`project_id`=m.`project_id` AND r.`field_name`=f.`xlat_entity_name`
WHERE m.`project_id`={$project_id}
  AND m.`xlat_entity_type`='matrix'
  AND m.`xlat_language` = {$language}
/*  AND m.`deleted`=0 */
  AND r.`form_name` = {$form_name}
ORDER BY m.xlat_entity_name, r.`field_order`   
   ";

   //$scripts[] = $matrixSql;

   $mm = Yes3Fn::fetchRecords( $matrixSql );
   if ( $mm ) {
      $nMatrices = count($mm);
      $BOR = true;
      $EOR = false;
      for ($i = 0; $i < $nMatrices; $i++) {
         if ($BOR) {
            $matrix_fields = [];
            $BOR = false;
            $EOR = false;
         }
         $matrix_fields[] = ['field_name' => $mm[$i]['field_name'], 'field_label' => $mm[$i]['field_label']];
         if ($i == $nMatrices - 1) $EOR = true;
         elseif ($mm[$i]['matrix_name'] != $mm[$i + 1]['matrix_name']) $EOR = true;
         if ($EOR) {
            $matrices[] = [
               'matrix_name' => $mm[$i]['matrix_name'],
               'matrix_header' => nl2br($mm[$i]['matrix_header']),
               'matrix_choices' => getChoices($mm[$i]['matrix_choices'], $mm[$i]['element_type']),
               'matrix_fields' => $matrix_fields
            ];
            $EOR = false;
            $BOR = true;
         }
      }
   }

   $fieldSql = "
SELECT f.`xlat_entity_name` AS `field_name`, f.`xlat_label` AS `field_label`, f.`xlat_choices` AS `field_choices`
  , r.`field_order`, r.`element_type`, r.misc
FROM ydcclib_translations f
INNER JOIN redcap_metadata r ON r.`project_id`=f.`project_id` AND r.`field_name`=f.`xlat_entity_name`
WHERE f.`project_id`={$project_id}
  AND r.`form_name` = {$form_name}
  AND f.`xlat_language`={$language}
  AND f.`xlat_entity_type` = 'field'
  AND f.`xlat_parent` IS NULL
  AND f.`deleted` = 0
ORDER BY r.`field_order`   
   ";

   //$scripts[] = $fieldSql;

   $ff = Yes3Fn::fetchRecords( $fieldSql );
   $language_field = "";
   foreach ( $ff as $f ){
      $fields[] = [
         'field_name'=>$f['field_name']
         //, 'field_label'=>nl2br($f['field_label'])
         , 'field_label'=>Piping::replaceVariablesInLabel(nl2br($f['field_label']), $record, $event_id)
         //, 'field_label'=>\Piping::replaceVariablesInLabel(nl2br($f['field_label'])."<br>".$record.":".$event_id)
         , 'field_type'=>$f['element_type']
         , 'field_choices'=>getChoices($f['field_choices'], $f['element_type'])
      ];

      if ( stripos($f['misc'], '@LANGUAGE') !== false ) $language_field = $f['field_name'];

   }

   return json_encode(['matrices'=>$matrices, 'fields'=>$fields, 'language_field'=>$language_field]);
}

function getChoices( $element_enum, $element_type='' ){

   if ( !$element_enum ) return array();

   if ( $element_type==="calc" ) return array();

   $element_enum = str_replace("\\n", "\n", $element_enum);
   $parts = explode("\n", $element_enum);
   $choices = array();
   foreach ( $parts as $part ) {
      $subparts = explode(",", $part, 2);
      $choices[] = ['value'=>trim($subparts[0]), 'label'=>trim($subparts[1])];
   }
   return($choices);
}

function get_languages() {
   global $module;
   return json_encode( $module->languages );
}

function get_initialization_data() {
   global $module;

   $form_name = $_POST['form_name'];
   $form_name_sql = Yes3Fn::sql_string( $form_name );
   $project_id_sql = Yes3Fn::sql_string( $_POST['project_id'] );

   $sql = "SELECT field_name
FROM redcap_metadata
WHERE project_id = {$project_id_sql}
  AND form_name = {$form_name_sql}
  AND misc LIKE '%@LANGUAGE%'";

   $y = Yes3Fn::fetchRecord( $sql );

   if ( $y ) $language_field = $y['field_name'];
   else $language_field = "";

   return json_encode( [
      'languages' => $module->languages,
      'language_field' => $language_field,
      'completion_field' => $form_name . "_complete"
   ] );
}

function save_xlat_records() {
   global $module;
   global $project_id;
   global $username;

   $username_sql = Yes3Fn::sql_string($username);
   $timestamp = Yes3Fn::sql_timestamp_string();

   $records = $_POST['queue'];

   $scripts = [];

   $n = 0;
   $e = 0;

   foreach ( $records as $record ) {

      $n++;

      $xlat_language = Yes3Fn::sql_string($record['xlat_language']);
      $xlat_parent = Yes3Fn::sql_string($record['xlat_parent']);
      $xlat_entity_type = Yes3Fn::sql_string($record['xlat_entity_type']);
      $xlat_entity_name = Yes3Fn::sql_string($record['xlat_entity_name']);
      $xlat_label = Yes3Fn::sql_string($record['xlat_label']);
      $xlat_choices = Yes3Fn::sql_string($record['xlat_choices']);

      $scripts = [];

      $sqlfetch = "SELECT xlat_id FROM ydcclib_translations WHERE project_id={$project_id} AND xlat_language={$xlat_language} AND xlat_entity_type={$xlat_entity_type} AND xlat_entity_name={$xlat_entity_name} AND `deleted`=0";
      $y = Yes3Fn::fetchRecord($sqlfetch);
      if ( $y['xlat_id'] ) {

         $sql = "UPDATE ydcclib_translations SET"
            ." xlat_parent={$xlat_parent}, xlat_label={$xlat_label}, xlat_choices={$xlat_choices}, xlat_modified_by={$username_sql}, xlat_modified_on={$timestamp}"
            ." WHERE  xlat_id = {$y['xlat_id']}"
         ;

         $scripts[] = $sql;


      } else {

         $sql = "INSERT INTO ydcclib_translations (project_id, xlat_language, xlat_parent, xlat_entity_type, xlat_entity_name, xlat_label, xlat_choices, xlat_created_by, xlat_created_on) VALUES ("
            . "{$project_id}, {$xlat_language}, {$xlat_parent}, {$xlat_entity_type}, {$xlat_entity_name}, {$xlat_label}, {$xlat_choices}"
            . ", {$username_sql}, {$timestamp})";
      }

      $rc = Yes3Fn::query($sql);

      if ( $rc != 1 ) $e++;
   }

   return( json_encode( array("query_count"=>$n, "errors"=>$e, "scripts"=>$scripts) ) );
}

function get_xlat_records() {
   global $module;
   global $project_id;

   $field_name = Yes3Fn::sql_string($_POST['field_name']);

   $sql = "
SELECT x.`xlat_entity_name`, x.`xlat_language`, x.`xlat_label`, x.`xlat_choices`
FROM ydcclib_translations `x`
WHERE x.`project_id`={$project_id} 
  AND x.xlat_language<>'primary'
  AND x.`xlat_entity_type`='field' 
  AND x.`xlat_entity_name`={$field_name}
  AND x.`deleted` = 0
ORDER BY x.`xlat_language`
   ";

   $yy = Yes3Fn::fetchRecords($sql);

   return( json_encode($yy) );
}

function get_xlat_matrix_records() {
   global $module;
   global $project_id;

   $grid_name = Yes3Fn::sql_string($_POST['grid_name']);

   $sql = "
SELECT x.`xlat_entity_type`, x.`xlat_entity_name`, x.`xlat_language`, x.`xlat_label`, x.`xlat_choices`
FROM ydcclib_translations `x`
WHERE x.`project_id`={$project_id} 
  AND x.xlat_language<>'primary'
  AND ((x.`xlat_entity_type`='field' AND x.`xlat_parent`={$grid_name}) OR (x.`xlat_entity_type`='matrix' AND x.`xlat_entity_name`={$grid_name}))
  AND x.`deleted` = 0
ORDER BY x.`xlat_entity_type`, x.`xlat_entity_name`, x.`xlat_language`   
   ";

   $yy = Yes3Fn::fetchRecords($sql);

   return( json_encode($yy) );
}
