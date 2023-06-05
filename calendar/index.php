<?php
/**
 * POST-NUKE Content Management System
 * Based on:
 * PHP-NUKE Web Portal System - http://phpnuke.org/
 * Thatware - http://thatware.org/
 *
 * Purpose of this file: Directs to the start page as defined in config.php
 *
 * @author    Francisco Burzi
 * @author    Post-Nuke Development Team
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2001 by the Post-Nuke Development Team <http://www.postnuke.com/>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../../globals.php");
require_once("$srcdir/calendar.inc");
require_once("$srcdir/patient.inc");
require_once 'includes/pnAPI.php';
require_once("$srcdir/acl.inc");

// From Michael Brinson 2006-09-19:
if (isset($_POST['pc_username'])) {
    $_SESSION['pc_username'] = $_POST['pc_username'];
}

//(CHEMED) Facility filter
if (isset($_POST['all_users'])) {
    $_SESSION['pc_username'] = $_POST['all_users'];
}

// bug fix to allow default selection of a provider
// added 'if..POST' check -- JRM
if (isset($_REQUEST['pc_username']) && $_REQUEST['pc_username']) {
    $_SESSION['pc_username'] = $_REQUEST['pc_username'];
}

// (CHEMED) Get the width of vieport
if (isset($_GET['framewidth'])) {
    $_SESSION['pc_framewidth'] = $_GET['framewidth'];
}

// FACILITY FILTERING (lemonsoftware) (CHEMED)
$_SESSION['pc_facility'] = 0;

/*********************************************************************
if ($_POST['pc_facility'])  $_SESSION['pc_facility'] = $_POST['pc_facility'];
*********************************************************************/
if ($GLOBALS['login_into_facility']) {
    $_SESSION['pc_facility'] = $_SESSION['facilityId'];
} else {
    if (isset($_COOKIE['pc_facility']) && $GLOBALS['set_facility_cookie']) {
        $_SESSION['pc_facility'] = $_COOKIE['pc_facility'];
    }
}

// override the cookie if the user doesn't have access to that facility any more
if ($_SESSION['userauthorized'] != 1 && $GLOBALS['restrict_user_facility']) {
    $facilities = getUserFacilities($_SESSION['authId']);
    // use the first facility the user has access to, unless...
    $_SESSION['pc_facility'] = $facilities[0]['id'];
    // if the cookie is in the users' facilities, use that.
    foreach ($facilities as $facrow) {
        if (($facrow['id'] == $_COOKIE['pc_facility']) && $GLOBALS['set_facility_cookie']) {
            $_SESSION['pc_facility'] = $_COOKIE['pc_facility'];
        }
    }
}

if (isset($_POST['pc_facility'])) {
    $_SESSION['pc_facility'] = $_POST['pc_facility'];
}

/********************************************************************/

if (isset($_GET['pc_facility'])) {
    $_SESSION['pc_facility'] = $_GET['pc_facility'];
}

if ($GLOBALS['set_facility_cookie']) {
    if (!$GLOBALS['login_into_facility'] && $_SESSION['pc_facility'] > 0) {
        // If login_into_facility is turn on $_COOKIE['pc_facility'] was saved in the login process.
        // In the case that login_into_facility is turn on you don't want to save different facility than the selected in the login screen.
        setcookie("pc_facility", $_SESSION['pc_facility'], time() + (3600 * 365));
    }
}

// Simplifying by just using request variable instead of checking for both post and get - KHY
if (isset($_REQUEST['viewtype'])) {
    $_SESSION['viewtype'] = $_REQUEST['viewtype'];
}

// start PN
pnInit();

// Get variables
list($module,
     $func,
     $type) = pnVarCleanFromInput(
         'module',
         'func',
         'type'
     );

if ($module != "PostCalendar") {
    // exit if not using PostCalendar module
    exit;
}

if ($type == "admin") {
    if (!acl_check('admin', 'calendar')) {
        // exit if do not have access
        exit;
    }
    if (($func != "modifyconfig") &&
        ($func != "clearCache") &&
        ($func != "testSystem") &&
        ($func != "categories") &&
        ($func != "categoriesConfirm") &&
        ($func != "categoriesUpdate")) {
        // only support certain functions in admin use
        exit;
    }
}

if (empty($type)) {
    $type = 'user';
}

if ($type == "user") {
    if (($func != "view") &&
        ($func != "search")) {
        // only support view and search functions in for non-admin use
        exit;
    }
}

if (($type != "user") && ($type != "admin")) {
    // only support admin and user type
    exit;
}

// Defaults for variables
if (isset($catid)) {
    pnVarCleanFromInput('catid');
}

if (pnModAvailable($module)) {
    if (pnModLoad($module, $type)) {
        // Run the function
        $return = pnModFunc($module, $type, $func);
    } else {
        $return = false;
    }
} else {
    $return = false;
}

// Sort out return of function.  Can be
// true - finished
// false - display error msg
// text - return information
if ((empty($return)) || ($return == false)) {
    // Failed to load the module
    $output = new pnHTML();
    $output->StartPage();
    $output->Text('Failed to load module ' . text($module) .' ( At function: "' . text($func) . '" )');
    $output->EndPage();
    $output->PrintPage();
    exit;
} elseif (strlen($return) > 1) {
    // Text
    $output = new pnHTML();
    //$output->StartPage();
    $output->SetInputMode(_PNH_VERBATIMINPUT);
    $output->Text($return);
    $output->SetInputMode(_PNH_PARSEINPUT);
    //$output->EndPage();
    $output->PrintPage();
} else {
    // duh?
}

?>

<style type="text/css">
#theform #bigCal *{
  box-sizing:border-box;
}
#theform td.week_dateheader {
  border-right: 1px solid #FFF;
  border-left: none;
}
#theform tr.lastRow td#times {
  width: 30px!important;
}
</style>
<script type="text/javascript">
  window.onload = function() {
    if(window.jQuery) {

        adjustLeftSidebar();
        $(window).on('resize orientationchange', function(){
          // location.reload();
          adjustLeftSidebar();
        });

        /* Modify Styling of the Calendar page. This will make the date bar and left sidebar sticky. */
        function adjustLeftSidebar() {

          if( $('#theform td.week_dateheader').length && ($('#topToolbarRight').length || $('#theform').length || $('#theform #bottomLeft').length || $('#theform .page-content-wrapper').length || $('#theform .page-content-wrapper #bigCal').length) ) {
            
            var topToolBarHeight = $('#topToolbarRight').outerHeight();
            var bottomLeftWidth = $('#bottomLeft').width();
            var bigCalWidth = $('#theform .page-content-wrapper #bigCal').width();

            $('#topToolbarRight').css({
              'position':'fixed',
              'top':'0',
              'left':'0',
              'width':'100%',
              'z-index':'100'
            });

            if( $('#theform #bottomLeft').length ) {
              $('#theform #bottomLeft').css({
                'position':'fixed',
                'top': topToolBarHeight + 'px',
                'left':'0',
                'width': bottomLeftWidth + 'px',
                'height':'100%',
                'overflow': 'hidden',
                'z-index':'99'
              });

              if( $('#theform .bottomLeftInner').length==0 ) {
                $('#theform #bottomLeft').wrapInner('<div class="bottomLeftInner" style="height:100%;overflow:auto;padding-bottom:50px"></div>');
              }
            }

            if( $('#theform').length ) {
              $('#theform').css({
                'padding-top': topToolBarHeight + 'px'
              });
            }

            if( $('#theform .page-content-wrapper').length ) {
              $('#theform .page-content-wrapper').css({
                'padding-left': bottomLeftWidth + 'px'
              });
            }

            if( $('#theform .page-content-wrapper #bigCal').length ) {

              /* PROVIDER ROW */
              var providerRowLeftOffset = bottomLeftWidth + 2;
              $('#theform #bigCal table tbody tr td.providerheader').eq(0).css({
                'position':'fixed',
                'top': topToolBarHeight + 'px',
                'left': providerRowLeftOffset + 'px',
                'width': bigCalWidth + 'px'
              });
              $('#theform #bigCal table tbody tr td.providerheader').parent().css({
                'position':'relative',
                'z-index':'80'
              });

              /* CALENDAR DATES HEADING */
              var providerRowHeight = $('#theform #bigCal table tbody tr td.providerheader').eq(0).outerHeight();
              var firstRowHeight = $('#theform #bigCal table tbody tr').eq(0).outerHeight();
              var timesCol = $('#theform #times').width();
              //var tableLeftOffset = bottomLeftWidth + timesCol;
              var tableLeftOffset = bottomLeftWidth + 29;
              var tableTopOffset = topToolBarHeight + providerRowHeight;

              // $('#theform #bigCal table tbody td').not('#times, .providerheader').css({
              //   'width':'14.2857%'
              // });

              $('#theform #bigCal table tbody tr').eq(1).css({
                'position':'fixed',
                'top': tableTopOffset + 'px',
                'left': tableLeftOffset + 'px',
                //'left': '0px',
                'z-index':'80',
                'width': bigCalWidth + 'px'
              });
              
              $('#theform #bigCal table tbody tr').eq(1).find('td').not('td:nth-child(1)').css({
                'border-bottom': '1px solid #999'
              });

              $('#theform #bigCal table tbody tr').eq(1).find('td').not('td:nth-child(1), td:nth-child(2)').css({
                'background-color': '#FFF',
              });

              var spacerHeight = tableTopOffset - 7;
              var spacer_css  = 'height:' + spacerHeight + 'px;';

              if( $('.calRowSpacer').length==0 ) {
                $('<tr class="calRowSpacer"><td colspan="8" style="'+spacer_css+'"></td></tr>').insertAfter( $('#theform #bigCal table tbody tr').eq(1) );
              } 

              $('#theform #bigCal table tbody tr').eq(3).addClass('lastRow');

              // var ctr = 0;
              $('#theform #bigCal table tbody tr td.week_dateheader').each(function(k,v){
                var headingColWidth = $(this).innerWidth();
                if(k==0) {
                  headingColWidth = headingColWidth + 2;
                }
                $('#theform #bigCal table tbody tr td.schedule').eq(k).css({
                  'width':headingColWidth+'px'
                });
              });

            }

          }

        }
      
    }
  }
</script>
<?php


exit;
