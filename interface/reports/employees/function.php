<?php
define("LOCAL_PATH_ROOT", $_SERVER["DOCUMENT_ROOT"]);
require(dirname(__FILE__) . '/Queries.class.php');

//$api = base64_encode('type=timesheet&userid=15');
$api_sample = 'dHlwZT10aW1lc2hlZXQmdXNlcmlkPTE1';

global $apiKey, $_data, $excludeCategoryList;
$apiKey = ( isset($_GET['api']) && $_GET['api'] ) ? $_GET['api'] : '';
if($apiKey) {
	$_data = new Queries( $apiKey );

} else {
	die('an API Key is required to access this function.');
}

if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
  
  /* PROVIDERS LIST */
  if( isset($_REQUEST['query']) && $_REQUEST['query']=='providers' ) {
    echo getProviderList($_REQUEST);
  }

  /* QUERY REPORTS */
  if( isset($_REQUEST['formtype']) && $_REQUEST['formtype']=='employeetimesheet' ) {
    //$res = getEmployeeTimesheet($_REQUEST);
    $res = getTimesheetRecords($_REQUEST);
    echo json_encode($res);
  }

  /* GET ALL RECORDS */
  if( isset($_REQUEST['formtype']) && $_REQUEST['formtype']=='all' ) {
    //$res = getEmployeeTimesheet($_REQUEST);
    $_REQUEST['limit'] = 'all';
    $allres = getTimesheetRecords($_REQUEST);
    echo json_encode($allres);
  }

  /* QUERY ADDITIONAL ENCOUNTER INFO */
  // if( isset($_REQUEST['extrainfo']) && $_REQUEST['extrainfo']=='encounter' ) {
  //   $extrainfo = getAdditionalSessionType($_REQUEST);
  //   echo json_encode($extrainfo);
  // }

  /* INSERT COMMENTS */
  if( isset($_REQUEST['formtype']) && $_REQUEST['formtype']=='insert-comments' ) {
   $res = insertComments($_REQUEST);
   echo json_encode($res);
  }

  if( isset($_REQUEST['formtype']) && $_REQUEST['formtype']=='getcomments' ) {
  	$res = getComments($_REQUEST['type_id'],$_REQUEST['type']);
  	echo json_encode($res);
  }



  

} else {
  //die('Permission Error!');
	
}

$excludeCategoryList = excludeCategoryItems();
function excludeCategoryItems() {
  global $_data;
  $excludeCategoryList = array();
  //$exclude_events_categories = @file_get_contents($_SERVER["DOCUMENT_ROOT"] . '/main/employees/exclude.json');
  $exclude_events_categories = @file_get_contents( $_SERVER["DOCUMENT_ROOT"] . '/interface/main/employees/exclude.json' );
  if( $excludeCategories = json_decode($exclude_events_categories) ) {
    foreach($excludeCategories as $catname) {
      $catname = trim($catname);
      $excatQuery = "SELECT * FROM openemr_postcalendar_categories WHERE pc_catname='".$catname."'";
      $excatResult = $_data->query($excatQuery)->fetchAll();
      if($excatResult) {
      	foreach($excatResult as $ex) {
      		$excludeCategoryList[] = $ex['pc_catid']; 
      	}
      }
    }
  }
  return $excludeCategoryList;
}

function insertComments($params) {
	global $_data;
	$type_id = $params['type_id'];
	$type = $params['type'];
	$userid = $params['userid'];
	$comments = $params['comments'];
	$noteid = $params['noteid'];
	$datemodified = date('Y-m-d H:i:s');

	if($noteid) {
		$query = "UPDATE employee_timesheet SET comments='".$comments."',userid=".$userid.",datemodified='".$datemodified."' WHERE id=".$noteid;
		$res = $_data->insertRow($query,true);
	} else {
		$query = "INSERT INTO employee_timesheet (type_id,type,userid,comments) VALUES ('".$type_id."','".$type."','".$userid."','".$comments."')";
		$res = $_data->insertRow($query);
	}
	$result['result'] = $res;
	return $result;
}

function getComments($type_id,$type) {
	global $_data;
	$query = "SELECT * FROM employee_timesheet WHERE type_id=".$type_id." AND type='".$type."'";
	$result = $_data->query($query)->fetchRow();
	return $result;
}



function getProviderList($params) {
  global $_data;
  $query = "SELECT id,username,fname,mname,lname,facility,facility_id FROM users WHERE npi!='' AND (physician_type!='na' OR physician_type!='') ORDER BY fname ASC";
  
  //$query = "SELECT id,username,fname,mname,lname,facility,facility_id FROM users WHERE (physician_type!='na' OR physician_type!='') ORDER BY fname ASC";
  $result = $_data->query($query)->fetchAll();
  return ($result) ? json_encode($result) : '';
}


function getEncounterData($encounter,$patientFields=null) {
  global $_data;
  $query = "SELECT * FROM form_encounter WHERE encounter=".$encounter;
  $result = $_data->query($query)->fetchAll();
  $records = array();
  $patient_columns = ($patientFields) ? $patientFields : null;
  if($result) {
    foreach($result as $row) {
      $patientData = getThePatientData($row['pid'],$patient_columns);
      $row['patient_details'] = $patientData; 
      $records[] = $row;
    }
  }
  return ($records) ? $records[0] : '';
}

function getThePatientData($pid,$fields=null) {
  global $_data;
  if(empty($pid)) return '';
  $columns = ($fields) ? implode(",",$fields) : "*";
  $query = "SELECT ".$columns." FROM patient_data WHERE pid=".$pid;
  $result = $_data->query($query)->fetchAll();
  return ($result) ? $result[0] : '';
}


/* Query Esign */
// $provider_id = 55;
// $fromDate = '2022-10-01';
// $toDate = '2022-11-30';
// $limit = 15;
// $page = 1;

// $params['provider'] = 66;
// $params['from_date'] = '2022-01-01';
// $params['to_date'] = '2022-04-01';
// $params['limit'] = 'all';
// $data = getAllRecords($params);
// echo "<pre>";
// print_r($data);
// echo "</pre>";


function getTimesheetRecords($params) {
	global $_data;
	$provider_id = $params['provider'];
  $from_date_var = $params['from_date'];
  $to_date_var = $params['to_date'];

	$fromDate =  date('Y-m-d',strtotime($from_date_var));
	$toDate =  date('Y-m-d',strtotime($to_date_var . ' +1 day'));
	$limit = ( isset( $params['limit'] ) ) ? $params['limit'] : 15;
	$page = ( isset( $params['page'] ) ) ? $params['page'] : 1;
	$links = ( isset( $params['links'] ) ) ? $params['links'] : 7;

	//$query = "SELECT enc.*, esign.id AS esign_id, esign.datetime AS service_date FROM (SELECT es.*, forms.encounter FROM esign_signatures es LEFT JOIN forms ON es.tid=forms.id WHERE es.uid=".$provider_id." AND es.datetime BETWEEN '".$fromDate."' AND '".$toDate."') esign LEFT JOIN form_encounter enc ON enc.encounter=esign.encounter";

  $query = "SELECT es.id AS esign_id, es.tid, es.table, es.datetime AS service_date FROM esign_signatures es WHERE es.uid=".$provider_id." AND es.datetime BETWEEN '".$fromDate."' AND '".$toDate."'";

	if($limit=='-1' || $limit=='all') {
		$sql_query = $query;
	} else {
		$sql_query = $query . " LIMIT " . ( ( $page - 1 ) * $limit ) . ", ".$limit;
	}
	
	$result = $_data->query($sql_query)->fetchAll();
	$records = array();
  $encounters = array();
  $pc_cat_ids = array();
  $i=0;
	if($result) {
		foreach($result as $row) {
      $table = $row['table'];
      $row_id = $row['tid'];
      $esign_id = $row['esign_id'];
      $service_date = $row['service_date'];

      /* Get data from `forms` or `form_encounter` table */
      $query2 = "SELECT encounter FROM " . $table . " WHERE id=".$row_id;
      $result2 = $_data->query($query2)->fetchAll();
      $patient_fields = array('id','title','fname','mname','lname','Medicaid_Client');

      /* Get Encounter Information */
      if($result2) {
        foreach($result2 as $re) {
          if( $data = getEncounterData($re['encounter'],$patient_fields) ) {
            $form_encounter_row_id = $data['id'];
            $rateAmount = '00.00';
            $patientFullName = '';
            $codeName = '';
            $patientMedicaid = '';
            $pc_catid = $data['pc_catid'];
            $category = getPostCalendarCategory($pc_catid);
            $codeName = ( isset($category['pc_catname']) && $category['pc_catname'] ) ? $category['pc_catname'] : '';
            if($pc_catid) {
              $pc_cat_ids[] = $pc_catid;
            }

            $data['esignID'] = $esign_id;
            $data['esignTid'] = $row_id;
            $data['signTable'] = $table;
            $data['serviceDate'] = $service_date;


           
            $customCodeRateAmount = 0;
            $extraCodes='';
            $patient = (isset($data['patient_details']) && $data['patient_details']) ? $data['patient_details'] : '';
            if($patient) {
              if( $patientNameArrs = array_filter( array($patient['title'],$patient['fname'],$patient['mname'],$patient['lname'] ) ) ) {
                $patientFullName = implode(' ',$patientNameArrs);    
                $patientMedicaid = ( isset($patient['Medicaid_Client']) && $patient['Medicaid_Client'] ) ? $patient['Medicaid_Client'] : '';
                $rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND pc_catid=" . $pc_catid;
                $rateResult = $_data->query($rateQuery)->fetchAll();
                if($rateResult) {
                  foreach($rateResult as $rt) {
                    $rateAmount = ($rt['rate']) ? number_format((float)$rt['rate'], 2, '.', '') : '0.00';
                  }
                }

              
                /* Additional Rate Custom Code */
                // if( $customCodes = getReportsOptions() ) {
                //   if( isset($customCodes->note_codes) && $customCodes->note_codes ) {
                //     foreach($customCodes->note_codes as $nc) {
                //       $xcode = $nc->code;
                //       $customCodeRateAmount = getCustomCodeRates($form_encounter_row_id,$provider_id,$xcode);
                //     }
                //   }
                // }


                $customCodeRes = getCustomCodeRates($form_encounter_row_id,$provider_id);
                $customCodeRateAmount = (isset($customCodeRes['rate']) && $customCodeRes['rate']) ? $customCodeRes['rate'] : 0;
                $xcodeName = (isset($customCodeRes['code']) && $customCodeRes['code']) ? $customCodeRes['code'] : '';
                // $xcodeDesc = (isset($customCodeRes['description']) && $customCodeRes['description']) ? $customCodeRes['description'] : '';
                if($xcodeName) {
                  $extraCodes=$customCodeRes;
                }
              }
            }



            


            $data['patientFullName'] = $patientFullName;
            $data['codeName'] = $codeName;
            $data['patientMedicaid'] = $patientMedicaid;
            $data['rateAmount'] = $rateAmount + $customCodeRateAmount;
            $data['customCode'] = $extraCodes;
            $records[$i] = $data;
            $i++;
          }
        }
      }
		}
	}


	/* CATEGORIES */
	$group_categories = array();
  $categories = getCategoryByProvider($pc_cat_ids,$provider_id);
	//$categories = getCategoryListing($query,$provider_id);
	$enc_rates_total = (isset($categories['total']) && $categories['total']) ? $categories['total'] : 0;
	$enc_rates_html = (isset($categories['html']) && $categories['html']) ? $categories['html'] : '';

	$total = $_data->query($query)->numRows();
	$paginate = $_data->pagination( $total, $params, 'pagination' );
	if($page==1) {
	  $offset = 1;
	} else {
	  $offset = (($page - 1) * $limit) + 1;
	}

  $total_rates = 0;
  if($pc_cat_ids) {
    foreach($pc_cat_ids as $pc_catid) {
      $rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND pc_catid=" . $pc_catid;
      $rateResult = $_data->query($rateQuery)->fetchAll();
      if($rateResult) {
        $data = $rateResult[0];
        $rateVal = ($data['rate']) ? $data['rate'] : 0;
        $total_rates += $rateVal;
        $group_categories[$pc_catid][]  = $rateVal;
      }
    }
  }

  /* GET ADDITIONAL CHARGES TOTAL (CUSTOM CODES) */
  $all_query = $_data->query($query)->fetchAll();
  $totalExtraCharges = 0;
  $extra_charges_info = '';
  $extraCodeArrs = array();
  if($all_query) {
    $x_total = 0;
    $x_count = 0;
    foreach($result as $ar) {
      $a_table = $ar['table'];
      $a_row_id = $ar['tid'];
      $a_query = "SELECT encounter FROM " . $a_table . " WHERE id=".$a_row_id;
      $a_result = $_data->query($a_query)->fetchAll();
      $extraItems = array();
      if($a_result) {
        foreach($a_result as $a) {
          $exCode = getCounselorProgressNote($a['encounter'],$provider_id);
          if( isset($exCode['rate']) && $exCode['rate'] ) {
            $xx_rate = ($exCode['rate']) ? $exCode['rate'] : 0;
            $xx_code = $exCode['code'];
            $xx_mixed = array($exCode['code'],$exCode['description']);
            $xx_desc = implode(' - ',$xx_mixed);
            $totalExtraCharges += $xx_rate;
            //$extraCodeArrs[$xx_code][] = array('code'=>$xx_code,'description'=>$xx_desc,'rate'=>$xx_rate);
            // $extraItems['code'] = $xx_code;
            // $extraItems['description'] = $xx_desc;
            // $extraItems['rate'][] = $xx_rate;
            $x_total += $xx_rate;
            $x_count ++;
            $extraCodeArrs[$xx_code]['description'] = $xx_desc;
            $extraCodeArrs[$xx_code]['count'] = $x_count;
            $extraCodeArrs[$xx_code]['total'] = $x_total;
          }
        }
        
      }
    }
  }


  /* Timsheet HTML output */
	$html = displayTimesheetRows($records,$offset);
	$date_range = 'From: ' . $params['from_date'].' To: '.$params['to_date'];
	$respond['daterange'] = $date_range;
	$respond['encounters']['display'] = $html;
	$respond['encounters']['total'] = $total;
	$respond['encounters']['rates'] = $group_categories;
	$respond['encounters']['paginate'] = $paginate;
	$respond['encounters']['categories'] = $categories;
	
	/* PROVIDER EVENTS */
	$events = getProviderEvents($params);
	$rates_total = (isset($events['rates_total']) && $events['rates_total']) ? $events['rates_total'] : 0;
	$rates_html = (isset($events['rates_html']) && $events['rates_html']) ? $events['rates_html'] : '';
	$events_total = (isset($events['total']) && $events['total']) ? $events['total'] : '';
	$events_paginate = (isset($events['paginate']) && $events['paginate']) ? $events['paginate'] : '';
	$events_display = (isset($events['display']) && $events['display']) ? $events['display'] : '';
	$respond['events']['display'] = $events_display;
	$respond['events']['total'] = $events_total;
	$respond['events']['paginate'] = $events_paginate;
	$allTotalRates = $enc_rates_total + $rates_total;
	$r_total_rates = number_format((float)$allTotalRates, 2, '.', '');
	$respond['rates_html'] = $enc_rates_html . $rates_html;
	$respond['rates_total'] = $r_total_rates;

  if($limit=='all') {
    if($extraCodeArrs) {
      foreach($extraCodeArrs as $code=>$c_data) {
        $rates_html .= '<div class="ri-group custom-code-info"> <div class="ri-col tcol1">'.$c_data['description'].'</div> <div class="ri-col tcol2">'.$c_data['count'].'</div> <div class="ri-col tcol3">'.$c_data['total'].'</div> </div>';
      }
    }
    $allTotalNum = $r_total_rates + $totalExtraCharges;
    $respondAll['rates_html'] = $enc_rates_html . $rates_html;
    $respondAll['rates_total'] = number_format((float)$allTotalNum, 2, '.', '');
    $respondAll['rates_extra'] = $extraCodeArrs;
    return $respondAll;
  } else {
    return $respond;
  }
}

function displayTimesheetRows($result,$offset) {
	$output = '';
	ob_start();
	if($result) {
		$rowCtr = $offset;
		foreach($result as $row) {
			$notes = getComments($row['id'],'encounter'); 
			$note_id = ($notes) ? $notes['id'] : '';
			$viewLabel = ($note_id) ? 'View Comment':'Add Comment';
      $customCode = (isset($row['customCode']) && $row['customCode']) ? $row['customCode'] : '';
      $extra_code = '';
      if($customCode) {
        $x_rate = (isset($customCode['rate']) && $customCode['rate']) ? $customCode['rate'] : '';
        $x_code = (isset($customCode['code']) && $customCode['code']) ? $customCode['code'] : '';
        $x_desc = (isset($customCode['description']) && $customCode['description']) ? $customCode['description'] : '';
        $extra_code = $x_code.' - '.$x_desc;
      }
		?>
		<tr data-encounternum="<?php echo $row['encounter'] ?>" data-noteid="<?php echo $note_id ?>" data-typeid="<?php echo $row['id'] ?>" data-type="encounter" data-encounter-id="<?php echo $row['id'] ?>" data-esign-id="<?php echo $row['esignID'] ?>" class="line-item encounter-id-<?php echo $row['id'] ?>">
         <td class="col1"><?php echo $rowCtr  ?>.</td>
         <td class="col2"><?php echo $row['patientFullName'] ?></td>
         <td class="col3"><?php echo $row['serviceDate'] ?></td>
         <td class="col4"><?php echo $row['facility'] ?></td>
         <td class="col5">
          <?php echo $row['codeName'] ?>
          <?php if ($extra_code) { ?>
          <div><strong style="color:#e32d2d">(<?php echo $extra_code ?>)</strong></div>
          <?php } ?>
        </td>
         <td class="col6"><?php echo $row['patientMedicaid'] ?></td>
         <td class="col7"><?php echo ($row['rateAmount']) ? number_format((float)$row['rateAmount'], 2, '.', '') : '0.00'; ?></td>
         <td class="col8 action-buttons">
           <a href="javascript:void(0)" class="action-btn" data-action="view"><?php echo $viewLabel ?></a>
         </td>
      </tr>
		<?php $rowCtr++; }
	} else { ?>
		<tr class="no-record-row">
      	<td colspan="8" style="text-align:center;font-weight:bold;">There are no record(s) found.</td>
     	</tr>
	<?php }

	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

/* PATIENT DATA */
// function getPatientData($pid) {
//   global $_data;
//   $records = [];
//   $query = "SELECT id,title,fname,mname,lname,DOB,Medicaid_Client FROM patient_data WHERE id=".$pid;
//   $result = $_data->query($query)->fetchRow();
//   return ($result) ? $result : '';
// }


function getPostCalendarCategory($pc_catid,$field=null) {
  global $_data;
  $records = [];
  $tableName = 'openemr_postcalendar_categories';
  if($field) {
    $query = "SELECT ".$field." FROM ".$tableName." WHERE pc_catid=".$pc_catid;
  } else {
    $query = "SELECT pc_catid,pc_constant_id,pc_catname FROM ".$tableName." WHERE pc_catid=".$pc_catid;
  }
  $result = $_data->query($query)->fetchRow();
  if($field) {
    return ($result) ? $result[$field] : '';
  } else {
    return ($result) ? $result : '';
  }
}

function getCategoryByProvider($pc_catid_ids,$provider_id) {
  global $_data;
  $output = array();
  $html = '';
  $total_rates = 0;
  $group_categories = array();
  if($pc_catid_ids) {
    foreach($pc_catid_ids as $pc_catid) {
      $rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND pc_catid=" . $pc_catid;
      $rateResult = $_data->query($rateQuery)->fetchAll();
      
      if($rateResult) {
        $data = $rateResult[0];
        $rateVal = ($data['rate']) ? $data['rate'] : 0;
        $total_rates += $rateVal;
        $group_categories[$pc_catid][]  = $rateVal;
      }
    }

    ob_start();
    if($group_categories) {
      foreach($group_categories as $catid=>$amounts) {
        $count_cats = count($amounts);
        $totalRates = array_sum($amounts);
        $categoryName = getPostCalendarCategory($catid,'pc_catname');
           $totalRatesVal = ($totalRates) ? number_format((float)$totalRates, 2, '.', '') : '0.00'; ?>
           <div class="ri-group">
              <div class="ri-col tcol1"><?php echo $categoryName ?></div>
              <div class="ri-col tcol2"><?php echo $count_cats ?></div>
              <div class="ri-col tcol3"><?php echo $totalRatesVal ?></div>
            </div>
           <?php
      }
    }
    $html = ob_get_contents();
    ob_end_clean();
  }

  $output['html'] = $html;
  $output['total'] = number_format((float)$total_rates, 2, '.', '');
  return $output;
}

function getCategoryListing($query,$provider_id) {
	global $_data;
	$output = array();
	$html = '';
	$group_categories = array();
	$catResult = $_data->query($query)->fetchAll();
	$total_rates = 0;
	if( $catResult ) {
		foreach($catResult as $cat) {
			$rateAmount = 0;
			/* Get Rate Amount */
			if( isset($cat['pc_catid']) && $cat['pc_catid'] ) {
        $pc_catid = $cat['pc_catid'];
				$rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND pc_catid=" . $pc_catid;
				$rateResult = $_data->query($rateQuery)->fetchAll();
				if($rateResult) {
					foreach($rateResult as $rt) {
						$rateAmount = $rt['rate'];
						//$rateAmount = ($rt['rate']) ? number_format((float)$rt['rate'], 2, '.', '') : '0.00';
					}
				}
        $group_categories[$pc_catid][]  = $rateAmount;
			}
      $total_rates += $rateAmount;
		}	
	}

	ob_start();
	if($group_categories) {
		foreach($group_categories as $catid=>$amounts) {
			$count_cats = count($amounts);
			$totalRates = array_sum($amounts);
			$categoryName = getPostCalendarCategory($catid,'pc_catname');
         $totalRatesVal = ($totalRates) ? number_format((float)$totalRates, 2, '.', '') : '0.00'; ?>
         <div class="ri-group">
            <div class="ri-col tcol1"><?php echo $categoryName ?></div>
            <div class="ri-col tcol2"><?php echo $count_cats ?></div>
            <div class="ri-col tcol3"><?php echo $totalRatesVal ?></div>
          </div>
         <?php
		}
	}
	$html = ob_get_contents();
	ob_end_clean();

	$output['html'] = $html;
	$output['total'] = number_format((float)$total_rates, 2, '.', '');
	return $output;
}



/* PROVIDER EVENTS */
function getProviderEvents($params) {
	global $_data;
  $excludeCategoryList = excludeCategoryItems();

	$records = [];
	$paginate = '';
	$is_paged = (isset($params['paging']) && $params['paging']) ? $params['paging'] : '';
	// $provider_id  = $params['provider'];
	// $fromDate     = ($params['form_from_date']) ? date('Y-m-d',strtotime($params['form_from_date'])):'';
	// $toDate       = ($params['form_to_date']) ? date('Y-m-d',strtotime($params['form_to_date'])):'';
	// $limit        = ( isset( $params['perpage'] ) ) ? $params['perpage'] : 25;
	// $page         = ( isset( $params['pg'] ) ) ? $params['pg'] : 1;
	$provider_id = $params['provider'];
	$fromDate =  date('Y-m-d',strtotime($params['from_date']));
	$toDate =  date('Y-m-d',strtotime($params['to_date']));
	$limit = ( isset( $params['limit'] ) ) ? $params['limit'] : 15;
	$page = ( isset( $params['page'] ) ) ? $params['page'] : 1;
	$links = ( isset( $params['links'] ) ) ? $params['links'] : 7;


	$links        = ( isset( $params['links'] ) ) ? $params['links'] : 7;

	//EXCLUDE CATEGORIES 
	$excludeCats = ($excludeCategoryList) ? " AND pc_catid NOT IN ( '" . implode( "', '" , $excludeCategoryList ) . "' ) " : "";
	$query = "SELECT events.* FROM openemr_postcalendar_events events WHERE events.pc_aid=".$provider_id." AND (events.pc_pid IS NULL OR events.pc_pid='') AND events.pc_eventDate BETWEEN '".$fromDate."' AND '".$toDate."'".$excludeCats." ORDER BY events.pc_eventDate DESC"; 

  if($limit=='-1' || $limit=='all') {
		$sql_query = $query;
	} else {
		$sql_query = $query . " LIMIT " . ( ( $page - 1 ) * $limit ) . ", ".$limit;
	}

	$total = $_data->query($query)->numRows();
	$paginate = $_data->pagination( $total, $params, 'pagination' );
	$result = $_data->query($sql_query)->fetchAll();
	if($page==1) {
	  $offset = 1;
	} else {
	  $offset = (($page - 1) * $limit) + 1;
	}

	$categories = getCategoryListing($query,$provider_id);
	$rates_total = (isset($categories['total']) && $categories['total']) ? $categories['total'] : 0;
	$rates_html = (isset($categories['html']) && $categories['html']) ? $categories['html'] : '';

  $html = ($total) ? displayProviderEventsRows($result,$offset) : '';
	
  // if($total==0) {
  //   $rates_total = 0;
  //   $rates_html = '';
  //   $html = '';
  // } else {
  //   $html = displayProviderEventsRows($result,$offset);
  // }
  
  $records['display'] = $html;
	$records['total'] = $total;
	$records['paginate'] = $paginate;
	$records['rates_total'] = $rates_total;
	$records['rates_html'] = $rates_html;
	return $records;
}

function displayProviderEventsRows($records,$offset=1) {
	global $_data;
	$output = '';
	ob_start();
	if ($records) {  
		$counter = $offset; 
	  	foreach ($records as $row) { 
			$event_id = $row['pc_eid'];
			$provider_id = $row['pc_aid'];
			$pc_catid = $row['pc_catid'];
			$rateAmount = '0.00';
			$rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND pc_catid=" . $pc_catid;
			$rateResult = $_data->query($rateQuery)->fetchAll();
			if($rateResult) {
				foreach($rateResult as $rt) {
					$rateAmount = ($rt['rate']) ? number_format((float)$rt['rate'], 2, '.', '') : '0.00';
				}
			}

			$notes = getComments($event_id,'events'); 
			$note_id = ($notes) ? $notes['id'] : '';
			$viewLabel = ($note_id) ? 'View Comment':'Add Comment';
	   ?>
		<tr data-typeid="<?php echo $event_id ?>" data-type="events" data-event-id="<?php echo $event_id ?>" class="provider-event-info">
			<td class="col1"><?php echo $counter ?>.</td>
			<td class="col2"><?php echo $row['pc_title'] ?></td>
			<td class="col3"><?php echo ($row['pc_eventDate']) ? date('m/d/Y',strtotime($row['pc_eventDate'])) : '' ?></td>
			<td class="col4"><?php echo $rateAmount ?></td>
			<td class="col5 action-buttons"><a href="javascript:void(0)" class="action-btn" data-action="view"><?php echo $viewLabel ?></a></td>
		</tr>  
  		<?php $counter++; } ?>
	<?php } else { ?>
	<tr class="no-record-row">
	  <td colspan="4" style="text-align:center;font-weight:bold;">There are no record(s) found.</td>
	</tr>
	<?php } 
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}


function getAdditionalSessionType($params) {
  global $_data;
  $encounter_row_id = ( isset($params['encounter']) && $params['encounter'] ) ? $params['encounter'] : '';
  $provider_id = ( isset($params['provider_id']) && $params['provider_id'] ) ? $params['provider_id'] : '';
  $code_description = '';
  $output['additional_session_type'] = '';
  $output['codeDescription'] = '';

  $note_codes = getReportsOptions('note_codes');
  if($note_codes) {
    foreach($note_codes as $n) {
      $code = $n->code;
      $additional_charge = $n->additional_charge;
      $code_description = $n->description;
      if($additional_charge) {
        $query = "SELECT e.id, e.encounter, e.provider_id FROM form_encounter e, form_counselor_progress_note n WHERE e.encounter=n.encounter AND e.id=".$encounter_row_id." AND e.provider_id=".$provider_id." AND n.additional_session_type='".$code."'";
        $result = $_data->query($query)->fetchAll();
        if($result) {
          $output['additional_session_type'] = $code;
          $output['codeDescription'] = $code_description;
        }
      }
      
    }
  }
  return $output;
}


/* RESULT */
//$opt = getReportsOptions('note_codes');
// Array
// (
//     [0] => stdClass Object
//         (
//             [code] => 90785
//             [description] => Play Therapy
//         )

// )

function getReportsOptions($key=null) {
  $result = '';
  if( file_exists(dirname(__FILE__).'/options.json') ) {
    ob_start();
    readfile(dirname(__FILE__).'/options.json');
    $content =  ob_get_contents();
    ob_end_clean();
    $data = json_decode($content);
    if($key) {
      $result = (isset($data->$key) && $data->$key) ? $data->$key : '';
    } else {
      $result = json_decode($content);
    }
  }
  return $result;
}


/* GET EMPLOYEE RATES BY CUSTOM CATEGORY */
function getCustomCodeRates($encounter_row_id,$provider_id) {
  global $_data;
  $rateAmount = 0;
  $output['rate'] = '';
  $output['code'] = '';
  $output['description'] = '';
  $note_codes = getReportsOptions('note_codes');
  if($note_codes) {
    foreach($note_codes as $n) {
      $code = $n->code;
      $code_description = $n->description;
      $additional_charge = $n->additional_charge;
      if($additional_charge) {
        $query = "SELECT e.id, e.encounter, e.provider_id FROM form_encounter e, form_counselor_progress_note n WHERE e.encounter=n.encounter AND e.id=".$encounter_row_id." AND e.provider_id=".$provider_id." AND n.additional_session_type='".$code."'";
        $result = $_data->query($query)->fetchAll();
        if($result) {
          $rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND custom_code='".$code."'";
          $rateResult = $_data->query($rateQuery)->fetchAll();
          if($rateResult) {
            foreach($rateResult as $row) {
              $rateAmount = ($row['rate']) ? $row['rate'] : 0;
              $output['rate'] = $rateAmount;
              $output['code'] = $code;
              $output['description'] = $code_description;
            }
          }
        }
      }
      
    }
  }

  return $output;
}

/* GET `form_counselor_progress_note` by `encounter` */
function getCounselorProgressNote($encounter,$provider_id) {
  global $_data;
  $output = array();
  $note_codes = getReportsOptions('note_codes');
  if($note_codes) {
    foreach($note_codes as $n) {
      $code = $n->code;
      $code_description = $n->description;
      $additional_charge = $n->additional_charge;
      if($additional_charge) {
        $query = "SELECT id,encounter,additional_session_type FROM form_counselor_progress_note n WHERE n.encounter=".$encounter." AND n.counselor=".$provider_id." AND n.additional_session_type='".$code."'";
        $result = $_data->query($query)->fetchAll();
        if($result) {
          /* GET THE RATE */
          $rateQuery = "SELECT * FROM user_employee_rates WHERE user_id=" . $provider_id . " AND custom_code='".$code."'";
          $rateResult = $_data->query($rateQuery)->fetchAll();
          if($rateResult) {
            foreach($rateResult as $row) {
              $rateAmount = ($row['rate']) ? $row['rate'] : 0;
              $output['rate'] = $rateAmount;
              $output['code'] = $code;
              $output['description'] = $code_description;
            }
          }
        }
      }
    }
  }
  return $output;
}
