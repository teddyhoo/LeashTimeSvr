<? // biz-capsule.php - called from LT Staff Menu in owner login
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
$bizid = $_SESSION["bizptr"];
$locked = locked('o-');
if(!($_SESSION["staffuser"])) {echo "For LT Staff Use Only."; exit;}


if(array_key_exists('disablerollover', $_REQUEST))
	setPreference('rolloverdisabled', $_REQUEST['disablerollover']);
$rolloverDisabled = fetchPreference('rolloverdisabled');

require_once "common/init_db_common.php";
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid");
$activated = $biz['activated']; // ? date('Y-m-d', $biz['activated']) : '--'; 
?>
<style>
.fauxlink {
	text-decoration: underline;
	color:blue;
	cursor:pointer;
}
</style>
<?

require_once "gui-fns.php";
$rolloverLink = $rolloverDisabled ? "disablerollover=0" : "disablerollover=1";
$rolloverLabel = $rolloverDisabled ? "Enable Rollover" : "Disable Rollover";
if($rolloverDisabled) echo "Recurring schedule rollover is disabled for this business.  ";
fauxLink($rolloverLabel, 
					"if(confirm(\"$rolloverLabel?\")) window.location.href=\"biz-capsule.php?$rolloverLink\";");
echo "<p>";
$dbLink = mattOnlyTEST() 
	? "<a href=\"http://leashtime.com/eegah/index.php?db={$biz['db']}&amp;lang=en-utf-8\" target=\"MYSQLDB\">{$biz['db']}</a>"
	: $biz['db'];
$bizDescription = "<b><u>Biz table:</u></b><p>DB: $dbLink<br>State: {$biz['state']}<br>Country: {$biz['country']}<br>Time Zone: {$biz['timeZone']}<br>Activated: $activated<p>";
$managers = fetchAssociations("SELECT * FROM tbluser WHERE bizptr = $bizid AND rights LIKE 'o-%' AND ltstaffuserid = 0");
foreach($managers as $man) {
	$class = $man['isowner'] ? 'boldfont' : '';
	$ownerLabel = $man['isowner'] ? 'Owner: ' : '';
	$mans[] = addslashes("<span class='$class'>$ownerLabel{$man['fname']} {$man['lname']} - {$man['email']}</span>");
	$bizDescription .= join('<br>', $mans);
}

$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass'], $force=1);
$ltclientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = {$biz['bizid']} LIMIT 1");
$_SESSION['preferences'] = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
if($ltclientid) $goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
//print_r('**'.$_SESSION['preferences']['bizName']."** [$ltclientid - $goldstar]");
if($ltclientid) {
	$ltclient = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $ltclientid LIMIT 1");
	foreach(explode(',', 'fname,lname,email,homephone,cellphone,workphone,city,state') as $f) $ltClientDescription[$f] = "<b>$f: </b>{$ltclient[$f]}";
	require_once "client-flag-fns.php";
//print_r(getBizFlagList());
	$ltClientDescription = '<b><u>LT Client:</u></b>'
	.clientFlagPanel($ltclientid, $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=true)
	.'<p>'.join('<br>', $ltClientDescription);
}
else $ltClientDescription = '<b><u>LT Client:</u></b><p>No LeashTime Client record associated with this business.';

reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=1);
$_SESSION['preferences'] = fetchPreferences();
$bizPrefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
$biz['phone'] = $bizPrefs['bizPhone'];
$biz['bizEmail'] = $bizPrefs['bizEmail'];


foreach(explode(',', 'bizName,shortBizName,bizPhone,bizEmail,bizAddress,bizHomePage,timeZone,emailHost') as $f) $bizPrefDescription[$f] = "<b>$f: </b>{$bizPrefs[$f]}";
$auth = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property = 'ccGateway' OR property = 'x_login' OR property = 'x_tran_key'");
$ccKeys = explode(',', 'ccGateway,x_login,x_tran_key');
foreach($ccKeys as $k) {
	if($k == 'ccGateway') $bizPrefDescription[] = "<b>$k</b>: ".($auth['ccGateway'] ?  : 'Not set.');
	else if($auth[$k]) $bizPrefDescription[] = "<b>$k</b>: is set.";
	else $bizPrefDescription[] = "<b>$k</b>: is NOT set.";
}
		

$bizPrefDescription = '<b><u>Prefs:</u></b><p>'.join('<br>', $bizPrefDescription);


$totalDescription = "<h2>{$biz['bizname']} (Biz ID: $bizid)</h2>$bizDescription<hr>$bizPrefDescription<hr>$ltClientDescription";
echo $totalDescription;