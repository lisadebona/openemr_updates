<?php
/**
 * This contains the tab set for encounter forms.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2017 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once(dirname(__FILE__).'/../../globals.php');
require_once("$srcdir/pid.inc");
require_once("$srcdir/encounter.inc");
require_once("$srcdir/forms.inc");

use OpenEMR\Tabs\TabsWrapper;
use OpenEMR\Core\Header;

if (isset($_GET["set_encounter"])) {
    // The billing page might also be setting a new pid.
    if (isset($_GET["set_pid"])) {
        $set_pid=$_GET["set_pid"];
    } else if (isset($_GET["pid"])) {
        $set_pid=$_GET["pid"];
    } else {
        $set_pid=false;
    }

    if ($set_pid && $set_pid != $_SESSION["pid"]) {
        setpid($set_pid);
    }

    setencounter($_GET["set_encounter"]);
}

$tabset = new TabsWrapper('enctabs');
$tabset->declareInitialTab(
    xl('Summary'),
    "<iframe frameborder='0' style='height:95.3%;width:100%;' src='forms.php'>Oops</iframe>"
);
// We might have been invoked to load a particular encounter form.
// In that case it will be the second tab, and removable.
if (!empty($_GET['formname'])) {
    $url = $rootdir . "/patient_file/encounter/load_form.php?formname=" . attr_url($_GET['formname']);
    $tabset->declareInitialTab(
        $_GET['formdesc'],
        "<iframe name='enctabs-2' frameborder='0' style='height:95.3%;width:100%;' src='$url'>Oops</iframe>",
        true
    );
}

// This is for making the page title which will be picked up as the tab label.
$dateres = getEncounterDateByEncounter($encounter);
$encounter_date = date("Y-m-d", strtotime($dateres["date"]));
$currentForm = ( isset($_GET['formname']) && $_GET['formname'] ) ? $_GET['formname'] : '';
?>

<html>
<head>
<title><?php echo text(oeFormatShortDate($encounter_date)) . ' ' . xlt('Encounter'); ?></title>
    <?php Header::setupHeader(); ?>
<?php echo $tabset->genCss(); ?>
<?php echo $tabset->genJavaScript(); ?>
<script>

$(function() {
  // Initialize support for the tab set.
  twSetup('enctabs');
  var formName = '<?php echo $currentForm; ?>';
  if(formName=='fee_sheet') {
    $('#enctabs li a').each(function(){
      if( $(this).text()=='Fee Sheet' ) {
        $(this).trigger('click');
      }
    });
  }
});

// This is called to refresh encounter display data after something has changed it.
// Currently only the encounter summary tab will be refreshed.
function refreshVisitDisplay() {
  for (var i = 0; i < window.frames.length; ++i) {
    if (window.frames[i].refreshVisitDisplay) {
      window.frames[i].refreshVisitDisplay();
    }
  }
}

// Called from the individual iframes when their forms want to close.
// The iframe window name is passed and identifies which tab it is.
// The "refresh" argument indicates if encounter data may have changed.
function closeTab(winname, refresh) {
  twCloseTab('enctabs', winname);
  if (refresh) {
    refreshVisitDisplay();
  }
}

</script>
</head>
<body style="margin: 0px;">
<?php echo $tabset->genHtml(); ?>
</body>
</html>
