<?
// user-preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Your Own Preferences";
$breadcrumbs = "<a href='preference-list.php' title='Business Preferences'>Business Preferences</a> "
								." - <a href='comm-prefs.php' title='Communication Preferences for individual Clients, Sitters, and Staff'>Communication Preferences</a> ";
include "frame.html";
// ***************************************************************************
// FORMAT: key|Label|type|enumeration|Hint or space|constraints

$_SESSION["preferences"] = fetchPreferences();


$helpStrings = 
"showZeroVisitCountSitters|Show all sitters, even those without visits or time off, on the Home page";

foreach(explode("\n", $helpStrings) as $pair) {
	$pair = explode('|', $pair);
	$help[$pair[0]] = $pair[1];
}
$generalPrefs = 	"homePageMode|Home Page Mode|picklist|Full Mode=>full,Brief Mode=>brief"
									."||managerNickname|From Name in your emails|string";


//if(staffOnlyTEST() || mattOnlyTEST() || dbTEST('dogslife,friendlyvisits')) $generalPrefs .= "||frameLayout|Use the full window to display LeashTime|custom_boolean|fullScreen-pref-edit.php";
if($_SESSION['preferences']['optionEnabledFrameLayout'] || staffOnlyTEST() || mattOnlyTEST()) $generalPrefs .= "||frameLayout|Use the full window to display LeashTime|custom_boolean|fullScreen-pref-edit.php";
if(staffOnlyTEST()) $generalPrefs .= "||composerSignature|[STAFF] Email composer signature|custom|signature-edit.php";
//if(staffOnlyTEST() || dbTEST('dogonfitness')) $generalPrefs .= "||managerNickname|[STAFF+] Name to use for the #MANAGER# token rather than the manager's name|string";
if(mattOnlyTEST()) $generalPrefs .= "||chooseMgrVisitListColumns|[MATT] Choose Visit List Columns|customlightbox|prov-schedule-list-options-lightbox.php?|760,520";
$ratesSuppressed = getUserPreference($_SESSION['auth_user_id'], 'suppressRevenueDisplay', $decrypted=false, $skipDefault=false);
if(userRole() == 'o' || !$ratesSuppressed) $generalPrefs .= "||showrateinclientschedulelist|Show rates in client visit lists|boolean";

if($_SESSION['preferences']['enableSMS']) $generalPrefs .= "||managerTextPhone|Cellphone to which texts may be sent|string";



if($db == 'leashtimecustomers') $generalPrefs .= "||sortClientsByFirstName|Sort Client List By First Name (LT Customers only)|boolean";

$generalPrefs .= "||showZeroVisitCountSitters|Show all sitters on Home page|boolean";

//bizLogo|Business Logo

$prefListSections = 
						array(
									'General'=>$generalPrefs);
									
$allOrNone = array('all'=>'all', 'none'=>array());									
if(!$_REQUEST['show']) $_REQUEST['show'] = 'all';
$showSections = 
	isset($allOrNone[$_REQUEST['show']]) 
	? $allOrNone[$_REQUEST['show']]
	: ($_REQUEST['show'] ? explode(',', $_REQUEST['show']) : array());

preferencesTable($prefListSections, $help, $showSections, $userprefs=true);

if($_REQUEST['dump']) {
	foreach($prefListSections as $label => $section) {
		echo "<p># $label<br>";
		$section = explode('||', $section);
		foreach($section as $value) {
			$value = explode('|', $value);
			$k = $value[0];
			echo "$k = \"{$_SESSION['preferences'][$k]}\"<br>";
		}
	}
}
		
//preferencesEditorLauncher($prefsDescription);
?>
<script language='javascript'>
<? 
dumpPrefsJS();
dumpShrinkToggleJS();
?>

function openSections() {
	var open = new Array();
	var numClosed = 0;
	var el;
	for(var i=1; el = document.getElementById('section'+i); i++) {
		if(el.style.display == 'none') numClosed++;
		else open.push(i);
	}
	if(numClosed == 0) return 'all';
	else return open.join(',');
}

function updateProperty(property, value) {
	//window.refresh();
	document.location.href='<?= basename($_SERVER['SCRIPT_NAME']) ?>?show='+openSections();
	document.getElementById('prop_'+property).scrollIntoView();
}

</script>
<?
include "refresh.inc";
// ***************************************************************************

include "frame-end.html";
?>

