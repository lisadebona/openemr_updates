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

  /* QUERY ADDITIONAL ENCOUNTER INFO */
  if( isset($_REQUEST['extrainfo']) && $_REQUEST['extrainfo']=='encounter' ) {
    $extrainfo = getAdditionalSessionType($_REQUEST);
    echo json_encode($extrainfo);
  }

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

function getTimesheetRecords($params) {
	global $_data;
	$provider_id = $params['provider'];
  $from_date_var = $params['from_date'];
  $to_date_var = $params['to_date'];

	$fromDate =  date('Y-m-d',strtotime($from_date_var));
	$toDate =  date('Y-m-d',strtotime($to_date_var));
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
              }
            }

            $data['patientFullName'] = $patientFullName;
            $data['codeName'] = $codeName;
            $data['patientMedicaid'] = $patientMedicaid;
            $data['rateAmount'] = $rateAmount;
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


	return $respond;
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
		?>
		<tr data-noteid="<?php echo $note_id ?>" data-typeid="<?php echo $row['id'] ?>" data-type="encounter" data-encounter-id="<?php echo $row['id'] ?>" data-esign-id="<?php echo $row['esignID'] ?>" class="line-item encounter-id-<?php echo $row['id'] ?>">
         <td class="col1"><?php echo $rowCtr  ?>.</td>
         <td class="col2"><?php echo $row['patientFullName'] ?></td>
         <td class="col3"><?php echo $row['serviceDate'] ?></td>
         <td class="col4"><?php echo $row['facility'] ?></td>
         <td class="col5"><?php echo $row['codeName'] ?></td>
         <td class="col6"><?php echo $row['patientMedicaid'] ?></td>
         <td class="col7"><?php echo $row['rateAmount'] ?></td>
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


// $params['provider'] = 50;
// $params['from_date'] = '2021-09-01';
// $params['to_date'] = '2021-09-31';
// $data = getProviderEvents($params);
// echo "<pre>";
// print_r($data);
// echo "</pre>";

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
  $part = 'counselor_progress_note';
  $tableName = 'form_'.$part;
  $encounter_id = ( isset($params['encounter']) && $params['encounter'] ) ? $params['encounter'] : '';
  $code = ( isset($params['code']) && $params['code'] ) ? $params['code'] : '';
  $code_description = '';
  $note_codes = getReportsOptions('note_codes');
  if($note_codes) {
    foreach($note_codes as $n) {
      if($code==$n->code){
        $code_description = $n->description;
      }
    }
  }

  $output = '';
  if($encounter_id && $code) {
    $query = "SELECT f.form_id, f.encounter, f.form_name, f.formdir FROM form_encounter e, forms f WHERE e.encounter=f.encounter AND e.encounter=".$encounter_id." AND f.formdir='".$part."'";
    /* Check if Table exists */
    $result = $_data->query($query)->fetchAll();
    if($result) {
      $tblquery = "SHOW TABLES LIKE '".$tableName."'";
      $tableExist = $_data->query($tblquery)->fetchAll();
      if($tableExist) {
        foreach($result as $r) {
          $form_id = $r['form_id'];
          $q2 = "SELECT id,encounter,pid,counselor,additional_session_type FROM ".$tableName." WHERE id=".$form_id." AND additional_session_type=".$code;
          $r2 = $_data->query($q2)->fetchAll();
          if($r2) {
            if(isset($r2[0]) && $r2[0]) {
              $r2[0]['codeDescription'] = $code_description;
              $output = ($r2) ? $r2[0]:'';
              break;
            }
          }
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




