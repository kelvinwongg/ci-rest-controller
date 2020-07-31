<?php
/**
 * Restful Controller for Codeigniter
 */
namespace kelvinwongg\CIRestController;

class RestController extends \CI_Controller {

  protected $endpoint;
  protected $verb;
  protected $params;
  
  public function __construct() {
    parent::__construct();
    $this->endpoint = $this->router->fetch_class();
    $this->verb = $this->input->method();
    $this->params = $this->uri->uri_to_assoc(1);
    $this->load->library( 'oauth2' );
    $this->load->model( 'restful_model', 'model' );
  }

  /**
   * Codeigniter Magic _remap function:
   * 1. remap all requests based on its verb
   * 2. call if method exists
   * 3. response error if method/verb is not exists
   */
  public function _remap($method, $params = []) {

    // If $method exist, call it,
    // except 'index' default method
    if ( $method !== 'index' && method_exists($this, $method) ) {
      call_user_func([$this, $method], $params);
      return;
    }

    // If the function do not exists for this verb
    if ( !method_exists($this, $this->verb) ) {
      json_response([
        'status' => 400,
        'message' => 'The requested HTTP Method is not supported in this endpoint.'
      ]);
      return;
    }

    // Call the verb with parameters
    call_user_func([$this, $this->verb], $this->params);
    return;

  }

  // For test purpose only.
  public function index() { echo $this->endpoint . '/index()'; }

  /**
   * Restful GET request
   */
  public function get($params) {
    // $this->oauth2->handleResourceRequest('staff');
    $ret = $this->model->get(
      $this->endpoint,
      $params[$this->endpoint]
    );
    json_response(['result'=>$ret]);
  }

  /**
   * Restful POST request (JSON)
   * with upload files handling
   */
  public function post($params) {
    $this->oauth2->handleResourceRequest('staff');
    
    // Parse JSON form data
    $data = json_decode(file_get_contents('php://input'), true);

    // Pop the 'files' from request data
    if (array_key_exists('files', $data)) {
      $files = $data['files'];
      unset($data['files']);
    } else { $file = NULL; }

    // Process the request data
    $ret['data'] = $this->model->post(
      $this->endpoint,
      $data
    );

    // Process the tmp files
    if ($files && gettype($ret['data']) == 'integer') {
      $ret['files'] = $this->_processTmpFiles($files, $ret['data']);
    } else { $ret['files'] = FALSE; }

    // Patch the object with files
    if ( $ret['files'] ) {
      if ( !$this->model->patch(
        $this->endpoint,
        $ret['data'],
        ['files' => implode(',', $ret['files'])]
      )) {
        $ret['files'] = FALSE; // patch files data failed
      }
    }

    json_response(['result'=>$ret]);
  }

  /**
   * Restful PATCH request (JSON)
   */
  public function patch($params) {
    $this->oauth2->handleResourceRequest('staff');
    // Parse JSON form data
    $data = json_decode(file_get_contents('php://input'), true);
    $ret = $this->model->patch(
      $this->endpoint,
      $params[$this->endpoint],
      $data
    );
    json_response(['result'=>$ret]);
  }

  /**
   * Restful DELETE request
   */
  public function delete($params) {
    // $this->oauth2->handleResourceRequest('staff');
    $ret = $this->model->delete(
      $this->endpoint,
      $params[$this->endpoint]
    );
    json_response(['result'=>$ret]);
  }

  /**
   * Handle upload files separately with the Restful requests
   */
  public function uploadfiles($params) {
    $this->oauth2->handleResourceRequest('staff');
    if (count($params)) { $id = $params[0]; }
    else { $id = FALSE; }
    $ret = $this->_handleUploadFiles( $id );
    json_response(['result'=>$ret]);
  }

  /**
   * @param id of the object to which the upload file(s) belongs
   * For rename upload file(s) in format:
   * [ENDPOINT]_[id]_[original-file-name.ext]
   * For example:
   * POST /invoice (with Image.jpg, id=123)
   * => /uploads/invoice/invoice_123_Image.jpg
   * @return FALSE if no file is uploaded.
   * @return array successful file upload data,
   * or failed file upload error.
   */
  private function _handleUploadFiles($id = FALSE) {
    $ret = FALSE;
    // Check if any file(s) uploaded, handle file upload
    if ( array_key_exists('upload_files',$_FILES) ) {
      // Load and configure 'upload' library
      $this->load->library('upload');
      // If $id is not pass, save the upload files in 'tmp' folder
      $config['upload_path'] = './uploads/' . (($id) ? $this->endpoint : 'tmp');
      $config['allowed_types'] = 'gif|jpg|png';
      
      // 
      // If variable type is string, handle SINGLE file upload
      // 
      if (gettype($_FILES['upload_files']['name']) === 'string') {
        // Rename this file
        $config['file_name'] = $this->formatFilename($_FILES['upload_files']['name'], $id);
        $this->upload->initialize($config);

        // Process upload on the server
        if ($this->upload->do_upload('upload_files')) {
          $ret[] = $this->upload->data();
        } else {
          $ret[] = $this->upload->display_errors();
        }
      }

      // 
      // If variable type is array, handle MULTIPLE file upload
      // 
      if (gettype($_FILES['upload_files']['name']) === 'array') {
        foreach ($_FILES['upload_files']['name'] as $key => $name) {
          // Rename this file
          $config['file_name'] = $this->formatFilename($name, $id);
          $this->upload->initialize($config);

          // Create temporary $_FILES['tmp'] for this upload file
          $_FILES['tmp']['name']     = $_FILES['upload_files']['name'][$key];
          $_FILES['tmp']['type']     = $_FILES['upload_files']['type'][$key];
          $_FILES['tmp']['tmp_name'] = $_FILES['upload_files']['tmp_name'][$key];
          $_FILES['tmp']['error']    = $_FILES['upload_files']['error'][$key];
          $_FILES['tmp']['size']     = $_FILES['upload_files']['size'][$key];

          // Process upload on the server
          if ($this->upload->do_upload('tmp')) {
            $ret[] = $this->upload->data();
          } else {
            $ret[] = $this->upload->display_errors();
          }
        }
      }
    }
    return $ret;
  }

  private function formatFilename($filename, $id=FALSE) {
    $timestamp = date('YmdHis');
    return $this->endpoint . '_' . (($id) ? $id : 'tmp') . '_' . $timestamp . '_' . $filename;
  }

  /**
   * Search, rename, and move upload files,
   * from the 'tmp' folder to the correct folder.
   * @param file array of filename.ext
   * @param id of the object to which the files belongs
   * @return array of successful filepath(s)
   * @return FALSE on file(s) search, rename, or more failed
   */
  private function _processTmpFiles($files, $id) {
    $ret = [];
    foreach($files as $key => $this_file) {
      $oldPath = "uploads/tmp/$this_file";
      if ( file_exists($oldPath) ) {
        $filename = str_replace('tmp', $id, $this_file);
        $newPath = "uploads/$this->endpoint/$filename";
        if (rename($oldPath, $newPath)) {
          $ret[] = $newPath;
        } else { $ret[] = FALSE; } // rename or move failed
      } else { $ret[] = FALSE; } // file do not exists
    }
    return ( !count(array_diff($ret, [FALSE])) ) ? FALSE : array_diff($ret, [FALSE]);
  }
}