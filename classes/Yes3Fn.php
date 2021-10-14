<?php

namespace Yale\Yes3;

/*
 * Table to hold debug log messages. Must be created by dba, see logDebugMessage() below.
 */
define('DEBUG_LOG_TABLE', "ydcclib_debug_messages");

use ExternalModules\ExternalModules;

class Yes3Fn {

   public static function helloWorld(): string
   {
      return "hello world!";
   }

   # too bad this logic is private in ExternalModules
   public static function getREDCapProjectId():int
   {
      if (defined('PROJECT_ID')) {
         return (int) PROJECT_ID;
      }
      if (isset($_GET['pid'])) {
         return (int) $_GET['pid'];
      }
      return 0;
   }

   public static function getREDCapUserId():string
   {
      if (defined('USERID')) {
         return (string)USERID;
      }
      return "";
   }

   public static function query(string $sql, array $parameters = [])
   {
      // remove any trailing semicolon, can cause fatal exception up the line
      $sql = trim($sql, " ;\n\r\t\v\0");
      return ExternalModules::query($sql, $parameters);
   }

   // like runQuery, but returns identity value
   public static function insertQuery(string $sql, array $parameters = []) 
   {
      $result = self::query($sql, $parameters);

      if ( $result ){
         return self::query("SELECT LAST_INSERT_ID()");
      }

      return 0;
   } 

   public static function yieldRecords(string $sql, array $parameters = [])
   {
      $resultSet = self::query($sql, $parameters);
      if ( $resultSet->num_rows > 0 ) {
         while ($row = $resultSet->fetch_assoc()) {
            yield $row;
         }
      }
   }

   public static function fetchRecords(string $sql, array $parameters = [])
   {
      $rows = [];
      $resultSet = self::query($sql, $parameters);
      if ( $resultSet->num_rows > 0 ) {
         while ($row = $resultSet->fetch_assoc()) {
            $rows[] = $row;
         }
      }

      return $rows;
   }

   private static function sql_limit_1( $sql )
   {
      if ( stripos($sql, "LIMIT 1") === false ) {
         return $sql . " LIMIT 1";
      } else {
         return $sql;
      }
   }

   public static function fetchRecord($sql, $parameters = [])
   {
      return self::query(self::sql_limit_1($sql), $parameters)->fetch_assoc();
   }

   public static function fetchValue($sql, $parameters = [])
   {
      return self::query(self::sql_limit_1($sql), $parameters)->fetch_row()[0];
   }

   public static function tableExists(string $table_name):bool
   {
      $dbname = self::fetchValue("SELECT DATABASE() AS DB");
      if ( !$dbname ) return false;
      $sql = "SELECT COUNT(*) FROM information_schema.tables"
         ." WHERE table_schema=?"
         ." AND table_name=?"
      ;
      return (self::fetchValue($sql, [$dbname, $table_name]));
   }

   public static function getREDCapEventIdForField( string $field_name, int $project_id=null ): int
   {
      if ( !$project_id ){
         $project_id = self::getREDCapProjectId();
      }

      $sql = "
SELECT e.`event_id`
FROM redcap_metadata m
  INNER JOIN redcap_events_arms a ON a.`project_id`=m.`project_id`
  INNER JOIN redcap_events_metadata e ON e.`arm_id`=a.`arm_id`
  INNER JOIN redcap_events_forms f ON f.`form_name`=m.`form_name` AND f.`event_id`=e.`event_id`
WHERE m.`project_id`=? AND m.`field_name`=? LIMIT 1
";

      return (int) self::fetchValue($sql, [$project_id, $field_name]);
   }

   public static function getREDCapValue( string $record, string $field_name, int $project_id=null, int $event_id=null, int $instance=1 )
   {
      if ( !$project_id ){
         $project_id = self::getREDCapProjectId();
      }
      if ( !$event_id ) {
         $event_id = self::getREDCapEventIdForField($field_name, $project_id);
      }
      $sql = "
SELECT `value` 
FROM `redcap_data` 
WHERE `project_id`=? AND `event_id`=? AND `record`=? AND `field_name`=? AND ifnull(instance, 1)=? LIMIT 1
";
      return self::fetchValue($sql, [$project_id, $event_id, $record, $event_id, $instance]);
   }

   /**
    * The sql_ functions will be retired
    * once parameterized queries are fully implemented.
    *
    */

   public static function sql_datetime_string($x)
   {
      if (!$x) {
         return "null";
      } else {
         return "'" . strftime("%F %T", strtotime($x)) . "'";
      }
   }

   public static function sql_date_string($x)
   {
      if (!$x) {
         return "null";
      } else {
         $d = strtotime($x);
         // if this didn't work, could be due to mm-dd-yyyy which doesn't fly
         if (!$d) {
            $date = str_replace('-', '/', $x);
            $d = strtotime($date);
         }
         if ($d) {
            return "'" . strftime("%F", $d) . "'";
         } else {
            return "null";
         }
      }
   }

   public static function sql_timestamp_string()
   {
      return "'" . strftime("%F %T") . "'";
   }

   public static function sql_string($x)
    {
       if (strlen($x) == 0) {
          return "null";
       } else if (is_numeric($x)) {
          return "'" . $x . "'";
       } else {
          return "'" . db_real_escape_string($x) . "'";
       }
   }
 
   /*
    * LOGGING DEBUG INFO
    * Call this function to log messages intended for debugging, for example an SQL statement.
    * The log database must exist and its name stored in the DEBUG_LOG_TABLE constant.
    * Required columns: project_id(INT), debug_message_category(VARCHAR(100)), debug_message(TEXT).
    * (best to add an autoincrement id field). Sample table-create query:
    *

         CREATE TABLE ydcclib_debug_messages
         (
             debug_id               INT AUTO_INCREMENT PRIMARY KEY,
             project_id             INT                                 NULL,
             debug_message_category VARCHAR(100)                        NULL,
             debug_message          TEXT                                NULL,
             debug_timestamp        TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
         );

    */

    public static function logDebugMessage(int $project_id, string $msg, string $msgcat="") {

      if ( !self::tableExists(DEBUG_LOG_TABLE) ) return false;

      $sql = "INSERT INTO `".DEBUG_LOG_TABLE."` (project_id, debug_message, debug_message_category) VALUES (?,?,?)";

      return self::query($sql, [$project_id, $msg, $msgcat]);
   }

   public static function REDCapAPI( string $host, array $params, string $module_prefix="", string $module_page="", bool $noauth=false )
   {

      /*
       * The regexp just removes any trailing slash from the provided host.
       */
      $url = preg_replace('^/$^', '', $host) . "/api/";

      $error_message = "";

      /*
       * API endpoint call?
       */
      if ( $module_prefix ){

         $url .= "?type=module&prefix={$module_prefix}&page={$module_page}";

         if ( $noauth ) $url .= "&NOAUTH";

      }

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
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
         $error_message = curl_error($ch);
      }

      curl_close($ch);

      if ( $error_message ) exit( "error: " . $error_message );

      return $response;
   }

}
