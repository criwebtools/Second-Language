<?php
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/
if ( class_exists('YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage') ) {
   $module = new YDCCLib\YDCCLibSecondLanguage\YDCCLibSecondLanguage();
} else {
   exit("Could not instantiate the Second Language class!");
}

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

print "<div class='code'>".nl2br(str_replace(' ', '&nbsp;', $module->get_create_table_sql()))."</div>";
