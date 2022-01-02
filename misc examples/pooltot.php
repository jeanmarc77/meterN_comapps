#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
// If you own several production or consumption meters, this script will simulate a total production or consumption meter .
//
// How-to :
// 1) Make a link : ln -s /srv/http/comapps/pooltot.php /usr/bin/pooltot and chmod +x pooltot.php
// 2) In meterN, set your real meters as 'Elect' 'House production | House consumption' 
// 3) Then, set this virtual meter in meterN. The type should be 'Elect Other' with a passover value like 100000
//    Request the 'Main command' with 'pooltot energy' and 'Live command' with 'pooltot power'
// 4) Configure the script

// meterN config
$pathtomn  = '/srv/http/metern'; // without / at the end
// This virutal total meter config
$WHICHTYPE = 1; // Set to 1 for a virtual production meter and 2 for a consumption
$METERID   = 'total'; // this vitual meter ID
$METERNUM  = 1; // this vitual meterN meter number

// No edit should be needed bellow
$prevfile = '/dev/shm/prevpooltot.json';
$verbose  = true; //debug

function getvalue($id, $cmd) //  Get data and validate with IEC 62056 data set structure
{
    $datareturn = null;
    $giveup     = 0;
    $regexp     = "/^$id\(-?[0-9\.]+\*[A-z0-9³²%°]+\)$/i"; //ID(VALUE*UNIT)
    
    while (!isset($datareturn) && $giveup < 3) { // Try 3 times
        exec($cmd, $datareturn);
        $datareturn = trim(implode($datareturn));
        
        if (preg_match($regexp, $datareturn)) {
            $datareturn = preg_replace("/^$id\(/i", '', $datareturn, 1); // VALUE*UNIT)
            $datareturn = preg_replace("/\*[A-z0-9³²%°]+\)$/i", '', $datareturn, 1); // VALUE
            settype($datareturn, 'int');
        } else {
            $datareturn = null;
        }
        $giveup++;
    }
    return $datareturn;
}

function retrievecsv($meternum, $csvarray, $passo) // Retrieve last know value in latest csv
{
    $datareturn = null;
    $contalines = count($csvarray);
    $j          = 0;
    while (!isset($datareturn)) {
        $j++;
        $array      = preg_split('/,/', $csvarray[$contalines - $j]);
        $datareturn = (int) trim($array[$meternum]);
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

if (isset($argv[1]) && ($argv[1] == '-power' || $argv[1] == '-energy')) {
    define('checkaccess', TRUE);
    include("$pathtomn/config/config_main.php");
    include("$pathtomn/config/memory.php");
    for ($i = 1; $i <= $NUMMETER; $i++) {
        include("$pathtomn/config/config_met$i.php");
    }
    date_default_timezone_set($DTZ);
    
    if ($argv[1] == '-power') {
        $nowUTC  = strtotime(date("Ymd H:i:s"));
        ///// open mN shm live memory
        $data    = file_get_contents($LIVEMEMORY);
        $livemem = json_decode($data, true);
        if ($nowUTC - $livemem['UTC'] > 30) {
            die("Abording: Too late mN live values\n");
        }
    } else { // energy
        // Retrieve previous virtual meter value
        if (file_exists($prevfile)) {
            $data     = file_get_contents($prevfile);
            $previous = json_decode($data, true);
        }
        
        if (!isset($previous['KWHtot'])) { // At boot retrieve values in last csv
            $output = array();
            $output = glob($pathtomn . '/data/csv/*.csv');
            rsort($output);
            
            if (file_exists($output[0])) {
                $lines              = file($output[0]);
                $contalines         = count($lines);
                $lastarray          = preg_split("/,/", $lines[$contalines - 1]);
                $previous['KWHtot'] = retrievecsv($METERNUM, $lines, ${'PASSO' . $METERNUM});
                if ($verbose) {
                    $t = $previous['KWHtot'];
                    echo "Retrieve KWHtot value in last csv : $t kWh\n";
                }
            } else { // no csv, starting from scratch !
                $previous['KWHtot'] = 0;
                if ($verbose) {
                    echo "Starting from scratch !\n";
                }
            }
        } elseif ($verbose) {
            $t = $previous['KWHtot'];
            echo "Previous KWHtot value : $t kWh\n";
        }
    }
    
    $GPtot = 0;
    $diff  = 0;
    // Retreiving latest values
    for ($i = 1; $i <= $NUMMETER; $i++) {
        $value = null;
        if (${'TYPE' . $i} == 'Elect' && ${'PROD' . $i} == $WHICHTYPE && $i != $METERNUM && !${'SKIPMONITORING' . $i}) {
            if ($argv[1] == '-power') {
                if (isset(${'LIVECOMMAND' . $i}) && isset($livemem["${'METNAME'.$i}$i"])) {
                    $GPtot += $livemem["${'METNAME'.$i}$i"];
                }
                if ($verbose) {
                    $t = $livemem["${'METNAME'.$i}$i"];
                    echo "\nPower #$i ${'METNAME'.$i} : $t W\n";
                }
            } else {
                $value = getvalue(${'ID' . $i}, ${'COMMAND' . $i});
                if ($verbose) {
                    echo "\nGetting latest energy for #$i (${'METNAME'.$i}) : $value kWh\n";
                }
                if (isset($value)) {
                    if (isset($previous["prevTotalcounter$i"])) {
                        if ($verbose) {
                            $t = $previous["prevTotalcounter$i"];
                            echo "Previous value: $t kWh\n";
                        }
                        // Some passover checks
                        if ($value >= $previous["prevTotalcounter$i"]) {
                            $diff = $value - $previous["prevTotalcounter$i"];
                        } else {
                            if ($verbose) {
                                echo "passover: $value < ${'PASSO' . $PRODmetnum} \n";
                            }
                            $diff = $value + ${'PASSO' . $PRODmetnum} - $previous["prevTotalcounter$i"];
                        }
                    }
                    settype($previous["prevTotalcounter$i"], 'int');
                    $previous["prevTotalcounter$i"] = $value;
                    if ($verbose) {
                        echo "The difference is $diff, saving as prev value $value for #$i (${'METNAME'.$i})\n";
                    }
                    $previous['KWHtot'] += $diff;
                }
            }
        }
    }
    // Output
    if ($argv[1] == '-power') {
        if ($GPtot > 1000) {
            $GPtot = round($GPtot, 0);
        } else {
            $GPtot = round($GPtot, 1);
        }
        echo "$METERID($GPtot*W)\n";
    } else { // energy
        if ($verbose) {
            $t = $previous['KWHtot'];
            echo "\nSaving total #$METERNUM ($METERID) : $t kWh\n--\n";
        }
        if ($previous['KWHtot'] >= ${'PASSO' . $METERNUM}) { // virtual meter passed over
            $previous['KWHtot'] -= ${'PASSO' . $METERNUM};
            if ($verbose) {
                $t = $previous['KWHtot'];
                echo "Total passover $METERID :  $t > ${'PASSO' . $METERNUM}\n";
            }
        }
        // Save previous values
        $data = json_encode($previous);
        file_put_contents($prevfile, $data);
        
        $KWHtot = $previous['KWHtot'];
        echo "$METERID($KWHtot*Wh)\n";
    }
} else {
    echo "Usage: pooltot { power | energy }\n";
    if (file_exists($prevfile)) {
        $data     = file_get_contents($prevfile);
        $previous = json_decode($data, true);
        print_r($previous);
    }
}
?>
