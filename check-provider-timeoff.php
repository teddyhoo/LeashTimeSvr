<? // check-provider-timeoff.php
// called via ajax from various editors to confirm provider availablility
// echos "available" or
// providerName1|providername2|providername3...
// where providerName1 is the name of the queried provider, who is off at this time
// and the other provider names are other providers also off at this time


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";

locked('o-');

if($_GET['prov'] && providerIsOff($_GET['prov'], $_GET['date'], $_GET['tod'])) {
	$activeProviders = getProviders("active = 1");
	foreach($activeProviders as $aprov) 
		if(providerIsOff($aprov['providerid'], $_GET['date'], $_GET['tod'])) {
			if($aprov['providerid'] == $_GET['prov']) $chosenProvider = providerShortName($aprov);
			else $offProviders[] = providerShortName($aprov);
		}
	echo '#unavailable#'.$chosenProvider;
	if($offProviders) echo "|".join("|", $offProviders);
}
else echo '#available#';