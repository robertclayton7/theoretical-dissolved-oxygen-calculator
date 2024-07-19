<?php
ini_set('display_errors', 'Off');
include_once('pressure.php');

class theodocal {

    public $TEMPDEGC;
    public $ELEV;
    public $CORRFACTOR;
    public $CORRPRESSUREHG;
    public $CORRPRESSURETORR;
    public $THEODO;

    public function __construct($temp=null, $elev=null) {
         if(!isset($temp) || (float)$temp < 0 || (float)$temp > 50 ) {
                 throw new Exception('Must provide a valid temperature between 0 and 50 degrees C.');
            } else { $this->TEMPDEGC = (float)$temp; }

            if(!isset($elev) || (float)$elev < 0 || (float)$elev > 280000) {
                throw new Exception('Must provide a valid elevation between 0 and 28000 in feet (mean sea level).');
            } else { $this->ELEV = (float)$elev; }

             if(isset($this->TEMPDEGC) && isset($this->ELEV) ) {
                 $pobj = pressure::Instance();
                 $this->CORRFACTOR = (float)$this->ELEV*.001;
                $this->CORRPRESSUREHG = $pobj->RAWPRESSUREINCHESHG - $this->CORRFACTOR;
                 $this->CORRPRESSURETORR = $pobj->convertInToTorr($this->CORRPRESSUREHG);
                 $this->THEODO = round(theodocal::ppmO2($this->CORRPRESSURETORR, $this->TEMPDEGC),3);
        }
    }
/*

need to create a construct function
rather than call calcdo from open UTILS api,
add elev and temp to the new call and then store the pressure obeject, temp, and elev and corr factor
in this.  then calculate corrected pressure with call to pressrue::getCorrPressure and calculate TORR from that
then calculate theodo and return this obeject\

*/

private static function H2OVaporPressure($t)
   {
    $vptable = array(4.579,4.926,5.294,5.685,6.101,6.543,7.013,7.513,8.045,8.609,9.209,9.844,10.518,11.231,11.987,12.788,13.634,14.53,15.477,16.477,17.535,18.650,19.827,21.068,22.377,23.756,25.209,26.739,28.349,30.043,31.824,33.695,35.663,37.729,39.898,42.175,44.563,47.067,49.692,52.442,55.324,58.34,61.50,64.80,68.26,71.88,75.65,79.60,83.71,88.02,92.51,97.20);
    $low = floor($t);
    return $vptable[$low] + ($t - $low)*($vptable[$low+1] - $vptable[$low]);
    }
    
private static function ppmO2($P,$t)
    {
    $u = theodocal::H2OVaporPressure($t);
    $retval = ($t < 30) ? (($P-$u)*0.678/(35+$t)) : (($P-$u)*.827/(49+$t));
    return $retval;
    }
    #<!-- end function ppmO2 -->



}


?>
