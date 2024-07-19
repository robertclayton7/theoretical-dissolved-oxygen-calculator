<?php
ini_set('display_errors', 'On');
final class pressure {
   public $DATETIME;
   public $RAWPRESSUREINCHESHG;
   public $STATUSMSG;
   public $STATUS; // {ERR,GTG}
   public $NOAAKATTURL;


   public static function Instance()
   {
       static $inst = null;
       if ($inst === null) {
           $inst = new pressure();
       }
       return $inst;
   }

   private function __construct() {
            $filename = $_SERVER['HTTP_HOST'].'.ini';
            $settings = parse_ini_file("../../inc/$filename");
            $this->NOAAKATTURL = $settings['katt_url'];
            $dt = new DateTime;
            $dateformatted = $dt->format('YmdHis');
            $this->DATETIME = $dateformatted;
            pressure::getPressure($dateformatted,$this);
       }


    public static function getPressure($dtme,$p) {
        // returns raw pressure with a datetime
        // returns pressure object
        if (isset($_SESSION['prevNOAAdata'])) {
            $pData = json_decode($_SESSION['prevNOAAdata']);
        //     echo var_dump($pData);
              if(substr($pData->STATUSMSG,0,3) != 'ERR') {
                $pData->STATUSMSG = str_replace('KATT','SESSION',$pData->STATUSMSG);
                $prevNOAAdata= $pData;
             }
        } else {
            $prevNOAAdata = json_decode(file_get_contents('data.txt'));
            $_SESSION['prevNOAAdata']=json_encode($prevNOAAdata);
            $prevNOAAdata->STATUSMSG = str_replace('SESSION','FILE',str_replace('KATT','FILE',$prevNOAAdata->STATUSMSG));
        }
        if (isset($prevNOAAdata->DATETIME)) {
            $datediff = $dtme-$prevNOAAdata->DATETIME;
        } else { $datediff = 11000; }
//        echo var_dump($prevNOAAdata);

        if ($datediff > 10000 || $prevNOAAdata->STATUS == 'ERR'  ) {
            // get data from noaa -- save to disk and session vars
                //echo('noaa');
                $data = json_decode(pressure::getNoaaData($p->NOAAKATTURL));
                if($data->rawPressureHG == 0) {

                    $p->RAWPRESSUREINCHESHG = $data->rawPressureHG;
                    $p->STATUSMSG = 'ERR: ' . $p->NOAAKATTURL;
                    $p->STATUS = 'ERR';
                    file_put_contents('data.txt',json_encode($p));
                    $_SESSION['prevNOAAdata']=json_encode($p);
                } else {
                    $p->RAWPRESSUREINCHESHG = $data->rawPressureHG;
                    $p->STATUSMSG = $data->Status;
                    $p->STATUS = substr($data->Status,0,3);
                    file_put_contents('data.txt',json_encode($p));
                    $_SESSION['prevNOAAdata']=json_encode($p);
                }

        } else {
            $p->RAWPRESSUREINCHESHG = $prevNOAAdata->RAWPRESSUREINCHESHG;
            $p->STATUSMSG = $prevNOAAdata->STATUSMSG;
            $p->STATUS = $prevNOAAdata->STATUS;
        }
    }


    public static function getCorrPressureTorr($elev) {
        if (isset($_SESSION['prevNOAAdata']) && is_numeric($elev)) {
              $pData = json_decode($_SESSION['prevNOAAdata']);
              if(substr($pData->STATUSMSG,0,3) != 'ERR') {
                $corrFactor = (float)$elev*0.001;
                $current_pressure_corr_hg = $pData->RAWPRESSUREINCHESHG-$corrFactor;
                $current_pressure_corr_torr = pressure::convertInToTorr($current_pressure_corr_hg);
                $cp = Array('Status' => 'GTG: SESSION', 'CorrPressureHG' => $current_pressure_corr_hg, 'CorrPressureTorr' => $current_pressure_corr_torr);
              } else {
                  // $pData->RAWPRESSUREINCHESHG contains an error message in this else
                $cp = Array('Status' => $pData->STATUSMSG, 'CorrPressureHG' => $pData->RAWPRESSUREINCHESHG, 'CorrPressureTorr' => $pData->RAWPRESSUREINCHESHG);
              }
        } else if (is_numeric($elev)) {
            $prevNOAAdata = json_decode(file_get_contents('data.txt'));
            $_SESSION['prevNOAAdata']=json_encode($prevNOAAdata);
            $prevNOAAdata->STATUSMSG = str_replace('SESSION','FILE',str_replace('KATT','FILE',$prevNOAAdata->STATUSMSG));
            $current_pressure_corr_torr = pressure::convertInToTorr($current_pressure_corr_hg);
            $cp = Array('Status' => $prevNOAAdata->STATUSMSG, 'CorrPressureHG' => $pData->RAWPRESSUREINCHESHG, 'CorrPressureTorr' => $current_pressure_corr_torr);
        }

        return $cp;
    }
    #<!-- End Function getCorrPressureTorr -->

    public static function convertInToTorr($inHG) {
                $torrVal = 25.4 * $inHG;
                return $torrVal;
        }
        #<!-- end function convert to torr -->



   private static function getNoaaData($url) {
        #<!-- returns json encoded array with dt and pressure -->
        $contents = null;
        $slashflag = false;
        $status = null;
        $rawPressureHG = 0;

        try{
        // This scraper code is basic and will need to be re-written if this web page ever changes
        // Have to have your head in the game
        ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');

        if(pressure::get_http_response_code($url) != "200"){
          throw new Exception('bad page in ' . $url);
        }else{
          $contents = file_get_contents($url);
        }

        $rows = explode("\n", $contents);
        array_shift($rows);
        foreach($rows as $row => $data)
        {
            //get row data
            $row_data = explode(' ', $data);
                foreach($row_data as $row_datum => $datum ) {
                    // look for a five-character string starting with a Capital A
                    // it will take the last one -- rewrite as needed.
                    if(substr($datum,0,1) == 'A' && mb_strlen($datum,'utf8')==5) { $rawPressureHG = (float)substr($datum,1,4)*.01;}
                    if($datum == 'KATT') { $status = 'GTG: KATT';}
                }
        }

        } catch (Exception $e) {
                $emsg = $e->getMessage();
                $resp = json_encode(Array('Status' => "ERR: $emsg",'rawPressureHG' => 'Pressure Server Error'));
        }

        $resp = json_encode(Array('Status' => "$status",'rawPressureHG' => "$rawPressureHG"));

        /* made so much cleaner having found the raw file that looks like so:
        ****************************
        000
        SAUS80 KWBC 160200
        MTRATT
        METAR KATT 160151Z AUTO 17005KT 10SM OVC055 20/12 A3024 RMK AO2 SLP237
        T02000117 $
        ****************************
        format notation here: https://math.la.asu.edu/~eric/workshop/METAR.html
        note that you cannot assume that ATM will be in a given bin after explode by space:
            sometimes 8, sometimes 9 ... the sky is the limit.

        And sometimes it's this god-awful thing where it's on a different row altogether:
        *****************************
        000
        SAUS80 KWBC 101700 CCA
        MTRATT
        METAR KATT 101651Z AUTO VRB03KT 3/4SM +RA BR SCT008 BKN013 OVC017 22/21
        A3004 RMK AO2 RAB1554E12B36 SLP164 P0004 T02220211
        *****************************
        */

       return $resp;

    }
    #<!-- end function getNoaaData() -->

    private static function get_http_response_code($url) {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }
    #<!-- end function get_http_response_code -->

}
#<!-- End Class wh -->

?>
