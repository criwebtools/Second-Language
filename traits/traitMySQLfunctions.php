<?php
namespace YDCCLib\YDCCLibSecondLanguage;
/*
 * August 2020: rewritten to use native REDCap functions throughout.
 */

/*
 * Table to hold debug log messages. Must be created by dba
 * and have columns project_id(INT), debug_message_category(VARCHAR(100)), debug_message(TEXT)
 */
define('DEBUG_LOG_TABLE', "ydcclib_debug_messages");

trait mysqlDb {

   // calls the generic REDCap query function, which is located in Config/init_functions.php
   // db_query returns mysqli_query() on success, or triggers a fatal redcap fail
   public static function runQuery($sql) {
      return db_query($sql);
   }

   // like runQuery, but returns identity value
   public static function runInsertQuery($sql) {
      $stmt = db_query($sql);
      if ($stmt == false) {
         return 0;
      } else {
         return db_insert_id();
      }
   } // runInsertQuery

   public static function fetchValue($sql) {
      $stmt = db_query($sql);
      if ( !$stmt ){
         return null;
      } else {
         $x = mysqli_fetch_array($stmt, MYSQLI_NUM);
         if ( !$x ) return null;
         else return $x[0];
      }
   }

   public static function fetchRecord($sql) {
      $r = array();
      $stmt = db_query($sql);
      if ($stmt) {
         $r = db_fetch_assoc($stmt);
         db_free_result($stmt);
      }
      return $r;
   }

   public static function fetchRecords($sql) {
      $r = array();
      $stmt = db_query($sql);
      if ($stmt) {
         while ($row = db_fetch_assoc($stmt)) {
            $r[] = $row;
         }
         db_free_result($stmt);
      }
      return $r;
   }

   public static function mysql_string($x) {
      if (strlen($x) == 0) {
         return "null";
      } else if (is_numeric($x)) {
         return "'" . $x . "'";
      } else {
         return "'" . db_real_escape_string($x) . "'";
      }
   }

   public static function mysql_datetime_string($x) {
      if (!$x) {
         return "null";
      } else {
         return "'" . strftime("%F %T", strtotime($x)) . "'";
      }
   }

   public static function mysql_date_string($x) {
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

   public static function mysql_timestamp_string() {
      return "'" . strftime("%F %T") . "'";
   }

   public static function tableExists($table_name){
      $dbname = self::fetchValue("SELECT DATABASE() AS DB");
      if ( !$dbname ) return false;
      $sql = "SELECT COUNT(*) FROM information_schema.tables"
            ." WHERE table_schema=".self::mysql_string($dbname)
            ." AND table_name=".self::mysql_string($table_name)
            ;
      return self::fetchValue($sql);
   }

   public static function logDebugMessage($project_id, $msg, $msgcat="") {

      if ( !self::tableExists(DEBUG_LOG_TABLE) ) return false;

      $sql = "INSERT INTO `".DEBUG_LOG_TABLE."` (project_id, debug_message, debug_message_category) VALUES ("
         .self::mysql_string($project_id).","
         .self::mysql_string($msg).","
         .self::mysql_string($msgcat)
         .");";

      return self::runQuery($sql);
   }

}
