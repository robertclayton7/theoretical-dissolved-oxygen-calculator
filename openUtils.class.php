<?php
abstract class openUtils
{   /** with thanks to: http://coreymaynard.com/blog/creating-a-restful-api-with-php/ */

    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = Array();

    /**
     * Constructor: __construct
     * Assemble and pre-process the data
     */
    public function __construct($request) {
        header("Content-Type: application/json; charset=utf-8");
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);


        $this->method = $_SERVER['REQUEST_METHOD'];
        switch($this->method) {
        case 'GET':
            $this->request = $this->_cleanInputs($_GET);
            break;
        default:
            $this->_response('Invalid Method', 405);
            break;
        }
    }

    public function processOpenUtils() {
         if (method_exists($this, $this->endpoint)) {
             $pOU = $this->_response($this->{$this->endpoint}($this->args));
             return $pOU;
         }
     }

     private function _response($data, $status = 200) {
         header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
         $retval = json_encode($data);
         return $retval;
     }

     private function _cleanInputs($data) {
         $clean_input = Array();
         if (is_array($data)) {
             foreach ($data as $k => $v) {
                 $clean_input[$k] = $this->_cleanInputs($v);
             }
         } else {
             $clean_input = trim(strip_tags($data));
         }
         return $clean_input;
     }

     private function _requestStatus($code) {
         $status = array(
             200 => 'OK',
             404 => 'Not Found',
             405 => 'Method Not Allowed',
             500 => 'Internal Server Error',
         );
         return ($status[$code])?$status[$code]:$status[500];
     }
}
?>
