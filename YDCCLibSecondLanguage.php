<?php

/*
 *
 */

namespace YDCCLib\YDCCLibSecondLanguage;

require "autoload.php";

use Yale\Yes3\Yes3Fn;

class YDCCLibSecondLanguage extends \ExternalModules\AbstractExternalModule {

   public $languages = array();
   public $login_user = array();
   public $message = "";
   public $project_id = 0;
   public $form_name = "";
   private $formInitializationDataJSON = "";
   private $formTranslationsJSON = "";
   private $super_user = 0;

   public function __construct() {
      parent::__construct();

      if ( defined('SUPER_USER') ){
         $this->super_user = SUPER_USER;
      }

      if ( defined('USERID') ) {
         $this->get_login_user();
      }

      // project-context initializations
       if ( defined('PROJECT_ID') ) {
         $this->project_id = PROJECT_ID;
         $this->getLanguages();
         if ( !$this->getProjectSetting('primary-language')){
            $this->setPrimaryLanguage("");
         }
      }
   }

   public function setPrimaryLanguage(string $languageName): string
   {
      if ( !$languageName ){
         $languageName = Yes3Fn::fetchValue("SELECT project_language FROM redcap_projects WHERE project_id=?", [$this->project_id]);
      }
      $this->setProjectSetting('primary-language', $languageName);
      return $languageName;
   }

   /*
    * HOOKS
    */

   // field editor prep
   function redcap_every_page_top( $project_id ) {
      if(strpos($_SERVER['REQUEST_URI'], 'online_designer.php') !== false && isset($_GET['page'])){
         $js = file_get_contents($this->getModulePath() . 'js/ydcclib_editor.js');
         ?>
         <link rel="stylesheet" type="text/css" href="<?php echo $this->getUrl("css/ydcclib.css?v=" . mt_rand(1, 32767)) ?>">
         <script type="text/javascript"><?php echo $this->preprocessJs($js) ?></script>
         <?php
      }
   }

   // data entry form prep
   function redcap_data_entry_form ( $project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1 ) {

      if ( !$record ) $record = "";
      if ( !$event_id ) $event_id = 0;

      $this->form_name = $instrument;

      $this->project_id = $project_id;

      $this->getFormInitializationDataJSON( $project_id, $instrument );

      $this->getFormTranslationsJSON( $project_id, $record, $instrument, $event_id );

      $js = file_get_contents($this->getModulePath() . 'js/ydcclib_form.js');

      ?>
      <link rel="stylesheet" type="text/css" href="<?php echo $this->getUrl("css/ydcclib.css?v=" . mt_rand(1, 32767)) ?>">
      <script type="text/javascript"><?php echo $this->preprocessJs($js, $record, $event_id) ?></script>
      <?php
   }

   // things to do when the module is first enabled for a REDCap implementation
   // Mainly, create the tables needed by the module
   // shut down for now as foolhardy: sysadmin should run the provided query
   function redcap_module_system_enable($version) {
      // create or update system tables
      //$rc = $this->setup_createTableTranslations($version);
   }

   // things to do when the module is first enabled for a project (i.e., project setup actions)
   function redcap_module_project_enable($version, $project_id) {

   }

   /*
    * Links are for admins only
    * super-user rights required
    * copy_from_dev: only for production projects
    */

   function redcap_module_link_check_display($project_id, $link) {
      if ( $this->super_user
         && $this->getProjectSetting('projecttype')==='P'
         && stripos($link['url'], 'copy_from_dev')!==FALSE
      ) return $link;
      elseif ( $this->super_user
         && stripos($link['url'], 'copy_from_dev')===FALSE
      ) return $link;
      else return null;
   }

   /*
    * PRIVATE
    */

   private function preprocessJs( $js, $record="", $event_id=0 ) {
      $t = str_replace('YDCCLIB_AJAX_URL', $this->getUrl("ajax/ydcclib_services.php?pid=".$this->project_id ), $js);
      $t = str_replace('YDCCLIB_CLOSE_BUTTON_IMAGE_URL', $this->getUrl("images/close-button.png"), $t);
      $t = str_replace('YDCCLIB_CSS_URL', $this->getUrl("css/ydcclib.css"), $t);
      $t = str_replace('YDCCLIB_PROJECT_ID', (string)$this->project_id, $t);
      $t = str_replace('YDCCLIB_USERNAME', $this->login_user['username'], $t);
      $t = str_replace('YDCCLIB_FORM_NAME', $this->form_name, $t);
      $t = str_replace('YDCCLIB_RECORD', $record, $t);
      $t = str_replace('YDCCLIB_EVENT_ID', $event_id, $t);
      $t = str_replace('YDCCLIB_FORM_INITIALIZATION_DATA', $this->formInitializationDataJSON, $t);
      $t = str_replace('YDCCLIB_FORM_TRANSLATIONS', $this->formTranslationsJSON, $t);
      $t = str_replace('YDCCLIB_PRIMARY_LANGUAGE_NAME', $this->getProjectSetting('primary-language'), $t);
      return($t);
   }

   private function getLanguages() {
      $this->languages = [];
      // v1.0.1 fix to prevent exception in PHP 8
      $langs = $this->getProjectSetting('ydcclib-language');
      if ( $langs ) {
         for ($i = 0; $i < count($langs); $i++) {
            $this->languages[] = strtolower($langs[$i]);
         }
      }
      /*
       * BREAKS IN FRAMEWORK 5:
      $settings = $this->getProjectSettings($this->project_id);
      foreach ( $settings['ydcclib-language']['value'] as $language) {
         $this->languages[] = strtolower($language);
      }
      */
   }

   public function getProjectName( $project_id ){
      return Yes3Fn::fetchRecord(
            "SELECT project_name, app_title FROM redcap_projects WHERE project_id=".Yes3Fn::sql_string($project_id)
      )['app_title'];
   }

   // populates login_user
   private function get_login_user() {
      $sql = "
SELECT LOWER(u.`username`) AS `username`, u.`user_lastname`, u.`user_firstname`, CONCAT(u.`user_firstname`, ' ', u.`user_lastname`) as user_fullname
  , u.`user_email`
FROM redcap_user_information u
WHERE LOWER(u.`username`)='".strtolower(Yes3Fn::getREDCapUserId())."'";
      ;
      $this->login_user = Yes3Fn::fetchRecord($sql);
   }

   /*
    * PUBLIC
    */

   /*
    * Returns project_id and user name from token
    *
    * verifies that
    *   (1) token is valid
    *   (2) user account has not expired
    *   (3) user has API export privileges
    *       either through user rights, user role or superuser status
    *
    */
   public function getProjectAndUserFromToken( $token ){

      $token_sql = Yes3Fn::sql_string( $token );

      $sql = "
SELECT u.`project_id`, u.`username`
FROM redcap_user_rights u
  INNER JOIN redcap_user_information ui ON ui.`username`=u.`username`
  LEFT JOIN redcap_user_roles r ON r.`role_id`<=>u.`role_id`
WHERE u.`api_token`={$token_sql}
  AND ui.`user_expiration` IS NULL
  AND (r.`api_export`<=>1 OR u.`api_export`<=>1 OR ui.`super_user`<=>1)       
      ";

      $y = Yes3Fn::fetchRecord($sql);

      if ( !$y ) return ['project_id'=>0, 'username'=>""];
      else return $y;
   }

   /*
    * Extracts all of the second lang metadata for the specified project
    */
   public function getTranslationsForProject( $project_id, $count_only=0 ){

      $project_id_sql = Yes3Fn::sql_string($project_id);

      $project_name = $this->getProjectName( $project_id );

      $fields = Yes3Fn::fetchRecords("
SELECT DISTINCT xlat_entity_name AS `field_name` FROM ydcclib_translations
WHERE project_id={$project_id_sql}
  AND xlat_entity_type = 'field'
  AND xlat_language <> 'primary'
  AND deleted=0    
        ");

      $count = Yes3Fn::fetchRecord("
SELECT COUNT(*) AS `count` FROM ydcclib_translations 
WHERE project_id={$project_id_sql}
  AND xlat_entity_type = 'field'
  AND xlat_language <> 'primary'
  AND deleted=0         
         ")['count'];

      if ( $count_only ){

         $translations = null;

      } else {

         $translations = Yes3Fn::fetchRecords( "
SELECT * FROM ydcclib_translations 
WHERE project_id={$project_id_sql}
  AND deleted=0
        ");

      }

      return [
            'project_name' => $project_name,
            'count' => $count,
            'fields' => $fields,
            'translations' => $translations
      ];

   }

   private function getFormInitializationDataJSON( $project_id, $form_name ) {

      $form_name_sql = Yes3Fn::sql_string( $form_name );
      $project_id_sql = Yes3Fn::sql_string( $project_id );

      $sql = "SELECT field_name
FROM redcap_metadata
WHERE project_id = {$project_id_sql}
  AND form_name = {$form_name_sql}
  AND misc LIKE '%@LANGUAGE%'";

      $y = Yes3Fn::fetchRecord( $sql );

      if ( $y ) $language_field = $y['field_name'];
      else $language_field = "";

      $this->formInitializationDataJSON = json_encode( [
         'languages' => $this->languages,
         'language_field' => $language_field,
         'completion_field' => $form_name . "_complete"
      ] );

   }

   private function getFormTranslationsJSON($project_id, $record, $form_name, $event_id) {

      $form_name_sql = Yes3Fn::sql_string( $form_name );
      $project_id_sql = (int) $project_id;

      $event_id = (int)$event_id;
      if ( !$record ) $record = "";

      $formTranslations = [];

      $theLanguages = array_merge( array("primary"), $this->languages);

      foreach ( $theLanguages as $language ) {

         $language_sql = Yes3Fn::sql_string( $language );

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
WHERE m.`project_id`={$project_id_sql}
  AND m.`xlat_entity_type`='matrix'
  AND m.`xlat_language` = {$language_sql}
/*  AND m.`deleted`=0 */
  AND r.`form_name` = {$form_name_sql}
ORDER BY m.xlat_entity_name, r.`field_order`   
   ";

         //$scripts[] = $matrixSql;

         $mm = Yes3Fn::fetchRecords($matrixSql);
         if ( $mm ){
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
                     'matrix_choices' => self::getChoices($mm[$i]['matrix_choices'], $mm[$i]['element_type']),
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
WHERE f.`project_id`={$project_id_sql}
  AND r.`form_name` = {$form_name_sql}
  AND f.`xlat_language`={$language_sql}
  AND f.`xlat_entity_type` = 'field'
  AND f.`xlat_parent` IS NULL
  AND f.`deleted` = 0
ORDER BY r.`field_order`   
   ";

         //$scripts[] = $fieldSql;

         $ff = Yes3Fn::fetchRecords($fieldSql);
         $language_field = "";
         foreach ($ff as $f) {
            $fields[] = [
               'field_name' => $f['field_name']
               //, 'field_label'=>nl2br($f['field_label'])
               , 'field_label' => \Piping::replaceVariablesInLabel(nl2br($f['field_label']), $record, $event_id)
               //, 'field_label'=>\Piping::replaceVariablesInLabel(nl2br($f['field_label'])."<br>".$record.":".$event_id)
               , 'field_type' => $f['element_type']
               , 'field_choices' => self::getChoices($f['field_choices'], $f['element_type'])
            ];

            if (stripos($f['misc'], '@LANGUAGE') !== false) $language_field = $f['field_name'];

         }

         $formTranslations[$language] = ['matrices'=>$matrices, 'fields'=>$fields, 'language_field'=>$language_field];
      } // language

      $this->formTranslationsJSON = json_encode( $formTranslations );

   }

   private static function getChoices( $element_enum, $element_type='' ){

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

   private function getEndpointApiUrl( $host ){
      if ( substr($host, -1) !== "/" ) $host .= "/";
      return $host . "api/?type=module&prefix=ydcclib_second_language&page=plugins/get_translations&NOAUTH";
   }

   public function getTranslationsViaAPI($host, $token, $count_only=0) {

      $url = $this->getEndpointApiUrl( $host );

      $data = array(
         'token' => $token,
         'count_only' => $count_only
      );

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_VERBOSE, 0);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_AUTOREFERER, true);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));

      $response = (string)curl_exec($ch);

      if (curl_errno($ch)) {
         $error_message = curl_error($ch);
      }

      curl_close($ch);

      if ( isset($error_message) ) return $this->badGetXOutcome($error_message);

      if ( !$this->isJson($response) ) return $this->badGetXOutcome( strip_tags($response) );

      return json_decode($response, true);
   }

   public function getTranslationsViaModule( $project_id, $count_only=0 ){

      $response = $this->getTranslationsForProject( $project_id, $count_only );

      return
         [
            'message'=>"success",
            'project_id' => $project_id,
            'project_name' => $response['project_name'],
            'count' => $response['count'],
            'fields' => $response['fields'],
            'translations' => $response['translations']
         ]
      ;

   }

   function badGetXOutcome($msg){
      return 
         [
            'message' => $msg,
            'project_id' => null,
            'project_name' => null,
            'count' => 0,
            'translations' => null
         ]
      ;
   }

   function isJson($string) {
      // psalm reports a 'not used' error but 'tis okay
      json_decode($string);
      return (json_last_error() == JSON_ERROR_NONE);
   }

   public function get_unique_backup_id() {

      $xlat_backup_id_root = strftime("%Y-%m-%d");

      $sql = "
      SELECT 1+IFNULL(MAX(SUBSTR(xlat_backup_id, -3)),0) AS xlat_backup_id_index
      FROM ydcclib_translations
      WHERE deleted=1 AND LENGTH(xlat_backup_id)=14
      AND xlat_backup_id LIKE '{$xlat_backup_id_root}%'
      ";

      $xlat_backup_id_index = Yes3Fn::fetchRecord($sql)['xlat_backup_id_index'];

      return $xlat_backup_id_root . "." . str_pad($xlat_backup_id_index, 3, '0', STR_PAD_LEFT);
   }


   public function get_create_table_sql() {

      // collation should match the metadata table
      $collation = Yes3Fn::fetchRecord("
         SELECT TABLE_COLLATION 
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME = 'redcap_metadata'
      ")['TABLE_COLLATION'];

      return "
CREATE TABLE IF NOT EXISTS ydcclib_translations
(
    xlat_id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT                NOT NULL,
    xlat_language    VARCHAR(64)        NULL,
    xlat_parent      VARCHAR(64)        NULL,
    xlat_entity_type CHAR(10)           NULL,
    xlat_entity_name VARCHAR(100)       NULL,
    xlat_label       MEDIUMTEXT         NULL,
    xlat_choices     MEDIUMTEXT         NULL,
    xlat_created_by  VARCHAR(64)        NULL,
    xlat_created_on  DATETIME           NULL,
    xlat_modified_by VARCHAR(64)        NULL,
    xlat_modified_on DATETIME           NULL,
    deleted          SMALLINT DEFAULT 0 NULL,
    xlat_backup_id   CHAR(16)           NULL
)
    COLLATE = {$collation};

CREATE INDEX project_id
    ON ydcclib_translations (project_id);

CREATE INDEX project_id__entity_type__parent
    ON ydcclib_translations (project_id, xlat_entity_type, xlat_parent);

CREATE INDEX project_id__language
    ON ydcclib_translations (project_id, xlat_language);

CREATE INDEX project_id__language__entity_type
    ON ydcclib_translations (project_id, xlat_language, xlat_entity_type);

CREATE INDEX project_id__language__entity_type__entity_name
    ON ydcclib_translations (project_id, xlat_language, xlat_entity_type, xlat_entity_name);

CREATE INDEX xlat_entity_name
    ON ydcclib_translations (xlat_entity_name);

CREATE INDEX xlat_entity_type
    ON ydcclib_translations (xlat_entity_type);

CREATE INDEX xlat_language
    ON ydcclib_translations (xlat_language);

CREATE INDEX xlat_parent
    ON ydcclib_translations (xlat_parent);
      ";
   }

}



?>