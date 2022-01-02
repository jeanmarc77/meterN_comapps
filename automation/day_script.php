#!/usr/bin/php
<?php
// A simple script to command something if a meter exceeded or is under a value over the day.

$MNDIR  = '/srv/http/metern'; // Path to meterN
$METNUM = 1; // meter number
$MAXVAL = 15000; // eg 15kWh
$MAXCMD = ''; // If over $MAXVAL, do something
$LOWVAL = 15000;
$LOWCMD = ''; // If under $LOWVAL

// No edit should be needed bellow
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
define('checkaccess', TRUE);
include("$MNDIR/config/config_main.php");
include("$MNDIR/config/memory.php");
include("$MNDIR/config/config_met$METNUM.php");

if (file_exists($MEMORY)) {
    $data  = file_get_contents($MEMORY);
    $array = json_decode($data, true);
    
    if (isset($array["First$METNUM"]) && isset($array["Last$METNUM"])) {
        if ($array["First$METNUM"] <= $array["Last$METNUM"]) {
            $val = $array["Last$METNUM"] - $array["First$METNUM"];
        } else { // counter pass over
            $val = $array["Last$METNUM"] + ${'PASSO' . $METNUM} - $array["First$METNUM"];
        }
        
        if ($val > $MAXVAL) { // over
            exec("$MAXCMD", $output);
        }
        if ($val < $LOWVAL) { // under
            exec("$LOWCMD", $output);
        }
    }
}
?>