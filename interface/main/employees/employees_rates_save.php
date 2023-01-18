<?php
require_once("../../globals.php");
require_once("$srcdir/api.inc");
require_once("$srcdir/forms.inc");

use OpenEMR\Common\Csrf\CsrfUtils;
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
  CsrfUtils::csrfNotVerified();
}
define('EMPLOYEE_RATES_TABLE','user_employee_rates');
define('EMPLOYEE_FORM_REDIRECT', $rootdir . '/main/employees/employee_form.php');


$data_saved = false;
if( isset($_POST['employee_rates_save']) ) {

    $user_id        = $_POST['user_id'];
    $pc_catids      = $_POST['pc_catid'];
    $rates          = $_POST['rate'];
    

    /* CHECK if there are any rates associated with the employee */
    $rate_query = "SELECT * FROM ".EMPLOYEE_RATES_TABLE." WHERE user_id=".$user_id;
    $rate_result = sqlStatement($rate_query);

    /* INSERT FIELDS */
    if($rate_result->_queryID->num_rows == 0) {
      
      if( $rates = array_filter($_POST['rate']) ) {
        foreach($rates as $pc_catid=>$amount) {
          if($amount) {
            $sets = "user_id = ?,
              pc_catid = ?,
              rate = ?";
            sqlStatement(
              "INSERT INTO ".EMPLOYEE_RATES_TABLE." SET " . $sets,
              [
                $user_id,
                $pc_catid,
                $amount
              ]
            );
          }
        }
      }

      /* CUSTOM CODE */
      if( isset($_POST['customcode']) ) {
        $custom_codes = $_POST['customcode'];
        $custom_code_amount = $_POST['customcodeamount'];
        foreach( $custom_codes as $k=>$codeName ) {
          $sets = "user_id = ?, custom_code = ?, rate = ?";
          sqlStatement(
              "INSERT INTO ".EMPLOYEE_RATES_TABLE." SET " . $sets,
              [
                $user_id,
                $codeName,
                $custom_code_amount[$k]
              ]
            );
        }
      }


    } else {

      /* UPDATE FIELDS */
      $date_modified = date('Y-m-d H:i:s');
      foreach($rates as $pc_catid=>$amount) {
        $amount = preg_replace('/\s+/','',$amount); /* strip out all spaces */

        /* Check pc_catid and user_id */
        $existing = sqlStatement("SELECT * FROM ".EMPLOYEE_RATES_TABLE." WHERE user_id=".$user_id." AND pc_catid=".$pc_catid);
        if( sqlNumRows($existing) ) {
          
          $sets = "rate = ?,
              date_modified = ?";
          $fieldValues = array($amount,$date_modified);
          sqlStatement("UPDATE ".EMPLOYEE_RATES_TABLE." SET ".$sets." WHERE user_id=".$user_id." AND pc_catid=".$pc_catid, $fieldValues);
        
        } else {

          /* item not exists, insert new rate */
          if($amount) {
            $sets = "user_id = ?,
              pc_catid = ?,
              rate = ?";
            sqlStatement(
              "INSERT INTO ".EMPLOYEE_RATES_TABLE." SET " . $sets,
              [
                $user_id,
                $pc_catid,
                $amount
              ]
            );
          }

        } 
      }


      /* UPDATE CUSTOM CODE */
      if( isset($_POST['customcode']) ) {
        $custom_codes = $_POST['customcode'];
        $custom_code_amount = $_POST['customcodeamount'];
        foreach( $custom_codes as $k=>$codeName ) {
          $customAmount = $custom_code_amount[$k];

          /* Check custom_code and user_id */
          $customExist = sqlStatement("SELECT * FROM ".EMPLOYEE_RATES_TABLE." WHERE user_id=".$user_id." AND custom_code='".$codeName."'");
          if( sqlNumRows($customExist) ) {
            $sets = "rate = ?,
              date_modified = ?";
            $xfieldValues = array($customAmount,$date_modified);
            sqlStatement("UPDATE ".EMPLOYEE_RATES_TABLE." SET ".$sets." WHERE user_id=".$user_id." AND custom_code='".$codeName."'", $xfieldValues);
          
          } else {

            $sets = "user_id = ?, custom_code = ?, rate = ?";
            sqlStatement(
                "INSERT INTO ".EMPLOYEE_RATES_TABLE." SET " . $sets,
                [
                  $user_id,
                  $codeName,
                  $customAmount
                ]
              );

          }

        }
      }


    }

}

formHeader("Redirecting....");
$index = EMPLOYEE_FORM_REDIRECT . '?id='.$user_id.'&view=1&tab=2&ratesave=1';
header("Location: {$index}");
exit;




