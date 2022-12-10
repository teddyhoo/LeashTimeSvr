<?
// maint-user-preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('z-');

// FORMAT: key|Label|type|enumeration|Hint or space|constraints
$id = $_REQUEST['id'];
$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '$id'");
if(!$user) $error = "Bad userid.";
else if(!$user['bizptr']) $error = "This is for business users only (not LT staff).";
if($error) {echo $error; exit;}
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$user['bizptr']}'");
if(!$biz) $error = "Bad biz: {$user['bizptr']}.";
if($error) {echo $error; exit;}
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);

$role = $user['rights'] ? $user['rights'][0] : '';
$ownerDispatcher = in_array($role, array('o', 'd'));
$person = $ownerDispatcher ? $user : ($role == 'p' ? 'tblprovider' : ($role == 'c' ? 'tblclient' : ''));
if(is_string($person)) $person = fetchFirstAssoc("SELECT * FROM $person WHERE userid = $id");
if(!$person) $error = "Bad person.";
if($error) {echo $error; exit;}

$pageTitle = "{$person['fname']} {$person['lname']} Preferences";
include "frame-bannerless.php";
echo "<h2>$pageTitle</h2>";
// ***************************************************************************
$Maint_editor_user_id = $id;
$bizPrefs = fetchPreferences();
$userPrefs = getUserPreferences($id);


$helpStrings = 
"managerNickname|This is the name in the From Line in outgoing messages.  Your actual name is used by default.
hideProScheduleAtTop|Hide the Pro Schedule Button at the top of the Client Sevices tab.
hideProScheduleAtBottom|Hide the Pro Schedule Button in the Short Term Schedules section of the Client Sevices tab.
hideOneDayScheduleAtTop|Hide the One Day Schedule Button at the top of the Client Sevices tab.
hideOneDayScheduleAtBottom|Hide the One Day Schedule Button in the Short Term Schedules section of the Client Sevices tab.
surchargeCollisionPolicy|Determine how the system responds when more than one automatic surcharge is applicable on the same day";

foreach(explode("\n", $helpStrings) as $pair) {
	$pair = explode('|', $pair);
	$help[$pair[0]] = $pair[1];
}
$generalPrefs = 	"homePageMode|Home Page Mode|picklist|Full Mode=>full,Brief Mode=>brief"
									."||managerNickname|From Name in your emails|string";
if($db == 'leashtimecustomers') $generalPrefs .= "||sortClientsByFirstName|Sort Client List By First Name (LT Customers only)|boolean";
$mobileSitterPrefs = 	"mobileVersionOverride|Mobile Version Override (Show ONLY Mobile Version to Sitter)|boolean"
									."||mobileVersionPreferred|On SmartPhone, Mobile Sitter Version is Preferred|boolean";



//bizLogo|Business Logo

$prefListSections = array();
if($ownerDispatcher) $prefListSections['General'] = $generalPrefs;
if($role == 'p') $prefListSections['Mobile Sitter Prefs'] = $mobileSitterPrefs;

foreach($prefListSections as $section => $str)
	foreach(explodePairsLine($str) as $k=>$v)
		$ignoreThese[$k] = $v;
			
foreach(getStandardUserPreferenceKeys() as $k)
	if(!$ignoreThese[$k]) $otherPrefs[] = "$k|$k|string";
	
$prefListSections['Other Prefs'] = join('||', $otherPrefs);


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
	document.location.href='<?= basename($_SERVER['SCRIPT_NAME']) ?>?id=<?= $id ?>&show='+openSections();
	document.getElementById('prop_'+property).scrollIntoView();
}

</script>
<?
include "refresh.inc";
// ***************************************************************************

?>

