<?php
// Tutorial: https://ourcodeworld.com/articles/read/342/how-to-create-with-github-your-first-psr-4-composer-packagist-package-and-publish-it-in-packagist

// If you don't to add a custom vendor folder, then use the simple class
// namespace HelloComposer;
namespace kelvinwongg\CIRestController;

class RestController extends CI_Controller {

  private $endpoint;
  private $verb;
  private $params;
  
  public function __construct() {
    parent::__construct();
    $this->endpoint = $this->router->fetch_class();
    $this->verb = $this->input->method();
    $this->params = $this->uri->uri_to_assoc(1);
    $this->load->library( 'oauth2' );
    $this->load->model( $this->endpoint . '_model', 'model' );
  }

  public function _remap($method, $params = []) {

    // If $method exist, call it,
    // except 'index' default method
    if ( $method !== 'index' && method_exists($this, $method) ) {
      call_user_func([$this, $method], $params);
    }

    // Check if the function exists for this verb
    if ( !method_exists($this, $this->verb) ) {
      response_json([
        'status' => 400,
        'message' => 'The requested HTTP Method is not supported in this endpoint.'
      ]);
      return;
    }
    
    // Call the verb with parameters
    call_user_func([$this, $this->verb], $this->params);

  }

  public function index() {
    echo $this->endpoint . '/index()';
  }

  public function get($params) {
    $this->oauth2->handleResourceRequest('basic staff');
    $ret = $this->model->get($this->endpoint, $params[$this->endpoint]);
    response_json($ret);
  }
}