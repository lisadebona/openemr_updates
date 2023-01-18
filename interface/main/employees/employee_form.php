<?php
/**
 * OpenEMR Employee Page
 *
 * This Displays an About page for OpenEMR Displaying Version Number, Support Phone Number
 * If it have been entered in Globals along with the Manual and On Line Support Links
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Terry Hill <terry@lilysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    J. Alvin Harris <jalvin.code@gmail.com>
 * @copyright Copyright (c) 2016 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../../globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/api.inc");

use OpenEMR\Core\Header;
use OpenEMR\Services\VersionService;
use OpenEMR\Common\Csrf\CsrfUtils;


/*  DETECT IF LOGGED-IN USER IS SUPER ADMIN */
$is_super_user = acl_check('admin', 'super');
$acl_name=acl_get_group_titles($_SESSION['authUser']);
$bg_name='';
$bg_count=count($acl_name);
$selected_user_is_superuser = false;
for ($i=0; $i<$bg_count; $i++) {
  if ($acl_name[$i] == "Emergency Login") {
    $bg_name=$acl_name[$i];
  }
  //check if user member on group with superuser rule
  if (is_group_include_superuser($acl_name[$i])) {
    $selected_user_is_superuser = true;
  }
}
$isADMIN = ( $is_super_user || $selected_user_is_superuser ) ? true : false;
define( IS_SUPER_ADMIN, $isADMIN );

$app_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$current_base_url = $app_url . $_SERVER['REQUEST_URI'];
define('EMPLOYEES_DIR_PATH', dirname(__FILE__) );
define('CSRFTOKEN', CsrfUtils::collectCsrfToken() );
define('FORM_DIR', $rootdir . '/main/employees' );
define('FORM_BASE_URL', $app_url . FORM_DIR);
define('PAGE_BASE_URL',$current_base_url);
define('EMPLOYEE_RATES_TABLE','user_employee_rates');
$pathparts = explode('interface',dirname(__FILE__))[1];
define('INTERFACE_ABSPATH', str_replace($pathparts,'',dirname(__FILE__)) );


$httpRefURL = ( isset($_GET['ratesave']) && $_GET['ratesave'] ) ? str_replace('&ratesave=1','',PAGE_BASE_URL) : PAGE_BASE_URL;
$id         = ($_GET['id']) ? (int) $_GET['id'] : 0 ;
$disabled   = ($_GET['view']) ? " disabled ":"";

$check_res  = array();

$table = "users";
//$folder = $rootdir . "/main/employees";


if($id){
    $sql = "SELECT * FROM users WHERE id = ?";
    $check_res = sqlQuery($sql, array($id));
}

$title = ( $disabled ) ? "View Employee Information" : "Update Employee Information";

/*
if( !acl_check('admin', 'super', $_SESSION['authUser']) ):
    die('Sorry! You are not allowed to access this page. Please check with your supervisor.');
endif;
*/


/* CREATE TABLE FOR EMPLOYEE RATES IF NOT EXISTS */
$emp_table_name = 'user_employee_rates';
$emp_query = "SHOW TABLES LIKE '".$emp_table_name."'";
$emp_res = sqlStatement($emp_query);
$employee_rates_data = array();
if($emp_res->_queryID->num_rows == 0) {
  $emp_add_table = "CREATE TABLE IF NOT EXISTS ".$emp_table_name."(
    `rate_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) DEFAULT NULL,
    `pc_catid` BIGINT(20) DEFAULT NULL,
    `rate` VARCHAR(255) DEFAULT NULL,  
    `date_posted` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modified` DATETIME NOT NULL,
    PRIMARY KEY (`rate_id`)
  ) ENGINE=InnoDB";
  sqlStatement($emp_add_table);
} else {
  

  $newTblFields = array('date','custom_code');
  foreach($newTblFields as $field) {
    $chkfield = sqlStatement("SHOW COLUMNS FROM  `".$emp_table_name."` LIKE '".$field."'");

    if($field=='date') {
      /* Check if `date` column exists if NOT rename field to `date_posted` */
      if( isset($chkfield->_numOfRows) && $chkfield->_numOfRows==1 ) {
        sqlStatement("ALTER TABLE `".$emp_table_name."` CHANGE `date` `date_posted` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
      }
    } else {
      /* Check if field  exists if NOT add new field */
      if( isset($chkfield->_numOfRows) && $chkfield->_numOfRows==0 ) {
        sqlStatement("ALTER TABLE `".$emp_table_name."` ADD `".$field."` VARCHAR(255) NULL DEFAULT NULL AFTER `rate`");
      }
    }

  }
  
} 
?>
<html>
<head>
    <?php Header::setupHeader(['datetime-picker', 'common', 'opener', "jquery-ui","jquery-ui-darkness"]); ?>
    <title><?php echo xlt("Update Employee Info");?></title>
    <link rel="stylesheet" href="/library/css/bootstrap-timepicker.min.css">
    <link rel="stylesheet" href="<?php echo FORM_DIR ?>/style.css">
    <script>var params={};location.search.replace(/[?&]+([^=&]+)=([^&]*)/gi,function(s,k,v){params[k]=v});</script>
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

<body class="body_top employee-info-page">
    <div class="container">
        <div class="row">

          <?php /* TABS */ 
            $tab2_title = 'Employee Rates'; 
            $tab3_title = 'Employee Timesheet'; 
            $tabList[] = $title;
            $tabList[] = $tab2_title;
            $tabList[] = $tab3_title;
            $currentTab = (isset($_GET['tab']) && $_GET['tab']) ? $_GET['tab'] : 1;

            /* EMPLOYEE NAME */
            $empNameArr = array();
            $employeeFirstName = '';
            if(isset($check_res['fname']) && $check_res['fname']) {
              $empNameArr[] = $check_res['fname'];
              $employeeFirstName = $check_res['fname'];
            }
            if(isset($check_res['mname']) && $check_res['mname']) {
              $empNameArr[] = ($check_res['mname']) ? substr($check_res['mname'], 0, 1) . '.' : '';
            }
            if(isset($check_res['lname']) && $check_res['lname']) {
              $empNameArr[] = $check_res['lname'];
            }
            $employee_fullname = ($empNameArr && array_filter($empNameArr)) ? implode(' ',array_filter($empNameArr)) : '';
          ?>
          <div class="col-lg-12 tabswrapper">
            <div class="row">
              
              <h1 class="employee-name"><?php echo $employee_fullname ?></h1>

              <nav class="navbar navbar-default navbar-color navbar-static-top employee-tabs">
                <ul id="employee-info-tabs" class="nav navbar-nav">
                  <?php $t=1; foreach ($tabList as $tabName) { 
                    //$is_active = ($t==$currentTab) ? ' active':'';
                    $is_active = '';
                    if( isset($_POST['formtype']) && $_POST['formtype']=='report_form' ) {
                      if( $t==3) {
                        $is_active = ' active';
                      } else {
                        $is_active = '';
                      }
                    } else {
                      $is_active = ($t==$currentTab) ? ' active':'';
                    }
                    ?>
                    <li class="oe-bold-black<?php echo $is_active ?>"><a href="javascript:void(0)" data-tab="tab-panel-<?php echo $t; ?>"><?php echo xlt($tabName);?></a></li>
                  <?php $t++; } ?>
                </ul>
              </nav>
            </div>
          </div>

          <!-- EMPLOYEE INFORMATION -->
          <div id="tab-panel-1" class="tab-panel active">
            <div class="col-lg-12">
                <div class="page-header">
                  <h1><?php echo xlt($title);?></h1>
                </div>

                <form action="<?php echo FORM_DIR ?>/employee_form_save.php" method="POST">
                    <input type="hidden" name="csrf_token_form" id="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <fieldset class="form_content">
                        <legend class=""><?php echo xlt('Employee Information'); ?></legend>

                        <div class="">
                            <div class="col-md-6">

                                <div class="form-group">
                                    <label for="username" class="col-sm-4 "><?php echo xlt('Username'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="username" id="username" readonly value="<?php echo $check_res['username']; ?>" >
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="lname" class="col-sm-4 "><?php echo xlt('Last Name'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="lname" id="lname" <?php echo $disabled; ?> value="<?php echo $check_res['lname']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="fname" class="col-sm-4 "><?php echo xlt('First Name'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="fname" id="fname" <?php echo $disabled; ?> value="<?php echo $check_res['fname']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="mname" class="col-sm-4 "><?php echo xlt('Middle Name'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" name="mname" id="mname" <?php echo $disabled; ?> value="<?php echo $check_res['mname']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email" class="col-sm-4 "><?php echo xlt('Email'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="email" id="email" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['email']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="phone" class="col-sm-4 "><?php echo xlt('Phone'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="phone" id="phone" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['phone']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="street" class="col-sm-4 "><?php echo xlt('Street'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="street" id="street" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['street']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="city" class="col-sm-4 "><?php echo xlt('City'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="city" id="city" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['city']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="state" class="col-sm-4 "><?php echo xlt('State'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="state" id="state" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['state']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="zip" class="col-sm-4 "><?php echo xlt('Zipcode'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="zip" id="zip" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['zip']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="country_code" class="col-sm-4 "><?php echo xlt('Country Code'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="country_code" id="country_code" class="form-control" <?php echo $disabled; ?> value="<?php echo $check_res['country_code']; ?>">
                                    </div>
                                </div>

                            </div>  <!-- .col-md-6 -->

                            <div class="col-md-6">

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('Occupation'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php $occupation = ( $check_res['physician_type'] == 'na' ) ? 'Admin Staff'  :  ucwords(str_replace('_', ' ', $check_res['physician_type']));  ?>
                                        <input type="text" class="form-control"  readonly value="<?php echo $occupation; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="facility_id" class="col-sm-4 "><?php echo xlt('Default Facility'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php if( $disabled ): ?>
                                            <input type="text" class="form-control" readonly value="<?php echo get_facility_name($check_res['facility_id']); ?>">
                                        <?php else: ?>

                                            <select name="facility_id" id="facility_id" class="form-control">
                                                <option value="">Select</option>
                                                <?php
                                                    $qsql = sqlStatement("SELECT id, name FROM facility WHERE service_location != 0");
                                                    while ($facrow = sqlFetchArray($qsql)) {
                                                        $selected = ($facrow['id'] == $check_res['facility_id'] ) ? ' selected="selected" ' : '';
                                                        echo "<option value='" . attr($facrow['id']) . "' $selected>" . text($facrow['name']) . "</option>";
                                                    }
                                                ?>
                                            </select>

                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('Federal Tax ID'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="federaltaxid"  <?php echo $disabled; ?> value="<?php echo $check_res['federaltaxid']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('Provider License'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="state_license_number" <?php echo $disabled; ?>  value="<?php echo $check_res['state_license_number']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('License Date'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php
                                            $license = ( $check_res['licenseDate'] && ( $check_res['licenseDate'] != '0000-00-00' ) ) ? date('m/d/Y', strtotime($check_res['licenseDate'])) : '';
                                        ?>
                                        <input type="text" class="form-control newDatePicker" name="licenseDate" <?php echo $disabled; ?> value="<?php echo $license; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('CEU Date'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php
                                            $ceuDate = ( $check_res['ceuDate']  && ( $check_res['ceuDate'] != '0000-00-00' ) ) ? date('m/d/Y', strtotime($check_res['ceuDate'])) : '';
                                        ?>
                                        <input type="text" class="form-control newDatePicker" name="ceuDate"  <?php echo $disabled; ?> value="<?php echo $ceuDate; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="dob" class="col-sm-4 "><?php echo xlt('ICANS EXP Date'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php
                                            $icans = ( $check_res['icans']  && ( $check_res['icans'] != '0000-00-00' ) ) ? date('m/d/Y', strtotime($check_res['icans'])) : '';
                                        ?>
                                        <input type="text" class="form-control newDatePicker" name="icans"  <?php echo $disabled; ?> value="<?php echo $icans; ?>">
                                    </div>
                                </div>

                            </div>   <!-- .col-md-6 -->
                        </div>   <!-- .row -->

                        <hr>
                        <div class="clearfix"></div>
                        <div class="col-md-12">
                            <h4>Additional Information</h4>
                        </div>


                        <div>
                            <div class="col-md-6">

                                <div class="form-group">
                                    <label for="birthdate" class="col-sm-4 "><?php echo xlt('Birthdate'); ?></label>
                                    <div class="col-sm-8"  >
                                        <?php
                                            $birthdate = ( $check_res['birthdate'] && ( $check_res['birthdate'] != '0000-00-00' ) ) ? date('m/d/Y', strtotime($check_res['birthdate'])) : '';
                                        ?>

                                        <input type="text" class="form-control newDatePicker" name="birthdate" id="birthdate" <?php echo $disabled; ?> value="<?php echo $birthdate; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="marriage_status" class="col-sm-4 "><?php echo xlt('Marital Status'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="marriage_status" id="marriage_status" <?php echo $disabled; ?> value="<?php echo $check_res['marriage_status']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="sex" class="col-sm-4 "><?php echo xlt('Gender'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="sex" id="sex" <?php echo $disabled; ?> value="<?php echo $check_res['sex']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="ss" class="col-sm-4 "><?php echo xlt('SS'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="ss" id="ss" <?php echo $disabled; ?> value="<?php echo $check_res['ss']; ?>">
                                    </div>
                                </div>


                            </div><!-- .col-md-6 -->

                            <div class="col-md-6">


                                <div class="form-group">
                                    <label for="contact_information" class="col-sm-4 "><?php echo xlt('Relationship Info'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="contact_information" id="contact_information" <?php echo $disabled; ?> value="<?php echo $check_res['contact_information']; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="contact_number" class="col-sm-4 "><?php echo xlt('Relationship Number'); ?></label>
                                    <div class="col-sm-8"  >
                                        <input type="text" class="form-control" name="contact_number" id="contact_number" <?php echo $disabled; ?> value="<?php echo $check_res['contact_number']; ?>">
                                    </div>
                                </div>


                            </div><!-- .col-md-6 -->
                        </div>


                        <hr>
                        <div class="clearfix"></div>

                        <div class="" style="margin-top:20px">
                            <div class="container">
                                <div class="form-group">
                                    <a href="<?php echo $folder; ?>/employees.php" class="btn btn-cancel mr-3">Cancel</a>

                                    <?php if( acl_check('admin', 'super', $_SESSION['authUser']) ):  ?>

                                        <?php if( $disabled ): ?>
                                            <a href="<?php echo $folder; ?>/employee_form.php?id=<?php echo $id;  ?>" class="btn btn-default">Edit</a>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-default" name="employee_save">Save</button>
                                        <?php endif; ?>

                                    <?php endif; ?>


                                    <a href="<?php echo $folder; ?>/employee_documents.php?id=<?php echo $id;  ?>" class="btn btn-primary">View Documents</a>
                                </div>
                            </div>


                        </div>



                    </fieldset>
                </form>

            </div>
          </div>

          <!-- EMPLOYEE RATES -->
          <div id="tab-panel-2" class="tab-panel employee-rates-panel">
            <div class="col-lg-12">
                <?php include(EMPLOYEES_DIR_PATH.'/employee_rates_form.php'); ?>
            </div>
          </div>


          <!-- EMPLOYEE TIMESHEET -->
          <div id="tab-panel-3" class="tab-panel employee-reports-panel">
            <div class="col-lg-12">
                <?php 
                    $employee_user_id = $id;
                    $csrf_token_form = attr(CsrfUtils::collectCsrfToken());
                    include_once($_SERVER["DOCUMENT_ROOT"].'/interface/reports/employees/result.php'); 
                   //include(INTERFACE_ABSPATH.'/reports/employees/employees_timesheet.php'); 
                ?>
            </div>
          </div>

        </div>
    </div>
    <script src="<?php echo $web_root; ?>/library/js/bootstrap-timepicker.min.js"></script>
    <script>
        $(document).ready(function(){

            if( typeof params.ratesave!='undefined' || params.ratesave!=null ) {
              history.pushState('',document.title, '<?php echo $httpRefURL ?>');
            }

            var current_tab = '<?php echo $currentTab ?>';
            let current_tab_id = 'tab-panel-'+current_tab;

            <?php if( isset($_POST['formtype']) && $_POST['formtype']=='report_form' ) { ?>
                current_tab_id = 'tab-panel-3';
            <?php } ?>

            //$('.employee-info-page .tab-panel').not(current_tab_id).removeClass('active').addClass('active');
            $('.employee-info-page .tab-panel').each(function(){
              var tab_id = $(this).attr('id');
              if(tab_id==current_tab_id) {
                $(this).addClass('active');
              } else {
                $(this).removeClass('active');
              }
            });


            $('.newDatePicker').datetimepicker({
              timepicker:false,
              format:'m/d/Y'
            });

            $('#employee-info-tabs li').on('click',function(){
              $('#employee-info-tabs li').not(this).removeClass('active');
              $(this).addClass('active');
              var panel = $(this).find('a').attr('data-tab');
              $('.employee-info-page .tab-panel').not('#'+panel).removeClass('active');
              $('.employee-info-page .tab-panel#'+panel).addClass('active');
            });

            $('#close-alert').on('click',function(e){
              e.preventDefault();
              $('#form-response').remove();
            });

            /* Restric `rate` field to only numeric and period */
            $('input.amount').on('keyup keypress',function(evt){
              var val = $(this).val();
              if(isNaN(val)){
                val = val.replace(/[^0-9\.]/g,'');
                if(val.split('.').length>2) 
                  val =val.replace(/\.+$/,"");
              }
              $(this).val(val); 
            });


            if( $('#report_form').length ) {
              /* To prevent from refreshing the page and remain on the current tab panel */
              $('#secondary-btn-reset').on('click',function(e){
                e.preventDefault();
                $('#report_form')[0].reset();
                $('.mini-reports-wrapper').remove();
                $('#report_results.custom-report-results table tbody').html('<tr class="no-record-row"><td colspan="10" style="text-align:center;font-weight:bold;">&nbsp;</td></tr>');
                $('#form_from_date').val( $('#form_from_date').attr('data-default') );
                $('#form_to_date').val( $('#form_to_date').attr('data-default') );
              });
            }

        });
    </script>
</body>
</html>
