#!/usr/bin/php
<?php
// A virtual meters example for meterN. This script will simulate a house and self consumption meters (*). You must own a total import/export and a production meter. 
//                         _____  
//                        /     \
//      +----------+     /       \     +-Total meter-+     - ^ -
//      |Production| --> | House | <-- |   import    |___   /X\ Grid
//      +----------+     |       | --> |   export    |     /V V\
//                                     +-------------+
//                consumption/selfconsumuption (*)
//
// ln -s /srv/http/comapps/houseenergy.php /usr/bin/houseenergy and chmod +x houseenergy.php
//
// The house virtual meter should be configured in mN as 'Elect House consumption' type with a passover value like 100000.
// The house self consumuption meter as 'Elect Other' also with a passover value like 100000.

if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Direct access not permitted');
}
if (!file_exists('/dev/shm/sdm_log.txt')) {
    usleep(500);
    die('sdm_log.txt does not exist yet');
}
//// Set the 2 virtual meters ////
// Consumption
$HOUSEID     = 'elect'; // ID
$HOUSEmetnum = 1; // meter number
// Selfconsumption
$SELFID      = 'self';
$SELFCmetnum = 7;

//// Set up real meters ////
// Production
$PRODID       = 'solar'; // ID
$PRODcmd      = 'pool123s -energy'; // Energy command
$POWERPRODcmd = 'pool123s -power'; // Power
$PRODmetnum   = 4; // meter number

// TOT return the total power (eg if import = 45W, export -55W)
$TPID        = '1_P';
$TOTPOWERcmd = 'cat /dev/shm/sdm_log.txt | egrep "^1_P\(" | grep "*W)"';

// Energy Imported
$IMPID     = '1_IE';
$IMPcmd    = 'cat /dev/shm/sdm_log.txt | egrep "^1_IE\(" | grep "*Wh)"';
$IMPmetnum = 5;

// Energy Exported
$EXPID     = '1_EE';
$EXPcmd    = 'cat /dev/shm/sdm_log.txt | egrep "^1_EE\(" | grep "*Wh)"';
$EXPmetnum = 6;

//// Indicators ////
$VOLTID  = '1_V';
$VOLTcmd = 'cat /dev/shm/sdm_log.txt | egrep "^1_V\(" | grep "*V)"';
$FRQID   = '1_F';
$FRQcmd  = 'cat /dev/shm/sdm_log.txt | egrep "^1_F\(" | grep "*Hz)"';
$COSID   = '1_PF';
$COScmd  = 'cat /dev/shm/sdm_log.txt | egrep "^1_PF\(" | grep "*F)"';

// Path to metern
$MNDIR    = '/srv/http/metern';
// Save previous values in a file, make sure the http user can write there
$prevfile = '/srv/http/comapps/daemon/prevhouseenergy.json';

// No edit should be needed bellow

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
            settype($datareturn, 'float');
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

if (isset($argv[2])) {
    die("Abording: Too many arguments\n");
}
if (isset($argv[1])) {
    if ($argv[1] == '-power' || $argv[1] == '-powerimp' || $argv[1] == '-powerexp' || $argv[1] == '-powerself') { // Power
        $totpower  = getvalue($TPID, $TOTPOWERcmd);
        $prodpower = getvalue($PRODID, $POWERPRODcmd);
        $power     = round(($prodpower + $totpower), 1);
        
        if ($argv[1] == '-power') {
            $outstr = utf8_decode("$HOUSEID($power*W)\n");
        } else {
            if ($totpower > 0) { // Import
                $imppower = $power - $prodpower;
                $exppower = 0;
                $slfpower = $prodpower;
            } else { // Export
                $imppower = 0;
                $exppower = $prodpower - $power;
                $slfpower = $power;
            }
        }
        if ($argv[1] == '-powerimp') {
            $imppower = round($imppower, 1);
            $outstr   = utf8_decode("$IMPID($imppower*W)\n");
        } elseif ($argv[1] == '-powerexp') {
            $exppower = round($exppower, 1);
            $outstr   = utf8_decode("$EXPID($exppower*W)\n");
        } elseif ($argv[1] == '-powerself') {
            $slfpower = round($slfpower, 1);
            $outstr   = utf8_decode("$SELFID($slfpower*W)\n");
        }
        echo "$outstr";
    } elseif ($argv[1] == '-volt' || $argv[1] == '-frq' || $argv[1] == '-cos') { // Indicators
        if ($argv[1] == '-volt') {
            $outstr = getvalue($VOLTID, $VOLTcmd);
            $outstr = round($outstr, 1);
            $outstr = utf8_decode("$VOLTID($outstr*V)\n");
        } elseif ($argv[1] == '-frq') {
            $outstr = getvalue($FRQID, $FRQcmd);
            $outstr = round($outstr, 1);
            $outstr = utf8_decode("$FRQID($outstr*Hz)\n");
        } else {
            $outstr = getvalue($COSID, $COScmd);
            $outstr = round($outstr, 1);
            $outstr = utf8_decode("$COSID($outstr*phi)\n");
        }
        echo "$outstr";
    } elseif ($argv[1] == '-eimp' || $argv[1] == '-eexp') { // Imported / Exported meters
        if ($argv[1] == '-eimp') {
            $outstr = getvalue($IMPID, $IMPcmd);
            $outstr = utf8_decode("$IMPID($outstr*Wh)\n");
        } else {
            $outstr = getvalue($EXPID, $EXPcmd);
            $outstr = utf8_decode("$EXPID($outstr*Wh)\n");
        }
        echo "$outstr";
    } elseif ($argv[1] == '-energy' || $argv[1] == '-self') { // Virtual energy and selfc meters
        define('checkaccess', TRUE);
        include("$MNDIR/config/config_main.php");
        include("$MNDIR/config/config_met$HOUSEmetnum.php");
        include("$MNDIR/config/config_met$SELFCmetnum.php");
        include("$MNDIR/config/config_met$IMPmetnum.php");
        include("$MNDIR/config/config_met$EXPmetnum.php");
        include("$MNDIR/config/config_met$PRODmetnum.php");
        
        // Retrieve previous virtuals meters values
        if (file_exists($prevfile)) {
            $data     = file_get_contents($prevfile);
            $previous = json_decode($data, true);
        } else {
            // Retrieve last know value in last csv
            $dir    = $MNDIR . '/data/csv/';
            $output = array();
            $output = glob($dir . '*.csv');
            rsort($output);
            
            if (file_exists($output[0])) {
                $lines                     = file($output[0]);
                $contalines                = count($lines);
                $previous['prevIMPhouse']  = retrievecsv($IMPmetnum, $lines, ${'PASSO' . $IMPmetnum});
                $previous['prevEXPhouse']  = retrievecsv($EXPmetnum, $lines, ${'PASSO' . $EXPmetnum});
                $previous['prevEXPself']   = retrievecsv($prevEXPself, $lines, ${'PASSO' . $prevEXPself});
                $previous['prevHOUSE']     = retrievecsv($HOUSEmetnum, $lines, ${'PASSO' . $HOUSEmetnum});
                $previous['prevSELF']      = retrievecsv($SELFCmetnum, $lines, ${'PASSO' . $SELFCmetnum});
                $previous['prevPRODhouse'] = retrievecsv($PRODmetnum, $lines, ${'PASSO' . $PRODmetnum});
                $previous['prevPRODself']  = $previous['prevPRODhouse'];
            } else { // restarting from scratch !
                $import     = null;
                $export     = null;
                $production = null;
                // latest import
                $import     = getvalue($IMPID, $IMPcmd);
                // latest export
                $export     = getvalue($EXPID, $EXPcmd);
                // latest production
                $production = getvalue($PRODID, $PRODcmd);
                
                $previous['prevIMPhouse']  = $import;
                $previous['prevEXPhouse']  = $export;
                $previous['prevEXPself']   = $export;
                $previous['prevHOUSE']     = 0;
                $previous['prevSELF']      = 0;
                $previous['prevPRODhouse'] = $production;
                $previous['prevPRODself']  = $production;
            }
        }
        
        // Now retrieve latest values
        $import     = null;
        $export     = null;
        $production = null;
        $outstr     = null;
        
        if ($argv[1] == '-energy') { // latest import
            $import = getvalue($IMPID, $IMPcmd);
        }
        $export     = getvalue($EXPID, $EXPcmd); // latest export
        $production = getvalue($PRODID, $PRODcmd); // latest production
        
        // Household consumption
        if ($argv[1] == '-energy' && isset($import) && isset($export)) {
            if ($export >= $previous['prevEXPhouse']) { // Some passover checks
                $diffEXP = $export - $previous['prevEXPhouse'];
            } else {
                $diffEXP = $export + ${'PASSO' . $EXPmetnum} - $previous['prevEXPhouse'];
            }
            if (isset($production)) {
                if ($production >= $previous['prevPRODhouse']) {
                    $diffPROD = $production - $previous['prevPRODhouse'];
                } else {
                    $diffPROD = $production + ${'PASSO' . $PRODmetnum} - $previous['prevPRODhouse'];
                }
                settype($previous['prevPRODhouse'], 'int');
                $previous['prevPRODhouse'] = $production;
            } else { // no production case
                $diffPROD = 0;
                $diffEXP  = 0;
            }
            if ($import >= $previous['prevIMPhouse']) {
                $diffIMP = $import - $previous['prevIMPhouse'];
            } else {
                $diffIMP = $import + ${'PASSO' . $IMPmetnum} - $previous['prevIMPhouse'];
            }
            $difference = $diffIMP + $diffPROD - $diffEXP;
            if ($difference < 0) { // Might happen if the production return no difference while the export did !
                $difference = 0;
            }
            $previous['prevHOUSE'] += $difference;
            
            if ($previous['prevHOUSE'] >= ${'PASSO' . $HOUSEmetnum}) { // passed over
                $previous['prevHOUSE'] -= ${'PASSO' . $HOUSEmetnum};
            }
            $val    = $previous['prevHOUSE'];
            $outstr = utf8_decode("$HOUSEID($val*Wh)\n");
            
            settype($previous['prevIMPhouse'], 'int');
            $previous['prevIMPhouse'] = $import;
            settype($previous['prevEXPhouse'], 'int');
            $previous['prevEXPhouse'] = $export;
            settype($previous['prevHOUSE'], 'int');
        } elseif ($argv[1] == '-self' && isset($export)) { // Self 
            // Some passover checks
            if ($export >= $previous['prevEXPself']) {
                $diffEXP = $export - $previous['prevEXPself'];
            } else {
                $diffEXP = $export + ${'PASSO' . $EXPmetnum} - $previous['prevEXPself'];
            }
            if (isset($production)) {
                if ($production >= $previous['prevPRODself']) {
                    $diffPROD = $production - $previous['prevPRODself'];
                } else {
                    $diffPROD = $production + ${'PASSO' . $PRODmetnum} - $previous['prevPRODself'];
                }
                settype($previous['prevPRODself'], 'int');
                $previous['prevPRODself'] = $production;
            } else { // no production case
                $diffPROD = 0;
                $diffEXP  = 0;
            }
            
            $difference = $diffPROD - $diffEXP;
            if ($difference < 0) {
                $difference = 0;
            }
            
            $previous['prevSELF'] += $difference;
            if ($previous['prevSELF'] >= ${'PASSO' . $SELFCmetnum}) {
                $previous['prevSELF'] -= ${'PASSO' . $SELFCmetnum};
            }
            $val    = $previous['prevSELF'];
            $outstr = utf8_decode("$SELFID($val*Wh)\n");
            
            settype($previous['prevEXPself'], 'int');
            $previous['prevEXPself'] = $export;
            settype($previous['prevSELF'], 'int');
        }
        
        // Saving previous values
        $data = json_encode($previous);
        file_put_contents($prevfile, $data);
        
        echo "$outstr";
    } elseif ($argv[1] == '-prev') {
        if (file_exists($prevfile)) {
            echo "\n$prevfile :\n\n";
            $data     = file_get_contents($prevfile);
            $previous = json_decode($data, true);
            print_r($previous);
        }
    } else {
        die("Abording: no valid argument given or missing value(s).\n");
    }
} else {
    echo "Usage: houseenergy { power | powerimp | powerexp | powerself | volt | freq | cos | eimp | eexp | energy | self }\n
	-power :\t Total power
	-powerimp :\t Power imported
	-powerexp :\t Power exported
	-powerself :\t Power self consumed
	-volt :\t\t Grid voltage
	-frq :\t\t Grid frequency
	-cos :\t\t Power factor
	-eimp :\t\t Energy imported
	-eexp :\t\t Energy exported
	-energy :\t Household virtual consumption meter
	-self :\t\t Household virtual  self consumption meter
	-prev :\t\t Show previous stored values
	\n";
}
?>
