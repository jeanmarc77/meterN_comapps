#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
// This script will output a meterN compatible format for the main command
// You'll need to setup the path to meterN ($pathtomn), put the meters numbers ($metnum) and the corresponding command ($cmd)
//
// ln -s  /path to/pooler.php /usr/bin/pooler and chmod +x pooler.php
//
// Optional :
// Set a initial counter value according to a real meter ($initial), set a drift positive correction ($correction)

$pathtomn = '/srv/http/metern';

if (isset($argv[1])) {
    if ($argv[1] == '-gas') {
        // meter number
        $metnum     = 2;
        // Request counters values during a 5 min period 
        $cmd        = "poolmeters rgas";
        // If the meter drift -negatively-, you can correct the data later on by changing the last record in daily csv.
        $initial    = 11633.33;
        // If counting is 2% too pessimist set it to 1.02
        $correction = 1;
    } elseif ($argv[1] == '-water') {
        $metnum     = 3;
        $cmd        = "poolmeters rwater";
        $initial    = 11430756;
        $correction = 1;
    } else {
        die("Abording: no valid argument given\n");
    }
} else {
    die("Usage: pooler { gas | water }\n");
}
// No edit should be needed bellow
define('checkaccess', TRUE);
include("$pathtomn/config/config_main.php");
include("$pathtomn/config/config_met$metnum.php");
$previous = null;

function retrievecsv($meternum, $csvarray, $passo)
{
    $datareturn = null;
    $contalines = count($csvarray);
    $j          = 0;
    while (!isset($datareturn) && $j < $contalines) {
        $j++;
        $array      = preg_split('/,/', $csvarray[$contalines - $j]);
        $datareturn = (float) trim($array[$meternum]);
        if ($datareturn == '') {
            $datareturn = null;
        }
    }
    if ($datareturn > $passo) {
        $datareturn -= $passo;
    }
    return $datareturn;
}

function isvalid($id, $datareturn) //  IEC 62056 data set structure
{
	$regexp = "/^$id\(.+\*.+\)$/i"; //ID(VALUE*UNIT)
	if (preg_match($regexp, $datareturn)) {
		$datareturn = preg_replace("/^$id\(/i", '', $datareturn, 1); // VALUE*UNIT)
		$datareturn = preg_replace("/\*.+\)$/i", '', $datareturn, 1); // VALUE
	} else {
		$datareturn = null;
	}
	return $datareturn;
}

// Retrieve last know value in last csv
$dir    = $pathtomn . '/data/csv/';
$output = array();
$output = glob($dir . '*.csv');
rsort($output);

if (file_exists($output[0])) { // today csv
    $lines    = file($output[0]);
    $previous = retrievecsv($metnum, $lines, ${'PASSO' . $metnum});
}
if (is_null($previous)) { // restarting from scratch !
    $previous = $initial;
}

// Now retrieve the current value
$datareturn = shell_exec($cmd);
$datareturn = trim($datareturn);
$last  = isvalid(${'ID'.$metnum}, $datareturn);

if(!isset($last)) {
	die("Abording: can't retrieve last\n");
}
settype($last, 'float');
settype($previous, 'float');

// Drift correction
if ($correction != 1) {
    $crt   = 1 / pow(10, ${'PRECI' . $metnum}); // a 0.01 precision with 2
    $lastc = $last * $correction; // 5,902
    
    $last     = number_format($lastc, ${'PRECI' . $metnum}); // 5.90
    $fraction = number_format(($lastc - $last), 8); // .002
    
    $prev_fragment = '/srv/http/comapps/pooler.json';
    if (file_exists($prev_fragment)) {
        $data      = file_get_contents($prev_fragment);
        $prev_frag = json_decode($data, true);
        if (!isset($prev_frag["$metnum"])) {
            $prev_frag["$metnum"] = 0;
        }
    } else {
        $prev_frag["$metnum"] = 0;
    }
    
    $fraction_sum = number_format(($prev_frag["$metnum"] + $fraction), 6); // say .00904 + .002
    
    if ($fraction_sum >= $crt) { // .01104
        $val = number_format($fraction_sum, ${'PRECI' . $metnum}); // .01
        $last += $val; // 5.91
        $fraction_sum -= $val; // .00104
    }
    
    $prev_frag["$metnum"] = number_format($fraction_sum, 8);
    $data                 = json_encode($prev_frag);
    file_put_contents($prev_fragment, $data);
}

$last += $previous;

if (${'PASSO' . $metnum} > 0 && $last > ${'PASSO' . $metnum}) { // counter pass over
    $last -= ${'PASSO' . $metnum};
}
$last = round($last, ${'PRECI' . $metnum});

$str = utf8_decode("${'ID'.$metnum}($last*${'UNIT'.$metnum})\n");
echo "$str";
?>
