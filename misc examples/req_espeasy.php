#!/usr/bin/php
<?php
// A simple script to request a espeasy counter that doesn't always increase (after a surge) and make virtual meter.
// You should set your meter ID in meterN and define a non null pass over value (like 100000)
// chmod +x then ln -s /srv/http/comapps/req_espeasy.php /usr/bin/req_espeasy
// Use req_espeasy -total as main command -live as live command

// Config
$pathtomn  = '/srv/http/metern'; // without / at the end
$metnum    = 1; // Vitual meter number
$url       = 'http://IP_OF_ESP/json?tasknr=2'; // espeasy url
$initial   = 0; // initial virtual meter value
$urlreboot = 'http://IP_OF_ESP/?cmd=reboot'; // restart counting on reboot

// No edit should be needed bellow
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
if (isset($argv[2])) {
    die("Abording: Too many arguments\n");
}
if (!isset($argv[1])) {
    die("\nUsage: req_espeasy { total | live | Humidity | prev }\n
    -total : Virtual total counter
    -live : Counter live value
    -Humidity : Show humidity value
    -prev : Show previous file
    \n");
}
// Save previous values in a file, make sure the http user can write there
$prevfile = '/dev/shm/req_espeasy.json';
if (file_exists($prevfile) && !is_writable($prevfile)) {
    die("$prevfile not writable");
}

define('checkaccess', TRUE);
include("$pathtomn/config/config_main.php");
include("$pathtomn/config/memory.php");
include("$pathtomn/config/config_met$metnum.php");

function retrievecsv($meternum, $csvarray, $passo) // Retrieve last know value in csv
{
    $datareturn = null;
    $contalines = count($csvarray);
    $j          = 0;
    while (!isset($datareturn)) {
        $j++;
        $array      = preg_split('/,/', $csvarray[$contalines - $j]);
        $datareturn = (float) trim($array[$meternum]);
        if ($datareturn == '') {
            $datareturn = null;
        }
        if ($j == $contalines) {
            $datareturn = 0;
        }
    }
    if ($datareturn > $passo) {
        $datareturn -= $passo;
    }
    return $datareturn;
}

if ($argv[1] == '-total' && !file_exists($prevfile)) {
    // Reboot
    $ch = curl_init($urlreboot);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000); // error
    if ((curl_exec($ch)) === false) {
        die(curl_error($ch) . "\n");
    }
    curl_close($ch);
    sleep(18);
}

if ($argv[1] == '-live' || $argv[1] == '-total' || $argv[1] == '-Humidity') { // Get last values
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000); // error
    $espjson = array();
    if (($espjson = curl_exec($ch)) === false) {
        die(curl_error($ch) . "\n");
    }
    curl_close($ch);
    
    // Testing
    /*
    $espjson = '
    {  
    "TaskValues":[  
    {  
    "ValueNumber":1,
    "Name":"Count",
    "NrDecimals":2,
    "Value":10000.00
    },
    {  
    "ValueNumber":2,
    "Name":"Total",
    "NrDecimals":2,
    "Value":1000.00
    },
    {  
    "ValueNumber":3,
    "Name":"Time",
    "NrDecimals":2,
    "Value":0.00
    }
    ],
    "TTL":1000,
    "DataAcquisition":[  
    {  
    "Controller":1,
    "IDX":0,
    "Enabled":"false"
    },
    {  
    "Controller":2,
    "IDX":0,
    "Enabled":"false"
    },
    {  
    "Controller":3,
    "IDX":0,
    "Enabled":"false"
    }
    ],
    "TaskInterval":1,
    "Type":"Generic - Pulse counter",
    "TaskName":"gas",
    "TaskEnabled":"true",
    "TaskNumber":3
    }';
    */
    $espjson   = json_decode($espjson, true);
    // Get values from json
    $val_live  = $espjson['TaskValues'][0]['Value'];
    $val_count = $espjson['TaskValues'][1]['Value'];
    $val_count /= 100; // 1 impulse = 0.01m3 gas
    $val_humid = $espjson['TaskValues'][1]['Value'];
    //print_r($espjson);
}

if ($argv[1] == '-live') {
    if (!isset($val_live)) {
        die("Abording: Cannot get live Count value\n");
    }
    $val    = (float) $val_live;
    $outstr = utf8_decode("${'LID' . $metnum}($val*${'LIVEUNIT' . $metnum})\n");
    echo "$outstr";
} elseif ($argv[1] == '-total') {
    if (!isset($val_count)) {
        die("Abording: Cannot get last Total value\n");
    }
    // Retrieve previous virtual meter value
    if (file_exists($prevfile)) {
        $data     = file_get_contents($prevfile);
        $previous = json_decode($data, true);
    } else {
        // Retrieve last know value in last csv
        $dir    = $pathtomn . '/data/csv/';
        $output = array();
        $output = glob($dir . '*.csv');
        rsort($output);
        
        if (file_exists($output[0])) {
            $lines                = file($output[0]);
            $previous['virt_tot'] = retrievecsv($metnum, $lines, ${'PASSO' . $metnum});
        } else { // restarting from scratch !
            $previous['virt_tot'] = $initial;
        }
        $previous['esp_count'] = 0;
    }
    $last = (float) $val_count;
    $prev = (float) $previous['esp_count'];
    if ($last > $prev) {
        $diff = $last - $prev; // Increment vitural meter
        $previous['virt_tot'] += $diff;
        if ($previous['virt_tot'] >= ${'PASSO' . $metnum}) { // passed over
            $previous['virt_tot'] -= ${'PASSO' . $metnum};
        }
        $previous['esp_count'] = $val_count;
    } elseif ($last < $prev) { // surge or esp counter restart
        $previous['esp_count'] = 0;
    }
    
    // Saving previous values
    $data = json_encode($previous);
    file_put_contents($prevfile, $data);
    $val    = $previous['virt_tot'];
    // Output
    $outstr = utf8_decode("${'ID' . $metnum}($val*${'UNIT' . $metnum})\n");
    echo "$outstr";
} elseif ($argv[1] == '-Humidity') {
    $val    = $val_humid;
    $outstr = utf8_decode("Humidity($val*hum)\n");
    echo "$outstr";
} elseif ($argv[1] == '-prev') {
    if (file_exists($prevfile)) {
        echo "\n$prevfile :\n\n";
        $data     = file_get_contents($prevfile);
        $previous = json_decode($data, true);
        print_r($previous);
    }
}
?>
