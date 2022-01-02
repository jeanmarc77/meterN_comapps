#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
error_reporting(~E_WARNING);
// This script will output a 123solar counter into a meterN compatible format
// Configure, then ln -s /srv/http/comapps/remotepool123s.php /usr/bin/remotepool123s and chmod +x remotepool123s.php
// Request Main command with 'remotepool123s -energy' and live command 'remotepool123s -power'

// 123solar config
$remotedata = file_get_contents("http://192.168.0.100/123solar/programs/programlive.php?invtnum=1");
//print_r($remotedata);
// meterN config
$METERID    = 'solar';

// No edit is needed below
if (isset($argv[1])) {
    $KWHT = null;
    if (!empty($remotedata)) {
        $memarray = json_decode($remotedata, true);
        $nowUTC   = strtotime(date("Ymd H:i:s"));
        if ($argv[1] == '-power') {
            if ($nowUTC - $memarray["SDTE"] < 30) {
                $GP = $memarray["G1P"] + $memarray["G2P"] + $memarray["G3P"];
                $GP = round($GP, 1);
            } else { // Too old
                $GP = 0;
            }
            echo "$METERID($GP*W)\n";
        } elseif ($argv[1] == '-energy') {
            if ($nowUTC - $memarray["SDTE"] < 600) {
                if (isset($memarray["KWHT"])) {
                    $KWHT = round($memarray["KWHT"] * 1000); // Wh
                    echo "$METERID($KWHT*Wh)\n";
                } else {
                die("Abording: KWHT not defined\n");
		}
            } else {
                die("Abording: Too late value\n");
            }
        } else {
            die("Abording: no valid argument given\n");
        }
    } else { // 123s ain't running
        die("Abording: Empty SHM\n");
    }
} else {
    die("Usage: pool123s { power | energy }\n");
}
?>
