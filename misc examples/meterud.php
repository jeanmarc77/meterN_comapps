#!/usr/bin/php
<?php
// A simple indicator script to track a main electric meter that "turns upside down".
// You need a import/export meter
// First, read and set the current value of your main meter. Then use -set and put the $set value.
// If your meter drift, you need to reajust those values.

$current = 14518500; // current main meter value in Wh
$set     = 14231370; // correction value
$id      = 'main'; // this indicator id

$IMPcmd = 'houseenergy -eimp'; // import command
$IMPID  = '1_IE'; // import id
$EXPcmd = 'houseenergy -eexp'; // export command
$EXPID  = '1_EE'; // export id

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

if (!isset($argv[1])) {
    $argv[1] = '';
} else {
$import = getvalue($IMPID, $IMPcmd);
$export = getvalue($EXPID, $EXPcmd);
}

if ($argv[1] == '-energy') { // latest import
    $val    = round((($set + $import - $export) / 1000), 0);
    $outstr = utf8_decode("$id($val*kWh)\n");
    echo $outstr;
} elseif ($argv[1] == '-set') {
    $val = $current - ($import - $export);
    echo "Change the set value to $val\n";
} else {
    echo "Usage: meterud { energy | set }\n
\t-energy :\tGet the main meter value
\t-set :\t\tSet the correction value
\n";
}
?>
