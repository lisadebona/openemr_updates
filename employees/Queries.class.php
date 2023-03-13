<?php 
class Queries {

  private $_api;
  private $_parameters;
  private $_userid;

  protected $connection;
  protected $query;
  protected $show_errors = TRUE;
  protected $query_closed = TRUE;
  public $query_count = 0;

  public function __construct( $api ) {
    $charset = 'utf8';
    $this->_api = $api ;
    $this->userid = '';
    include($_SERVER["DOCUMENT_ROOT"].'/sites/default/sqlconf.php');
    

    $this->connection = new mysqli($host, $login, $pass, $dbase);
    if ($this->connection->connect_error) {
      $this->error('Failed to connect to MySQL - ' . $this->connection->connect_error);
    }
    $this->connection->set_charset($charset);

    $parameters = array();
    if($api) {
      $api = base64_decode($api);
      $params = explode('&',$api);
      foreach($params as $par) {
        $p = explode("=",$par);
        $k = $p[0];
        $parameters[$p[0]] = $p[1];
      }

      $api = base64_decode($api);
      $userid = (isset($parameters['userid']) && $parameters['userid']) ? $parameters['userid'] : '';
      if($userid) {
        $res = $this->query("SELECT id FROM users WHERE id=".$userid)->fetchRow();
        $this->userid = ($res) ? $res['id']:'';
      }
    }
    $this->_parameters = $parameters;
    if(empty($this->userid)) {
      die('PERMISSION ERROR!');
    }
  }

  public function query($query) {
    if (!$this->query_closed) {
        $this->query->close();
    }
    if ($this->query = $this->connection->prepare($query)) {
      if (func_num_args() > 1) {
        $x = func_get_args();
        $args = array_slice($x, 1);
        $types = '';
        $args_ref = array();
        foreach ($args as $k => &$arg) {
          if (is_array($args[$k])) {
            foreach ($args[$k] as $j => &$a) {
              $types .= $this->_gettype($args[$k][$j]);
              $args_ref[] = &$a;
            }
          } else {
            $types .= $this->_gettype($args[$k]);
            $args_ref[] = &$arg;
          }
        }
        array_unshift($args_ref, $types);
        call_user_func_array(array($this->query, 'bind_param'), $args_ref);
      }
      $this->query->execute();
      if ($this->query->errno) {
        $this->error('Unable to process MySQL query (check your params) - ' . $this->query->error);
      }
      $this->query_closed = FALSE;
      $this->query_count++;
    } else {
      $this->error('Unable to prepare MySQL statement (check your syntax) - ' . $this->connection->error);
    }
    return $this;
  }

  public function fetchAll($callback = null) {
      $params = array();
      $row = array();
      $meta = $this->query->result_metadata();
      while ($field = $meta->fetch_field()) {
          $params[] = &$row[$field->name];
      }
      call_user_func_array(array($this->query, 'bind_result'), $params);
        $result = array();
        while ($this->query->fetch()) {
            $r = array();
            foreach ($row as $key => $val) {
                $r[$key] = $val;
            }
            if ($callback != null && is_callable($callback)) {
                $value = call_user_func($callback, $r);
                if ($value == 'break') break;
            } else {
                $result[] = $r;
            }
        }
        $this->query->close();
        $this->query_closed = TRUE;
    return $result;
  }


  public function fetchRow() {
    $params = array();
    $row = array();
    $meta = $this->query->result_metadata();
    while ($field = $meta->fetch_field()) {
      $params[] = &$row[$field->name];
    }
    call_user_func_array(array($this->query, 'bind_result'), $params);
    $result = array();
    while ($this->query->fetch()) {
      foreach ($row as $key => $val) {
        $result[$key] = $val;
      }
    }
    $this->query->close();
    $this->query_closed = TRUE;
    return $result;
  }

  public function insertRow($query,$update=false) {
    $result = '';
    if ($this->connection->query($query) === TRUE) {
      if($update) {
        $result = 'updated';
      } else {
        $result = $this->connection->insert_id;
      }
    }
    $this->close();
    return $result;
  }

  public function close() {
    return $this->connection->close();
  }

  public function numRows() {
    $this->query->store_result();
    return $this->query->num_rows;
  }

  public function affectedRows() {
    return $this->query->affected_rows;
  }

    public function lastInsertID() {
      return $this->connection->insert_id;
    }

    public function error($error) {
        if ($this->show_errors) {
            exit($error);
        }
    }

  private function _gettype($var) {
      if (is_string($var)) return 's';
      if (is_float($var)) return 'd';
      if (is_int($var)) return 'i';
      return 'b';
  }


  // public function myDBOpen() {
  //   include($_SERVER["DOCUMENT_ROOT"].'/sites/default/sqlconf.php');
  //   $conn = new mysqli($host,$login,$pass,$dbase);
  //   if ($conn->connect_errno) {
  //     echo "Failed to connect to MySQL: " . $conn->connect_error;
  //     exit();
  //   }
  //   return $conn;
  // }
   
  // public function myDBClose($conn) {
  //   $conn->close();
  // }


  // public function getResults($query) {
  //   $records = [];
  //   $conn = $this->myDBOpen();
  //   if( $result = $conn->query($query) ) {
  //     while($row = $result->fetch_assoc()){
  //       $records[] = $row;
  //     }
  //   }
  //   return $records;
  //   $this->myDBClose($conn);
  // }

  // public function getRow($query) {
  //   $records = [];
  //   $conn = $this->myDBOpen();
  //   if( $result = $conn->query($query) ) {
  //     while($row = $result->fetch_assoc()){
  //       $records[] = $row;
  //     }
  //   }
  //   return ($records) ? $records[0] : '';
  //   $this->myDBClose($conn);
  // }

  public function pagination( $total, $params, $list_class='pagination' ) {
    $limit = ( isset( $params['limit'] ) ) ? $params['limit'] : 15;
    $page = ( isset( $params['page'] ) ) ? $params['page'] : 1;
    $links = ( isset( $params['links'] ) ) ? $params['links'] : 7;
    if($limit=='-1' || $limit=='all') {
      return '';
    }
  
    $last       = ceil( $total / $limit );
    $start      = ( ( $page - $links ) > 0 ) ? $page - $links : 1;
    $end        = ( ( $page + $links ) < $last ) ? $page + $links : $last;
  
    $html       = '<ul class="' . $list_class . '">';
    $class      = ( $page == 1 ) ? "disabled" : "";
    $html       .= '<li class="' . $class . '"><a href="?limit=' . $limit . '&page=' . ( $page - 1 ) . '">&laquo;</a></li>';
  
    if ( $start > 1 ) {
      $html   .= '<li><a href="?limit=' . $limit . '&page=1">1</a></li>';
      $html   .= '<li class="disabled"><span>...</span></li>';
    }
  
    for ( $i = $start ; $i <= $end; $i++ ) {
      $class  = ( $page == $i ) ? "active" : "";
      $html   .= '<li class="' . $class . '"><a href="?limit=' . $limit . '&page=' . $i . '">' . $i . '</a></li>';
    }
  
    if ( $end < $last ) {
      $html   .= '<li class="disabled"><span>...</span></li>';
      $html   .= '<li><a href="?limit=' . $limit . '&page=' . $last . '">' . $last . '</a></li>';
    }
  
    $class      = ( $page == $last ) ? "disabled" : "";
    $html       .= '<li class="' . $class . '"><a href="?limit=' . $limit . '&page=' . ( $page + 1 ) . '">&raquo;</a></li>';
    $html       .= '</ul>';
    return $html;
  }


  public function getPagination( $total, $params, $baseUrl=null, $list_class='pagination' ) {
    if( empty($baseUrl) ) return '';
    $limit = ( isset( $params['limit'] ) ) ? $params['limit'] : 15;
    $page = ( isset( $params['page'] ) ) ? $params['page'] : 1;
    $links = ( isset( $params['links'] ) ) ? $params['links'] : 7;
    if($limit=='-1' || $limit=='all') {
      return '';
    }
  
    $last       = ceil( $total / $limit );
    $start      = ( ( $page - $links ) > 0 ) ? $page - $links : 1;
    $end        = ( ( $page + $links ) < $last ) ? $page + $links : $last;
  
    $html       = '<ul class="' . $list_class . '">';
    $class      = ( $page == 1 ) ? "disabled" : "";
    if($page == 1) {
      $html       .= '<li class="begin disabled"><span>&laquo;</span></li>';
    } else {
      $html       .= '<li class="' . $class . '"><a data-page="'.( $page - 1 ).'" data-href="' . $baseUrl . '&page=' . ( $page - 1 ) . '">&laquo;</a></li>';
    }
    
  
    if ( $start > 1 ) {
      $html   .= '<li><a data-page="1" data-href="' . $baseUrl . '&page=1">1</a></li>';
      $html   .= '<li class="disabled"><span>...</span></li>';
    }
  
    for ( $i = $start ; $i <= $end; $i++ ) {
      $class  = ( $page == $i ) ? "active" : "";
      $html   .= '<li class="' . $class . '"><a data-page="'.$i.'" data-href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
  
    if ( $end < $last ) {
      $html   .= '<li class="disabled"><span>...</span></li>';
      $html   .= '<li><a data-page="'.$last.'" data-href="' . $baseUrl . '&page=' . $last . '">' . $last . '</a></li>';
    }
  
    $class      = ( $page == $last ) ? "disabled" : "";
    if($page == $last) {
      $html       .= '<li class="end disabled"><span>&raquo;</span></li>';
    } else {
      $html       .= '<li><a data-page="'.( $page + 1 ).'" data-href="' . $baseUrl . '&page=' . ( $page + 1 ) . '">&raquo;</a></li>';
    }
    

    $html       .= '</ul>';
    return $html;
  }


}