<?php
require_once(__DIR__ . '/../../globals.php');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');

//Check access control
if (!acl_check('patients', 'appt', '', array('write','wsome'))) {
    die(xl('Access not allowed'));
}


if( ($_POST['form_pid']) && ($_POST['form_category']) ){

    $pid        = $_POST['form_pid'];
    $category   = $_POST['form_category'];
    $column_tx  = '';
    $column     = '';
    $table      = '';
    $review     = '';
    $result     = true;

    echo $pid;
    die();

    $counselor_arr = [38,39,40,41,42];
    if(in_array($category, $counselor_arr)){  // Counseling Treatment Plan
        $table      = 'form_counselor_treatment_plan';
        $column     = 'Counseling_Treatment_Plan';
        $sql_plan   = "SELECT day_review FROM {$table} WHERE pid = ? ORDER BY id DESC LIMIT 1";
    } elseif ( $category == 24 ) {  // CBRS
        $column     = 'CBRS_Treatment_Plan';
        $table      = 'form_cbrs_treatment_plan';
        $sql_plan   = "SELECT review FROM {$table} WHERE pid = ? ORDER BY id DESC LIMIT 1";
    } elseif ( $category == 27 ) {  // Case Management
        $column     = 'Case_Management_Plan';
        $table      = 'form_cm_treatment_plan';
        $sql_plan   = "SELECT review FROM {$table} WHERE pid = ? ORDER BY id DESC LIMIT 1";
    } elseif ( $category == 30 ) {  // Peer Support Plan
        $column     = 'Peer_Support_Plan';
        $table      = 'form_peer_support_txt_plan';
        $sql_plan   = "SELECT review FROM {$table} WHERE pid = ? ORDER BY id DESC LIMIT 1";
    }

    // getting the plan as per form

    $res_plan = sqlQuery($sql_plan, array($pid));

    if($res_plan){
        if($table == 'form_counselor_treatment_plan'){
            $review     = $res_plan['day_review'];
            $tx_column  = getTxColumn($review, $category);
        } else{
            $review     = $res_plan['review'];
            $tx_column  = getTxColumn($review, $category);
        }
    }

    // check if the TX Plan is expired

    $sql_tx_query   = "SELECT * FROM patient_data WHERE pid = ?";
    $res_tx_plan    = sqlQuery($sql_tx_query, array($pid));

    if( empty($res_tx_plan) || empty($res_tx_plan[$column]) ){
        $result = true;
    } else {

        $today          = date('Y-m-d');
        $tx_column      = ($res_tx_plan[$tx_column]) ? $res_tx_plan[$tx_column] : '';
        $tx_date        = ($res_tx_plan[$column]) ? $res_tx_plan[$column] : '';
        $ninety_days    = date('Y-m-d', strtotime($tx_date . '+ 90 days'));
        $one_eighty     = date('Y-m-d', strtotime($tx_date . '+ 180 days'));
        $two_seventy    = date('Y-m-d', strtotime($tx_date . '+ 270 days'));

        if( $review == '90 Day Review' ){
            $result = (strtotime($ninety_days) < strtotime($today)) ? true : false;
        } elseif( $review == '180 Day Review' ){
            $result = (strtotime($one_eighty) < strtotime($today)) ? true : false;
        } elseif( $review == '270 Day Review' ){
            $result = (strtotime($two_seventy) < strtotime($today)) ? true : false;
        }

    }

    echo json_encode(['result' => $result]);

}

//die();