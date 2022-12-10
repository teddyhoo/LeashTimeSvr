<? //visit-sheet.php

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "BANG!";print_r($_SESSION["preferences"]);exit;}
if(!$_SESSION["preferences"]) {  // don't know how prefs are getting cleared...
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	require_once "preference-fns.php";
	$_SESSION["preferences"] = fetchPreferences();
}

tallyPage('visit-sheet.php');

if($provider && $baseFontSize = getProviderPreference($provider, 'visitSheetBaseFontSize')) 
	$magnificationCSS = "\nbody {font-size:$baseFontSize;}\n";  // affects petrx (Compact) only

$preferredFormat = $_SESSION['preferences']['preferredVisitSheetFormat'];
if($preferredFormat == 'petrx') include "visit-sheet-ptrx.php";
else if($preferredFormat == 'simple') include "visit-sheet-simple.php";
else include "visit-sheet-original.php";