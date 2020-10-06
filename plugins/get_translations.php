<?php

$dev_project_id = 0;

if ( class_exists('YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage') ) {
   $module = new YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage();
} else {
   badOutcome("Could not instantiate the Second Language class!");
}

/*
 * If the EM page is called through the API, the constant API_EXTMOD will have been defined.
 */
if ( !defined("API_EXTMOD") ) {
   badOutcome("Invalid API call.");
}

if ( !isset($_POST['token']) ) {
   badOutcome( "No token supplied." );
}

if ( !$dev_project_id = $module->getProjectAndUserFromToken($_POST['token'])['project_id'] ) {
   badOutcome("Bad token");
}

$response = $module->getTranslationsForProject($dev_project_id, (int)$_POST['count_only']);

exit(
   json_encode(
      [
         'message'=>"success",
         'project_id' => $dev_project_id,
         'project_name' => $response['project_name'],
         'count' => $response['count'],
         'fields' => $response['fields'],
         'translations' => $response['translations']
      ]
   )
);

function badOutcome( $msg ){
   exit(
      json_encode(
         [
            'message'=>$msg,
            'project_id' => null,
            'project_name' => null,
            'count' => 0,
            'fields' => null,
            'translations' => null
         ]
      )
   );
}



