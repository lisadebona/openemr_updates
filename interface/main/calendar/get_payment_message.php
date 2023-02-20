<?php

require_once("../../globals.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

if( ($_POST['pid']) ){
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $pid        = (int) $_POST['pid'];
    $sql        = "SELECT payment_pending_message FROM patient_data WHERE pid = {$pid}";
    $form_stmt  = sqlQuery($sql);
    $status     = ($form_stmt) ? $form_stmt['payment_pending_message'] : '';

    echo $status;
    return;
} else {
    //echo json_encode(['status' => 0, 'message' => 'Error in getting payment message']);
    return;
}
