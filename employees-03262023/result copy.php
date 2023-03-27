<?php $root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/'; ?>
<style>
  @import url('/interface/reports/employees/styles.css');
</style>
<div class="pagecontent employee-timesheet-report">
  <div class="wrapper">
    <h1 class="page-title"><?php echo xlt('Employee Timesheet'); ?></h1>

    <div class="content">
      <?php 
      // $currentMonth = date('M');
      // $currentYear = date('Y');

      $date = new DateTime('now');
      $first = $date->modify('first day of this month');
      $firstDay = $first->format('m/d/Y'); /* YYYY/MM/DD */
      $defaultFirstDay = $firstDay;
      
      $last = $date->modify('last day of this month');
      $lastDay = $last->format('m/d/Y'); /* YYYY/MM/DD */
      $defaultLastDay = $lastDay;
      $employeeID = (isset($employee_user_id) && $employee_user_id) ? $employee_user_id : '';
      $employeeName = (isset($employee_fullname) && $employee_fullname) ? $employee_fullname : '';
      ?>

      <!-- FILTER FORM -->
      <div class="form-wrapper">
        <div id="formrespond"></div>
        <form method='post' name='report_form' id='report_form' action=''>
          <input type="hidden" name="csrf_token_form" value="<?php echo $csrf_token_form; ?>" />
          <input type="hidden" name="api" id="apikey" value="<?php echo base64_encode('type=timesheet&userid='.$_SESSION['authUserID']); ?>">
          <input type="hidden" name="formtype" value="employeetimesheet">
          <div class="form-fields">
            
            <div class="form-group provider-field">
              <label for="provider">Provider</label>
              <input type="hidden" id="providerVal" name="provider" value="<?php echo $employeeID ?>">
              <?php if ($employeeID) { ?>
              <input type="text" class="form-control" readonly value="<?php echo $employeeName ?>">
              <?php } else { ?>
              <input type="text" id="provider-search-field" class="form-control" value="" placeholder="Seach provider..." autocomplete="off">
              <div id="provider-result-container"><a href="javascript:void(0)" id="closeProvList"><span>close</span></a><div class="provresult"></div><div class="provpaging"></div><div id="provLoader" class="lds-ring"><div></div><div></div><div></div><div></div></div></div>
              <?php } ?>
            </div>

            <?php  
            $dateTypes = array('esign_dt'=>'E-Sign Date','dos_dt'=>'Date of Service');
            ?>

            <div class="form-group date-type">
              <label for="date_type">Filter By:</label>
              <select name="date_type" id="date_type" class="form-control">
                <?php foreach ($dateTypes as $t=>$d) { ?>
                  <option value="<?php echo $t ?>"><?php echo $d ?></option>
                <?php } ?>
              </select>
            </div>

            <div class="date-range">
              <div class="form-group">
                <label for="from_date">From:</label>
                <input type="text" name="from_date" id="from_date" class="datepicker form-control" value="<?php echo $defaultFirstDay ?>" autocomplete="off">
              </div>
              <div class="form-group">
                <label for="to_date">To:</label>
                <input type="text" name="to_date" id="to_date" class="datepicker form-control" value="<?php echo $defaultLastDay ?>" autocomplete="off">
              </div>
            </div>

            <div class="form-group limit-field">
              <label for="limit">Page limit:</label>
              <input type="number" name="limit" class="form-control" value="400">
            </div>

            <div class="buttons">
              <a href='javascript:void(0)' id="submitButton" class='btn btn-secondary btn-save'><?php echo xlt('Submit'); ?></a>
              <a href="javascript:void(0)" class="btn btn-secondary btn-refresh" id="secondary-btn-reset"><?php echo xlt('Reset'); ?></a>
              <a href="javascript:void(0)" class="btn btn-secondary btn-print" onclick="window.print()">Print</a>
            </div>

          </div>

        </form>
      </div>  


      <div id="report_results" class="custom-report-results reports-two-columns">
        
        <!-- RESULTS -->
        <div class="column-left">
          <section id="section_encounter">
            <div class="table-caption">
              <div><strong>PATIENT ENCOUNTERS</strong> <span class="dateRange"></span></div>
              <div class="total-records _encounters"></div>
            </div>
            <div class="table-wrap">
              <table class="table flowboard" cellpadding="5" cellspacing="2" id="ds_report">
                <thead>
                  <tr>
                    <th class="col1">#</th>
                    <th class="col2">Client Name</th>
                    <th class="col3">E-Sign Date</th>
                    <th class="col4">Date of Service</th>
                    <th class="col5">Place of Service</th>
                    <th class="col6">CPT Code</th>
                    <th class="col7">Medicaid? (Yes/No)</th>
                    <th class="col8">CPT Pay</th>
                    <th class="col9">Comments</th>
                  </tr>
                </thead>
                <tbody id="encounter-items-tbl">
                </tbody>
              </table>
              <div class="loader _encounters"><div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div></div>
            </div>
            <div class="paginationdiv" data-paginate="encounters"></div>
          </section>


          <section id="section_events">
            <div class="table-caption">
              <div><strong>PROVIDER EVENTS</strong> <span class="dateRange"></span></div>
              <div class="total-records _events"></div>
            </div>
            <div class="table-wrap">
              <table class="table flowboard" cellpadding="5" cellspacing="2">
                <thead>
                  <tr>
                    <th class="col1">#</th>
                    <th class="col2">Event Name</th>
                    <th class="col3">Event Date</th>
                    <th class="col4">Event Pay</th>
                    <th class="col5" style="text-align:center;">Notes/Comments</th>
                  </tr>
                </thead>
                <tbody id="events-items-tbl"></tbody>
              </table>
              <div class="loader _events"><div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div></div>
            </div>
            <div class="paginationdiv" data-paginate="events"></div>
          </section>
        </div>


        <!-- SUMMARY -->
        <div class="column-right">
          <div class="mini-reports">
            <div class="thead heading">
              <div class="tdata tcol1 codes">CPT Codes</div>
              <div class="tdata tcol2">Quantity</div>
              <div class="tdata tcol3">Subtotal</div>
            </div>
            <div class="rates-result">
              <div class="ri-group">
                <div class="ri-col tcol1">--</div>
                <div class="ri-col tcol2">--</div>
                <div class="ri-col tcol3">--</div>
              </div>
            </div>
            <div class="rates-overall-total">
              <strong>Total: </strong><strong id="ratesTotalAmount">0.00</strong>
            </div>
          </div>
        </div>

      </div>

    </div>
  
  </div>
</div>


<!-- MODAL BOX FOR COMMENTS -->
<div id="commentsBox">
  <div class="commentsDetails">
    <a href="javascript:void(0)" title="Close" id="commentsBoxClose"><span class="fa fa-times"></span></a>
    <form action="post" id="commentsForm">
      <input type="hidden" name="userid" value="<?php echo (isset($_SESSION['authUserID'])) ? $_SESSION['authUserID'] : '' ?>">
      <input type="hidden" name="type_id" value="">
      <input type="hidden" name="type" value="">
      <input type="hidden" name="noteid" value="">
      <input type="hidden" name="formtype" value="insert-comments">

      <div id="commentsRespond"></div>
      <div class="form-group">
        <label>Details:</label>
        <div id="commentInfo"></div>
      </div>

      <div class="form-group">
        <label for="comments">Notes/Comments:</label>
        <div class="comments-field">
          <textarea name="comments" class="form-control"></textarea>
        </div>
      </div>

      <div class="form-group form-buttons">
        <a href="javascript:void(0)" id="submitComments" class="btn btn-large btn-add-comment">Save Changes</a>
        <!-- <input type="submit" class="form-control btn btn-large" value="SAVE"> -->
      </div>
    </form>
  </div>
</div>

<script>
  jQuery(document).ready(function($){
    var apikey = $('input#apikey').val();
    var functionURL = '<?php echo $root ?>interface/reports/employees/function.php?api='+apikey;


    /*=== COLOR SWITCHING */
    var themeFuncURL = '<?php echo $root ?>public/colors.json';
    fetch(themeFuncURL)
    .then((response) => response.json())
    .then((json) => 
      switchColors(json)
    );

    function switchColors(json) {
      //var key = '<?php echo getSelectedTheme() ?>';
      var color = json.<?php echo getSelectedTheme() ?>; /* This function can be found on interface/globals.php */
      //console.log(color.midpale);

      var customStyles = '#report_form{background:'+color.midpale+'}';
          customStyles += '#report_form .buttons .btn{background:'+color.darkest+'}#report_form .buttons .btn:hover{background:'+color.dark+'}';
          customStyles += '#report_form .form-fields input[type=text], #report_form .form-fields input[type=number], #report_form .form-fields select{border-color: '+color.darkest+'!important;border-radius:0px!important;}';
          customStyles += '#report_form .form-group.limit-field input.form-control{border-color: '+color.darkest+'!important}';
          customStyles +='.employee-tabs.navbar-default .navbar-nav > li.active a{background-color:'+color.darkest+'!important;color:#FFF!important;}';
          customStyles +='.employee-tabs .navbar-nav > li > a{background-color:'+color.pale+'}';
          customStyles +='.employee-info-page .tab-panel{border:1px solid '+color.darkest+'!important}';
          customStyles +='.lds-ellipsis div{background:'+color.darkest+'!important}';
          customStyles +='#commentsBox table{border-color:'+color.darkest+'!important}';
          customStyles +='#commentsBox table td{border-bottom-color:'+color.darkest+'!important}';
          customStyles +='#commentsBox .form-buttons .btn{background:'+color.darkest+'}#commentsBox .form-buttons .btn:hover{background:'+color.dark+'}';
          customStyles +='.employee-rates-table .table-head{background:'+color.midpale+'!important}';
          customStyles +='.employee-rates-table .table-body .trow{background:'+color.white+'!important}';
          customStyles +='.employee-rates-table .table-body .trow:nth-child(odd){background:'+color.midpalepale+'!important}';
      document.body.insertAdjacentHTML("beforeend", '<style type="text/css">'+customStyles+'</style>');
    }




    // if( $('select[name="provider"]').length ) {
    //   $.ajax({
    //     type: 'GET',
    //     url: functionURL,
    //     data: {query:'providers'},
    //     dataType:'json',
    //     success: function (data) {
    //       if(data.length) {
    //         $(data).each(function(k,v){
    //           console.log(v);
    //           var id = v.id;
    //           var middle = (v.mname && v.mname.replace(/\s+/g,'')) ? v.mname.charAt(0)+'.' : '';
    //           var firstName = (v.fname!=null) ? v.fname : '';
    //           var lasttName = (v.lname!=null) ? v.lname : '';
    //           var fullName = firstName + ' ' + middle + ' ' + lasttName;
    //           var fullName = fullName.trim().replace(/\s+/g,' ');
    //           var item = '<option value="'+id+'">'+fullName+'</option>';
    //           $('select[name="provider"]').append(item);
    //         });
    //       }
    //     },
    //     error: function(xhr, status, error) {
    //       var err = eval("(" + xhr.responseText + ")");
    //       //console.log(err.Message);
    //     }
    //   });
    // }

    /* Provider Search */
    $(document).on('focus blur','input#provider-search-field',function(e){
        var srch = $(this).val().trim().replace(/\s+/g,' ');
        $('#provider-result-container').show();
        GetProvidersList('',1,functionURL);
    });

    $(document).on('keyup','input#provider-search-field',function(e){
      var srch = $(this).val().trim().replace(/\s+/g,' ');
      $('#providerVal').val("");
      if(e.type=='keyup') {
        functionURL = functionURL + '&search=' + encodeURIComponent(srch);
      }
      GetProvidersList(srch,1,functionURL);
    });

    $(document).on('click','.pagination_providers a',function(e){
      e.preventDefault();
      var pageUrl = $(this).data('href');
      var pagenum= $(this).data('page');
      var srch = $('input#provider-search-field').val().trim().replace(/\s+/g,' ');
      GetProvidersList(srch,pagenum,pageUrl);
    });

    /* init */
    GetProvidersList('',1,functionURL);
    $(document).on('click','#closeProvList',function(){
      $('#provider-result-container').hide();
      GetProvidersList('',1,functionURL);
    });
    $(document).on('click','a.provider-name-item',function(e){
      e.preventDefault();
      var id = $(this).data('id');
      GetProvidersList('',1,functionURL);
      $('#provider-search-field').val( $(this).text() );
      $('#providerVal').val(id);
      $('#encounter-items-tbl, #events-items-tbl').html('');
      $('#provider-result-container').hide();
    });
    function GetProvidersList(srch,pagenum,actionURL) {
      const frmData = {
        query:'providerslist',
        limit:20,
        page:pagenum
      }
      //const srchString = $('input#provider-search-field').val().trim().replace(/\s+/g,' ').replace('.','');
      const isSearch = actionURL.split('&search');
      if(isSearch.length==2) {
        //frmData.search = srchString;
        if(srch.replace(/\s+/g,'')) {
          const searchParts = srch.trim().replace(/\s+/g,' ').replace('.','').split(' ');
          frmData.search = searchParts[0];
        }
      }


      $.ajax({
        type: 'GET',
        url: actionURL,
        data: frmData,
        dataType:'json',
        beforeSend:function(){
          $('#provLoader').show();
        },
        success: function (data) {
          if(data.result.length) {
            var list = '<ul class="providerItems">';
            var currentProvId = $('#providerVal').val();
            $(data.result).each(function(k,v){
              var id = v.id;
              var middle = (v.mname && v.mname.replace(/\s+/g,'')) ? v.mname.charAt(0)+'.' : '';
              var firstName = (v.fname!=null) ? v.fname : '';
              var lastName = (v.lname!=null) ? v.lname : '';
              var fullName = firstName + ' ' + middle + ' ' + lastName;
              var selected = (id==currentProvId) ? ' selected':'';
              list += '<li><a href="javascript:void(0)" data-id="'+id+'" class="provider-name-item'+selected+'">'+fullName.trim().replace(/\s+/g,' ')+'</a></li>';
            });
            list += '</ul>';

            $('#provider-result-container .provresult').html(list);
            if(data.paginate) {
              $('#provider-result-container .provpaging').html(data.paginate);
            }
          } else {
            $('#provider-result-container .provresult').html('<p class="notfound">No record found.</p>');
            $('#provider-result-container .provpaging').html("");
          }
        },
        complete:function(){
          $('#provLoader').hide();
        },
        error: function(xhr, status, error) {
          //var err = eval("(" + xhr.responseText + ")");
          //console.log(err.Message);
        }
      });
    }

    /* Submit form via ajax */
    $(document).on('click','#submitButton',function(e){
      e.preventDefault();
      submit(1,'all');
    });

    $('select[name="provider"]').on('change',function(e){
      e.preventDefault();
      if(this.value!='-1') {
        $('#formrespond').html("");
      }
    });

    function submit(pagenum,type) {
      var params = $('#report_form').serialize()+'&page='+pagenum;
      //var provider = $('select[name="provider"]').val();
      var provider = $('[name="provider"]').val();
      var errors = [];

      var errorMessage = '<div class="alert alert-danger"><strong>Error! Please select a Provider.</strong></div>';
      //$('#formrespond').html(errorMessage);
      if(provider=='') {
        errors.push('Select a Provider.');
      }
      if( $('input[name="from_date"]').val().trim()=='' ) {
        errors.push('From Date is required.');
      }
      if( $('input[name="to_date"]').val().trim()=='' ) {
        errors.push('To Date is required.');
      }

      if(errors.length) { 

        var errmsg = '';
        $(errors).each(function(k,msg){
          errmsg += '<div><i class="fa fa-info-circle"></i> '+msg+'</div>';
        });
        $('#formrespond').html('<div class="alert alert-danger">'+errmsg+'</div>');

      } else {
        $.ajax({
          type: 'GET',
          url: functionURL,
          data: params,
          dataType:'json',
          beforeSend:function(){
            $('#encounter-items-tbl').html("");
            $('#events-items-tbl').html("");
            $('.total-records._encounters').html("");
            $('.total-records._events').html("");
			      $('#formrespond').html("");

            /* Reset Amount */
            // $('.mini-reports .rates-result').html('<div class="ri-group"> <div class="ri-col tcol1">--</div> <div class="ri-col tcol2">--</div> <div class="ri-col tcol3">--</div> </div>');
            // $('.mini-reports .rates-overall-total').html('<strong>Total: </strong><strong id="ratesTotalAmount">0.00</strong>');

            if(type=='all') {
              $('#report_results .loader').addClass('show');
            } else {
              $('#report_results .loader._'+type).addClass('show');
            }
          },
          success: function (data) {
            // console.log(type);
            //console.log(data);
            $('.dateRange').html(data.daterange);
            //$('.rates-result').html(data.rates_html);
            //$('#ratesTotalAmount').html(data.rates_total);

 
            if(type=='all') {
              /* ENCOUNTER */
              if(data.encounters.display) {
                $('#encounter-items-tbl').html(data.encounters.display);
              }
              if(data.encounters.paginate) {
                $('[data-paginate="encounters"]').html(data.encounters.paginate);
              }
              if(data.encounters.total) {
                $('.total-records._encounters').html('Record(s): ' + data.encounters.total);
              }

              /* EVENTS */
              if(data.events.display) {
                $('#events-items-tbl').html(data.events.display);
              }
              if(data.events.paginate) {
                $('[data-paginate="events"]').html(data.events.paginate);
              }
              if(data.events.total) {
                $('.total-records._events').html('Record(s): ' + data.events.total);
              }

            } else if(type=='encounters') {
              /* ENCOUNTER */
              if(data.encounters.display) {
                $('#encounter-items-tbl').html(data.encounters.display);
              }
              if(data.encounters.paginate) {
                $('[data-paginate="encounters"]').html(data.encounters.paginate);
              }
            } else if(type=='events') {
              /* EVENTS */
              if(data.events.display) {
                $('#events-items-tbl').html(data.events.display);
              }
              if(data.events.paginate) {
                $('[data-paginate="events"]').html(data.events.paginate);
              }
            }
          },
          complete:function(){
            setTimeout(function(){
              $('#report_results .loader').removeClass('show');
            },500);
            $('#report_results section').css('min-height','auto');
            //getExtraEncounterInfo();

            var newParams = params.replace('formtype=employeetimesheet','formtype=all');
            displayTheSummary(newParams);

          },
          error: function(xhr, status, error) {
            var err = eval("(" + xhr.responseText + ")");
            //console.log(err.Message);
          }
        });
      }
    }

    function displayTheSummary(params) {
      $.ajax({
          type: 'GET',
          url: functionURL,
          data: params,
          dataType:'json',
          beforeSend:function(){
          },
          success: function (data) {
            //console.log(data);
            if(data) {
              $('.rates-result').html(data.rates_html);
              $('#ratesTotalAmount').html(data.rates_total);
            }
          }
      });
    }


    // function getExtraEncounterInfo() {
    //   //var noteCode = 90785;
    //   if( $('#encounter-items-tbl tr').length ) {
    //     $('#encounter-items-tbl tr').each(function(){
    //       var target = $(this);
    //       var descriptionColumn = target.find('td.col5');
    //       let params = {
    //         'extrainfo':'encounter',
    //         'encounter':$(this).attr('data-encounter-id'),
    //         'provider_id':$('select[name="provider"]').val()
    //       };
    //       $.ajax({
    //         type: 'GET',
    //         url: functionURL,
    //         data: params,
    //         dataType:'json',
    //         beforeSend:function(){
              
    //         },
    //         success: function (data) {
    //           if(data) {
    //             var code = data.additional_session_type;
    //             var description = data.codeDescription;
    //             if(code){
    //               var codeDescription = '<div><strong style="color:#e32d2d">('+code+' - '+description+')</strong></div>';
    //               $(descriptionColumn).append(codeDescription);
    //             }
    //           }
    //         }

    //       });

    //     });
    //   }
    // }

    $(document).on('click','.pagination a',function(e){
      e.preventDefault();
      var url = $(this).attr('href');
      var type = $(this).parents('.paginationdiv').attr('data-paginate');
      var pagenum = getParameterByName('page',url);
      submit(pagenum,type);
    });

    $(document).on('click','.btn-refresh',function(e){
      e.preventDefault();
      // $('section .table-wrap tbody').html("");
      // $('.paginationdiv').html("");
      // $('.rates-result').html('<div class="ri-group"> <div class="ri-col tcol1">--</div> <div class="ri-col tcol2">--</div> <div class="ri-col tcol3">--</div> </div>');
      // $('#ratesTotalAmount').html("0.00");
      // $('.dateRange, .total-records').html("");
      // $('#report_form')[0].reset();
      location.reload();
    });

    $(document).on('click','.action-btn',function(e){
      e.preventDefault();
      var action = $(this).attr('data-action');
      $('#commentsBox').show();
      var rowInfo = $(this).parents('tr');
      var table = $(this).parents('.table');
      var thead = table.find('thead').html();
      var trow = $(rowInfo).clone()[0]['innerHTML'];
      var data = '<table class="table flowboard"><thead>'+thead+'</thead><tbody><tr>'+trow+'</tr></tbody></table>';
      var noteid = rowInfo.attr('data-noteid');
      $('#commentInfo').html(data);
      $('#commentsBox [name="type_id"]').val( rowInfo.attr('data-typeid') );
      $('#commentsBox [name="type"]').val( rowInfo.attr('data-type') );
      $('#commentsBox [name="noteid"]').val( rowInfo.attr('data-noteid') );
      var formData = $('#commentsForm').serialize() + '&formtype=getcomments';
      $.ajax({
        type: 'GET',
        url: functionURL,
        data: formData,
        dataType:'json',
        success: function (data) {
          if( typeof data.comments!='undefined' && data.comments!=null) {
            $('#commentsForm [name="comments"]').val(data.comments);
          }
        },
        error: function(xhr, status, error) {
          var err = eval("(" + xhr.responseText + ")");
          //console.log(err.Message);
        }
      });

    });

    $(document).on('click','#commentsBoxClose',function(e){
      e.preventDefault();
      $('#commentsBox').hide();
      $('#commentsForm [name="type_id"]').val("");
      $('#commentsForm [name="type"]').val("");
      $('#commentsForm [name="noteid"]').val("");
      $('#commentsRespond').html("");
      $('#commentsForm')[0].reset();
    });

    /* Submit form via ajax */
    $(document).on('click','#submitComments',function(e){
      e.preventDefault();
      var encounterId = $('#commentsForm [name="type_id"]').val();
      var commentBox = $('#commentsForm [name="comments"]').val().trim();

      $.ajax({
        type: 'GET',
        url: functionURL,
        data: $('#commentsForm').serialize(),
        dataType:'json',
        success: function (data) {
          
        },
        complete:function(){
          $('#commentsRespond').html('<div class="alert alert-success"><i class="fa fa-check"></i> Comments saved.</div>');
          if(commentBox && commentBox.replace(/\s+/g,'')) {
            $('#encounter-items-tbl tr[data-encounter-id="'+encounterId+'"] a.action-btn').text('View Comment');
          } else {
            $('#encounter-items-tbl tr[data-encounter-id="'+encounterId+'"] a.action-btn').text('Add Comment');
          }
        },
        error: function(xhr, status, error) {
          //var err = eval("(" + xhr.responseText + ")");
          //console.log(err.Message);
        }
      });
    });
    

    function getParameterByName(name, url) {
      if (!url) url = window.location.href;
      name = name.replace(/[\[\]]/g, "\\$&");
      var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
         results = regex.exec(url);
      if (!results) return null;
      if (!results[2]) return '';
      return decodeURIComponent(results[2].replace(/\+/g, " "));
    }


  });
</script>