<?php
class Paginator {

  private $_conn;
  private $_limit;
  private $_page;
  private $_query;
  private $_type;
  private $_total;

  public function __construct( $query ) {
    $rs = sqlStatement($query);
    $total = (sqlNumRows($rs)) ? sqlNumRows($rs) : 0;
    $this->_query = $query;
    $this->_total = $total;
  }


  public function getData( $limit = 10, $page = 1 ) {
    $this->_limit   = $limit;
    $this->_page    = $page;
    $results        = [];

    if ( $this->_limit == 'all' || $this->_limit == '-1' ) {
        $query      = $this->_query;
    } else {
        $query      = $this->_query . " LIMIT " . ( ( $this->_page - 1 ) * $this->_limit ) . ", $this->_limit";
    }
    

    $rs = sqlStatement($query);
    $total = 0;
    if( $total = sqlNumRows($rs) ) {
      while ($row = sqlFetchArray($rs)) {
        if($row) {
          $results[]  = $row;
        }
      }
    }



    $result         = new stdClass();
    $result->page   = $this->_page;
    $result->limit  = $this->_limit;
    $result->total  = $total;
    $result->data   = $results;
    return $result;
  }


  public function createLinks( $links, $list_class, $params=null ) {
      if ( $this->_limit == 'all' || $this->_limit == '-1') {
        return '';
      }

      $addons = '';
      if($params) {
        foreach($params as $k=>$v) {
          $addons .='&'.$k.'='.$v;
        }
      }
    
      $last       = ceil( $this->_total / $this->_limit );
    
      $start      = ( ( $this->_page - $links ) > 0 ) ? $this->_page - $links : 1;
      $end        = ( ( $this->_page + $links ) < $last ) ? $this->_page + $links : $last;
    
      $html       = '<ul class="' . $list_class . '">';
    
      $class      = ( $this->_page == 1 ) ? "disabled" : "";
      
      if($addons) {
        $html       .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . ( $this->_page - 1 ) . $addons.'">&laquo;</a></li>';
      } else {
        $html       .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . ( $this->_page - 1 ) . '">&laquo;</a></li>';
      }

      if ( $start > 1 ) {
          if($addons) {
            $html   .= '<li><a href="?limit=' . $this->_limit . '&page=1'.$addons.'">1</a></li>';
          } else {
            $html   .= '<li><a href="?limit=' . $this->_limit . '&page=1">1</a></li>';
          }
          $html   .= '<li class="disabled"><span>...</span></li>';
      }
    
      for ( $i = $start ; $i <= $end; $i++ ) {
          $class  = ( $this->_page == $i ) ? "active" : "";
          if($addons) {
            $html   .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . $i .$addons.'">' . $i . '</a></li>';
          } else {
            $html   .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . $i . '">' . $i . '</a></li>';
          }
          
      }
    
      if ( $end < $last ) {
        $html   .= '<li class="disabled"><span>...</span></li>';
        if($addons) {
          $html   .= '<li><a href="?limit=' . $this->_limit . '&page=' . $last . $addons.'">' . $last . '</a></li>';
        } else {
          $html   .= '<li><a href="?limit=' . $this->_limit . '&page=' . $last . '">' . $last . '</a></li>';
        }
      }
    
      $class      = ( $this->_page == $last ) ? "disabled" : "";
      if($addons) {
        $html       .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . ( $this->_page + 1 ) . $addons.'">&raquo;</a></li>';
      } else {
        $html       .= '<li class="' . $class . '"><a href="?limit=' . $this->_limit . '&page=' . ( $this->_page + 1 ) . '">&raquo;</a></li>';
      }
      $html       .= '</ul>';
    
      return $html;
  }

}