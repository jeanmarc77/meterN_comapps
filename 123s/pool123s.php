#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
// This script will output a 123solar counter into a meterN compatible format
// Configure, then ln -s /srv/http/comapps/123s/pool123s.php /usr/bin/pool123s and chmod +x pool123s.php
// Request Main command with 'pool123s -energy' and live command 'pool123s -power'

// 123solar config
$pathto123s = '/srv/http/123solar';
$invtnum    = 1;
// meterN config
$METERID    = 'solar';

// No edit is needed below
if (isset($argv[1]) && ($argv[1] == '-power' || $argv[1] == '-energy')) {
    include("$pathto123s/config/memory.php");
    $KWHT = null;
    $GP   = null;
    
    if (file_exists($MEMORY)) {
        $data     = file_get_contents($MEMORY);
        $memarray = json_decode($data, true);
        
        if ($memarray['awake'] && file_exists($LIVEMEMORY)) {
            $data     = file_get_contents($LIVEMEMORY);
            $memarray = json_decode($data, true);
            $nowUTC   = strtotime(date("Ymd H:i:s"));
            if ($argv[1] == '-power') {
                if ($nowUTC - $memarray["SDTE$invtnum"] < 300) {
                    $GP = $memarray["G1P$invtnum"] + $memarray["G2P$invtnum"] + $memarray["G3P$invtnum"];
                    $GP = round($GP, 1);
                } else { // Too old
                    die("Abording: Too late value\n");
                }
                
            } elseif ($argv[1] == '-energy') {
                if ($nowUTC - $memarray["SDTE$invtnum"] < 600) {
                    if (isset($memarray["KWHT$invtnum"])) {
                        $KWHT = round($memarray["KWHT$invtnum"] * 1000); // Wh
                    } else {
                        die("Abording: KWHT not defined\n");
                    }
                } else {
                    die("Abording: Too late value\n");
                }
            }
        }
    }
    if ($argv[1] == '-power') {
        echo "$METERID($GP*W)\n";
    } elseif ($argv[1] == '-energy') {
        echo "$METERID($KWHT*Wh)\n";
        
    }
} else {
    die("Usage: pool123s { power | energy }\n");
}
?>