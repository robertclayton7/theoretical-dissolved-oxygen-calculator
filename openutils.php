<?php

/*
author:         robert.clayton@autintexas.gov
use:            Return Theoretical DO, Corrected Pressure in Inches of Mercury and Corrected Pressure
                in Torr given current water temp and an elevation correction factor.
application:    Support Post-calibration of Datasondes
specifically:   https://utils.atxwatersheds.org/u/theopostcal/ <-view
                https://utils.atxwatersheds.org/u/theopostcal/?corr=.5&temp=22 <- json
dependencies:   All contents of the /u/ folder
last_edited:    March 2, 2017
hosted_on@:     As of February 28, 2017: https://utils.atxwatersheds.org/u/
*/
session_start();
date_default_timezone_set('America/Chicago');
require_once('openUtils.class.php');
require_once('pressure.php');
require_once('theodocal.php');

class openUtil extends openUtils
{
    public $request;

    public function __construct($request) {
        parent::__construct($request);
    }
    #<!-- end function constructor -->

    /**
    * theopostcal endpoint
    */
    protected function theopostcal() {
        if ($this->method == 'GET') {
           if($this->request['request'] == 'theopostcal/calc_theoretical_do.php') {
               // this is here only to support a published url until 06/2017 then kill
               $this->Action='calcdo';
            } else if (isset($this->request['action'])) {
             $this->Action=$this->request['action'];
         } else if (isset($this->request['elev']) && isset($this->request['temp'])) {
             $this->Action='calcdo';
            } else {
                $this->Action='view';
            }
        }
       switch($this->Action) {
       case 'status':
           $theopostcal['status'] = "GTG:TIMPANI";
           return $theopostcal;
       break;
       case 'serverstatus':
            $ss = pressure::Instance();
           return $ss;
       break;
       case 'corrpressure':
           $d = pressure::getCorrPressureTorr($this->request['elev']);
           return $d;
       break;
       case 'calcdo':
            if (!array_key_exists('elev', $this->request) ) {
                throw new Exception('Must Provide Elevation in Ft MSL');
            } else if(!array_key_exists('temp',$this->request)) {
                throw new Exception('Must Provide Temperature in Degrees C');
            } else {
                 $tp = $this->request['temp'];
                 $el = $this->request['elev'];
                 $theopostcal = new theodocal($tp,$el);
                 return $theopostcal;
            }
        break;
        case 'view':
            header("Content-Type: text/html; charset=utf-8",true);
            $theopostcal = include_once('view.php');
            return $theopostcal;
        break;
        }
        #<!-- end switch case -->

        if(!isset($theopostcal)) {
          <!-- note there was a logger -->
            $this->log->error("theopostcal hit - invalid action.");
            die();
        }
    }
    #<!-- end theopostcal endpoint -->

}
#<!-- end class theoDO -->


// Requests from the same server don't have a HTTP_ORIGIN header
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
   $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
    }
try {
  <!-- note there was a logger -->    
  $outerlog->info('Open Utils pinged from ' . $_SERVER['HTTP_ORIGIN']);
       $OpenUtility = new openUtil($_REQUEST['request']);
       echo $OpenUtility->processOpenUtils();

    } catch (Exception $e) {
       echo json_encode(Array('error: ' => $e->getMessage()));
          <!-- note there was a logger -->
         $outerlog->error($e->getMessage());
    }

?>
