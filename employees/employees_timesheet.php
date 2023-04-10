<?php

/**
 *  Daily Summary Report. (/interface/reports/daily_summary_report.php)
 *
 *  This report shows date wise numbers of the Appointments Scheduled,
 *  New Patients, Visited patients, Total Charges, Total Co-pay and Balance amount for the selected facility & providers wise.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rishabh Software
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2016 Rishabh Software
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


$root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
$baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

/* PATH: /openemr/interface */
define( 'OPENEMR_ROOT', dirname(__FILE__, 4) . '/' );
define( 'INTERFACE_DIR', dirname(__FILE__, 3) . '/' );
define( 'SITE_DIR', dirname(__FILE__, 6) . '/' );
define( 'SITE_URL', $root . basename(OPENEMR_ROOT) . '/' );


require_once(INTERFACE_DIR . "globals.php");
require_once "$srcdir/options.inc.php";
require_once "$srcdir/appointments.inc.php";
//require_once(dirname(__FILE__) . '/Paginator.class.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\FacilityService;

if (!empty($_POST)) {
  if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
  }
}

$facilityService = new FacilityService();
$currentUSERID = (isset($_SESSION['authUserID'])) ? $_SESSION['authUserID'] : 0;

/* CREATE TABLE FOR EMPLOYEE RATES IF NOT EXISTS */
$new_table_name = 'employee_timesheet';
$new_table_query = "SHOW TABLES LIKE '".$new_table_name."'";
$new_table_res = sqlStatement($new_table_query);
if($new_table_res->_queryID->num_rows == 0) {
  $new_table = "CREATE TABLE IF NOT EXISTS ".$new_table_name."(
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `type_id` bigint(20) DEFAULT NULL,
    `type` varchar(255) DEFAULT NULL,
    `comments` longtext,
    `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `datemodified` datetime DEFAULT NULL,
    `userid` bigint(20) DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB";
  sqlStatement($new_table);
} else {
  // $new_table = "ALTER TABLE `employee_timesheet` CHANGE `id` `id` BIGINT(20) NOT NULL AUTO_INCREMENT";
  // sqlStatement($new_table);
} 

?>
<html>
  <head>
    <title><?php echo xlt('Employee Timesheet'); ?></title>
    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>
    <script>
      $(function () {
        $('.datepicker').datetimepicker({
          <?php $datetimepicker_timepicker = false; ?>
          <?php $datetimepicker_showseconds = false; ?>
          <?php $datetimepicker_formatInput = true; ?>
          <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
          <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
        });
      });
    </script> 
  </head>
  <body>
    <?php
      $csrf_token_form = attr(CsrfUtils::collectCsrfToken());
      include_once($_SERVER["DOCUMENT_ROOT"].'/interface/reports/employees/result.php'); 
    ?>
  </body>
</html>

