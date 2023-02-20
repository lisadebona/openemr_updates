<?php


require_once(__DIR__ . '/../../globals.php');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/calendar.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/encounter_events.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');
require_once($GLOBALS['srcdir'].'/classes/postmaster.php');
require_once($GLOBALS['srcdir'].'/group.inc');


 //Check access control
if (!acl_check('patients', 'appt', '', array('write','wsome'))) {
    die(xl('Access not allowed'));
}



