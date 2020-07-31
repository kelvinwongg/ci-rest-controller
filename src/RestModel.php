<?php
/**
 * Standard model for Restful Controller
 */
namespace kelvinwongg\CIRestController;

class RestModel extends \CI_Model {

  public function __construct() {
    parent::__construct();
    $this->load->database();
  }

  public function get($table = FALSE, $id = NULL) {
    if ( !$table ) return FALSE;
    if ($id === NULL) {
      $query = $this->db->get($table);
      return $query->result_array();
    } else {
      $query = $this->db->get_where($table, ['id' => $id]);
      $ret = $query->row_array();
      return ($ret) ? $ret : [];
    }
  }

  public function post($table = FALSE, $data = NULL) {
    if ( !$table || !$data ) return FALSE;
    if ( !$this->db->insert($table, $data) ) return $this->db->error();
    return $this->db->insert_id();
  }
  
  public function patch($table=FALSE, $id=FALSE, $data=FALSE) {
    if ( !$table || !$id || !$data ) return FALSE;
    if ( !$this->db->where('id', $id)->update($table, $data) ) return $this->db->error();
    return TRUE;
  }

  public function delete($table=FALSE, $id=FALSE) {
    if ( !$table || !$id ) return FALSE;
    if ( !$this->db->where('id', $id)->delete($table) ) return $this->db->error();
    return ($this->db->affected_rows()) ? TRUE : FALSE;
  }
}