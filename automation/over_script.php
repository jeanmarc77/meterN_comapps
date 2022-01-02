<?php
// A simple script to command something if a meter or a sensor exceeded a live value.

$MNDIR  = '/srv/http/metern'; // Path to meterN
$METNUM = 1; // meter/sensor number
$MAXVAL = 150; // eg 150 W
$MAXCMD = 'http://192.168.1.139/control?cmd=GPIO,12,1'; // Do something
$UNDCMD = '';

// No edit should be needed bellow
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
define('checkaccess', TRUE);
include("$MNDIR/config/config_main.php");
include("$MNDIR/config/config_met$METNUM.php");
include("$MNDIR/config/memory.php");
date_default_timezone_set($DTZ);

if (file_exists($LIVEMEMORY)) {
    $data   = file_get_contents($LIVEMEMORY);
    $array  = json_decode($data, true);
    $nowutc = strtotime(date('Ymd H:i:s'));
    
    if ($nowutc - $array['UTC'] < 15 && isset($array['UTC']) && $array["${'METNAME'.$METNUM}$METNUM"] > $MAXVAL) {
	    $ch = curl_init($MAXCMD);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000); // error
	    curl_exec($ch);
	    curl_close($ch);
        //exec("$MAXCMD", $output);
    } else {
        //exec("$UNDCMD", $output);
    }
}
?>
