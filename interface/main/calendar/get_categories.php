<?php
require_once(__DIR__ . '/../../globals.php');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/forms.inc');
require_once($GLOBALS['srcdir'].'/calendar.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/encounter_events.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');
require_once($GLOBALS['srcdir'].'/patient_tracker.inc.php');
require_once($GLOBALS['incdir']."/main/holidays/Holidays_Controller.php");
require_once($GLOBALS['srcdir'].'/group.inc');

 //Check access control
if (!acl_check('patients', 'appt', '', array('write','wsome'))) {
    die(xl('Access not allowed'));
}

$eid            = $_POST['eid']; 
$type_patient   = $_POST['type_patient'];
$status         = $_POST['status'];
$cattype        = $_POST['cattype'];
$pc_catid       = $_POST['pc_catid'];

//die('<option>'.$status.'</option>');

if($type_patient == 'medicaid' AND $status == 1){
    $cres = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories WHERE pc_catid IN (1, 37)  AND pc_active = 1 OR pc_cattype <> 0 ORDER BY pc_seq");
} elseif($type_patient == 'medicaid' AND empty($status) ){
    $cres = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories WHERE pc_catid NOT IN (52, 53, 54, 55, 56, 57, 58) AND pc_active = 1 ORDER BY pc_seq");
} elseif($type_patient == 'private' AND $status == 1){
    $cres = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories WHERE pc_catid IN (1, 52)  AND pc_active = 1 OR pc_cattype <> 0 ORDER BY pc_seq");
} elseif($type_patient == 'private' AND empty($status) ){
    $cres = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories WHERE pc_catid IN (1, 52, 53, 54, 55, 56, 57, 58) OR pc_cattype <> 0  AND pc_active = 1 ORDER BY pc_seq");
} else {
    $cres = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, " .
        "pc_recurrtype, pc_duration, pc_end_all_day " .
        "FROM openemr_postcalendar_categories where pc_active = 1 ORDER BY pc_seq");
}

$catoptions = "";

while ($crow = sqlFetchArray($cres)) {
    $duration = round($crow['pc_duration'] / 60);
      

    if ($crow['pc_cattype'] != $cattype) {
        continue;
    }

    //echo " durations[" . attr($crow['pc_catid']) . "] = " . attr($duration) . "\n";
    // echo " rectypes[" . $crow['pc_catid'] . "] = " . $crow['pc_recurrtype'] . "\n";
    $catoptions .= "    <option value='" . attr($crow['pc_catid']) . "'";
    if ($eid) {
        if ($crow['pc_catid'] == $pc_catid) {
            $catoptions .= " selected";
        }
    } else {
        if ($crow['pc_catid'] == $default_catid) {
            $catoptions .= " selected";
            
        }
    }

    $catoptions .= ">" . text(xl_appt_category($crow['pc_catname'])) . "</option>\n";
}

echo $catoptions;