#!/usr/bin/php
<?php
// A simple script to return peak power and lower power of the day.
// Set as indicator type

$MNDIR    = '/srv/http/metern'; // Path to meterN
$METNUM   = 1; // live meter number to check
$INDID = 'peak'; // this indicator ID

// No edit should be needed bellow
$prevfile = '/dev/shm/prevpeaknlow.json';
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
define('checkaccess', TRUE);
include("$MNDIR/config/config_main.php");
include("$MNDIR/config/config_met$METNUM.php");
include("$MNDIR/config/memory.php");
date_default_timezone_set($DTZ);

if (file_exists($prevfile)) {
    $prevdata = file_get_contents($prevfile);
    $previous = json_decode($prevdata, true);
} else {
    $previous['max'] = 0;
    $previous['low'] = null;
}

if (file_exists($LIVEMEMORY)) {
    $data   = file_get_contents($LIVEMEMORY);
    $array  = json_decode($data, true);
    $nowutc = strtotime(date('Ymd H:i:s'));
    
    if (isset($argv[1])) {
        if ($argv[1] == '-peak') {
            if ($nowutc - $array['UTC'] < 15 && isset($array['UTC']) && $array["${'METNAME'.$METNUM}$METNUM"] > $previous['max']) { // peak
                $previous['max'] = $array["${'METNAME'.$METNUM}$METNUM"];
                $prevdata        = json_encode($previous);
                file_put_contents($prevfile, $prevdata);
            }
            $ret = $previous['max'];
            echo "$INDID($ret*W)";
        } elseif ($argv[1] == '-low') {
            if (($nowutc - $array['UTC'] < 15 && isset($array['UTC']) && $array["${'METNAME'.$METNUM}$METNUM"] < $previous['low'] && $array["${'METNAME'.$METNUM}$METNUM"] > 0) || !isset($previous['low'])) { // low
                $previous['low'] = $array["${'METNAME'.$METNUM}$METNUM"];
                $prevdata        = json_encode($previous);
                file_put_contents($prevfile, $prevdata);
            }
            $ret = $previous['low'];
            echo "$INDID($ret*W)";
        }
        if (date('H') == 0 && date('i') == 0) { // Midnight
            $previous['max'] = $array["${'METNAME'.$METNUM}$METNUM"];
            $previous['low'] = $array["${'METNAME'.$METNUM}$METNUM"];
        }
    } else {
        echo "Usage: peaknlow { peak | low }\n";
        if (file_exists($prevfile)) {
            $data     = file_get_contents($prevfile);
            $previous = json_decode($data, true);
            print_r($previous);
        }
    }
}
?> 