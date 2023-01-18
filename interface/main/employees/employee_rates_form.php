<div class="page-header">
  <h1><?php echo xlt($tab2_title);?></h1>
</div>
<?php 
$user_id = $id;
$form_message = '';
if(isset($_GET['ratesave']) && $_GET['ratesave']) { 
  $form_message = '<div id="form-response" class="alert alert-success"><strong><i class="fa fa-check"></i> Form has been saved.</strong> <a href="javascript:void(0)" id="close-alert"><i class="fa fa-times"></i></a></div>';
}

$exclude_categories = ( @file_get_contents(FORM_BASE_URL.'/exclude.json') ) ? @json_decode(file_get_contents(FORM_BASE_URL.'/exclude.json')) : '';

/* FUNCTIONS */
/* GET CATEGORIES */
function empr_categories($excludeCats=null) {
  // $categories = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories where pc_active = 1 AND pc_cattype!=1 ORDER BY pc_seq");

  $categories = sqlStatement("SELECT pc_catid, pc_cattype, pc_catname, pc_recurrtype, pc_duration, pc_end_all_day FROM openemr_postcalendar_categories where pc_active = 1 ORDER BY pc_seq");

  $newList = array();
  if( sqlNumRows($categories) ) {
    while ($row = sqlFetchArray($categories)) {
      $catname = $row['pc_catname'];
      if($excludeCats) {
        if(!in_array($catname,$excludeCats)) {
          $newList[] = $row;
        }
      } else {
        $newList[] = $row;
      }
    }
  }
  return $newList;
}

/* GET CATEGORY NAME */
function empr_category($pc_catid,$fields=null) {
  $query = "SELECT * FROM openemr_postcalendar_categories WHERE pc_catid=".$pc_catid;
  $result = sqlStatement($query);
  $all = array();
  $entry = '';
  if( sqlNumRows($result) ) {
    while ($row = sqlFetchArray($result)) { 
      if($fields) {
        if( is_array($fields) ) {
          foreach($fields as $field) {
            $entry[$field] = $row[$field];
          }
        } else {
          $entry = $row[$fields];
        }
      } else {
        $all[] = $row;
      }
    }
  }
  if($fields) {
    return $entry;
  } else {
    return ($all) ? $all[0] : '';
  }
}

/* GET EMPLOYEE RATES */
function empr_rates($user_id) {
  $query = "SELECT * FROM " . EMPLOYEE_RATES_TABLE . " WHERE user_id=" . $user_id;
  $result = sqlStatement($query);
  $records = array();
  if( sqlNumRows($result) ) {
    while ($row = sqlFetchArray($result)) { 
      $key = $row['pc_catid'];
      $records[$key] = $row['rate'];
    }
  }
  return $records;
}

/* GET EMPLOYEE RATES BY CUSTOM CATEGORY */
function empr_customcode_rates($user_id,$code) {
  $query = "SELECT * FROM " . EMPLOYEE_RATES_TABLE . " WHERE user_id=" . $user_id . " AND custom_code='".$code."'";
  $result = sqlStatement($query);
  $records = array();
  if( sqlNumRows($result) ) {
    while ($row = sqlFetchArray($result)) { 
      $key = $row['custom_code'];
      $records[$key] = $row['rate'];
    }
  }
  return $records;
}

?>
<?php echo $form_message; ?>
<form action="<?php echo FORM_DIR; ?>/employees_rates_save.php" method="POST">
  <input type="hidden" name="csrf_token_form" value="<?php echo attr(CSRFTOKEN); ?>" />
  <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
  <?php $ratesList = empr_rates($user_id); ?>
  <fieldset class="form_content">
      <div class="table-flex employee-rates-table">
        <div class="table-head">
          <div class="th full">
            <span class="form-title"><?php echo xlt('Rates');?></span>
            <?php if (IS_SUPER_ADMIN) { ?>
            <button type="submit" class="btn btn-default employee_rates_button" name="employee_rates_save">Save</button>
            <?php } ?>
          </div>
        </div>
        <div class="table-body">
          <?php if ( $categories = empr_categories($exclude_categories) ) { ?>
            <?php $i=0; foreach($categories as $row) { 
              $pc_catid = $row['pc_catid'];
              $pc_catname = $row['pc_catname'];
              $rate_amount = ( isset($ratesList[$pc_catid]) && $ratesList[$pc_catid] ) ? $ratesList[$pc_catid] : '';
              ?>
              <div data-index="<?php echo $i ?>" class="trow">
                <div class="tdata catname">
                  <strong><?php echo $pc_catname ?></strong>
                </div>
                <div class="tdata rate">
                  <input type="text" name="rate[<?php echo $pc_catid ?>]" class="form-control amount" value="<?php echo $rate_amount ?>"<?php echo (IS_SUPER_ADMIN) ? '':' disabled'?> />
                </div>
              </div>
            <?php $i++; } ?>

            <?php  
            $customCategories = array();
            $interfaceFolder = str_replace('main/employees','',dirname(__FILE__)) ;
            $customCategoryFile = $interfaceFolder . 'reports/employees/options.json';
            if( file_exists($customCategoryFile) ) {
              $jsonData = '';
              ob_start();
              readfile($customCategoryFile);
              $jsonData = ob_get_contents();
              ob_end_clean();
              if($jsonData) {
                if( $convertedData = json_decode($jsonData) ) {
                  if( isset($convertedData->note_codes) ) {
                    $customCategories = $convertedData->note_codes;
                  }
                }
                
              }
            }
            if($customCategories) { ?>
              <?php foreach ($customCategories as $x=>$c) { 
                $xcode = $c->code;
                $cusCode = empr_customcode_rates($user_id,$xcode);
                $customCodeAmount = ( isset($cusCode[$xcode]) && $cusCode[$xcode] ) ? $cusCode[$xcode] : '';
                ?>
                <div data-index="<?php echo $i ?>" class="trow custom-code">
                  <div class="tdata catname">
                    <strong><?php echo $c->code ?> - <?php echo $c->description ?></strong>
                  </div>
                  <div class="tdata rate">
                    <input type="hidden" name="customcode[<?php echo $x ?>]" value="<?php echo $c->code ?>">
                    <input type="text" name="customcodeamount[<?php echo $x ?>]" class="form-control customcodeamount amount" value="<?php echo $customCodeAmount ?>"<?php echo (IS_SUPER_ADMIN) ? '':' disabled'?> />
                  </div>
                </div>
              <?php } ?>
            <?php } ?>

          <?php } ?>
        </div>
      </div>
  </fieldset>
</form>
