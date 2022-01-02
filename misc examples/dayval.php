#!/usr/bin/php
<?php
// A simple script to show daily value.

$MNDIR  = '/srv/http/metern'; // Path to meterN
$METNUM = 1; // meter number

// No edit should be needed bellow
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
define('checkaccess', TRUE);
include("$MNDIR/config/config_main.php");
include("$MNDIR/config/config_met$METNUM.php");
include("$MNDIR/config/memory.php");

if (file_exists($MEMORY)) {
    $data  = file_get_contents($MEMORY);
    $array = json_decode($data, true);
    
    if (isset($array["First$METNUM"]) && isset($array["Last$METNUM"])) {
        if ($array["First$METNUM"] <= $array["Last$METNUM"]) {
            $val = $array["Last$METNUM"] - $array["First$METNUM"];
        } else { // counter pass over
            $val = $array["Last$METNUM"] + ${'PASSO' . $METNUM} - $array["First$METNUM"];
        }
        $outstr = utf8_decode("${'ID'.$METNUM}($val*${'UNIT'.$METNUM})\n");
        echo "$outstr";
    } else {
        die("Abording: Missing first or last value\n");
    }
    
} else {
    die("Abording: Empty $MEMORY\n");
}
?>