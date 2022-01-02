<?php
// A simple script to command something if a house production meter's is over a house consumption's meter.

$MNDIR      = '/srv/http/metern'; // Path to meterN
$CONSMETNUM = 1; // Consumption meter number
$PRODMETNUM = 4; // Production meter number

$OVERT   = 150; // over threshold
$OVERCMD = 'ls'; // Do something
$UNDERT  = 0; // under threshold
$UNDCMD  = ''; // Do something

// No edit should be needed bellow
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
define('checkaccess', TRUE);
include("$MNDIR/config/config_main.php");
include("$MNDIR/config/config_met$CONSMETNUM.php");
include("$MNDIR/config/config_met$PRODMETNUM.php");
include("$MNDIR/config/memory.php");
date_default_timezone_set($DTZ);

if (file_exists($LIVEMEMORY)) {
    $data   = file_get_contents($LIVEMEMORY);
    $array  = json_decode($data, true);
    $nowutc = strtotime(date('Ymd H:i:s'));
    
    if ($nowutc - $array['UTC'] < 15 && isset($array['UTC'])) {
        $val = $array["${'METNAME'.$PRODMETNUM}$PRODMETNUM"] - $array["${'METNAME'.$CONSMETNUM}$CONSMETNUM"];
        if ($val >= $OVERT) {
            exec("$OVERCMD", $output);
        }
        
        if ($val <= $UNDERT) {
            exec("$UNDCMD", $output);
        }
    }
}
?>