<?
//maint-edit-biz.php

/* Rules:
1. Only an Super-user may use this page.
X. An owner may only edit logins in the same petbiz

Inputs: (R = required, * = optional, @ = one required among all @'s
[R] id - bizid
*/

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "system-login-fns.php";


$SUPPORT_FNS_SUPPORTED = false;

// Verify login information here
locked('z-');
extract(extractVars('id,sort,supportactive,activebiz,freeun1til,rates,staffonly,droplogo,droplogotoken,test,bizName,magrights', $_REQUEST));

if($_GET['showEULA']) {
	echo fetchRow0Col0("SELECT terms FROM tbleula WHERE eulaid = {$_GET['showEULA']} LIMIT 1");
	exit;
}


if($magrights) {
	require_once "rights-maint-fns.php";
	$parts = explode('-', $magrights);
	$gRights = getGlobalRights($dispatcherOnly=($parts[0] == 'd'));
	$magrights = explode(',', $parts[1]);
//print_r($magrights);
	echo "GRANTED permissions:<br>";
	foreach($gRights as $key => $ar) if(in_array($key, $magrights)) echo "({$key}) {$ar['label']} - {$ar['description']}<br>";
	echo "<p>WITHHELD permissions:<br>";
	foreach($gRights as $key => $ar) if(!in_array($key, $magrights)) echo "({$key}) {$ar['label']} - {$ar['description']}<br>";
	exit;
}
if($droplogo) {
	if($droplogotoken != $_SESSION['DROPLOGOTOKEN']) $error = 'Expired token';
	else {
		foreach(explode(',', 'gif,jpg,png') as $ext)
			if(file_exists("/var/www/prod/bizfiles/biz_$droplogo/logo.$ext")) 
				unlink("/var/www/prod/bizfiles/biz_$droplogo/logo.$ext");
		$message = "Logo dropped for biz $droplogo";
	}
	$id = $droplogo;
}
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
if(!$biz) {
	echo "No biz for id: [$id]";
	exit;
}
if($_FILES['logofile']['tmp_name']) {
	$dir = "/var/www/prod/bizfiles/biz_$id";
	if(!is_dir($dir)) mkdir($dir);
//echo "BANG: $dir";	
	$originalName = $_FILES['logofile']['name'];
	$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
	if(file_exists("$dir/logo.$extension")) unlink("$dir/logo.$extension");
	$random = rand(1000,100000);
	foreach(glob("$dir/logo.*") as $f) {
		$oldExt = substr($f, strpos($f, '.')+1);
		rename($f, "$dir/logo$random.$oldExt");
	}
	if(!move_uploaded_file($_FILES['logofile']['tmp_name'], strtolower("$dir/logo.$extension"))) {
		$uploaderror = "There was an error uploading the file, please try again! [{$_FILES['logofile']}]";
	}
//echo "BANG: [$dir] [$dir/logo.$extension]";	
}
$_SESSION['DROPLOGOTOKEN'] = time();

$dbExists = in_array($biz['db'], fetchCol0("SHOW DATABASES"));
if($_POST['saveBiz']) {
	if($SUPPORT_FNS_SUPPORTED) {
		require "support/support_onezero_fns.php";
		updateCustomerSupportInfo($id, $supportactive);
		include "common/init_db_common.php";
		$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
		$message = "Support for {$biz['bizname']} as been ".($supportactive ? 'activated.' : 'deactivated.').'<br>';
	}
	if($_POST['unsign']) updateTable('tblpetbiz', array('eulaversion'=>0,'eulasigned'=>null,'eulasigner'=>0), "bizid ='$id'", 1);
	if($dbExists) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		setPreference('mod_securekey', $_POST['mod_securekey'] ? 1 : 0);
		setPreference('mobileSitterAppEnabled', $_POST['mobileSitterAppEnabled'] ? 1 : 0);
	}
	include "common/init_db_common.php";
	$freeuntil = in_array($freeuntil, array('forever','always')) 
								? '1970-01-01' 
								: ($freeuntil ? date('Y-m-d', strtotime($freeuntil)) : array('NULL'));
	updateTable('tblpetbiz', 
							array('freeuntil'=>$freeuntil,
								'rates'=>$rates, 
								'test'=>($test ? 1 : 0),
								'bizName'=>($bizName ? $bizName : $biz['bizname'])), 
							"bizid = '$id'", 1);
	$activebiz = $activebiz ? '1' : '0';
	if($biz['activebiz'] != $activebiz) {
		updateTable('tblpetbiz', array('activebiz'=>$activebiz), "bizid = '$id'", 1);
	//echo "BIZ: $id active: [$activebiz]".print_r($_POST, 1)."<br>";
		$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
		if($biz['orgptr']) {
			$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$biz['orgptr']} LIMIT 1");
			reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
			updateTable('tblbranch', array('activebranch'=>$activebiz), "bizptr = '$id'", 1);
			include "common/init_db_common.php";
		}
	}
}
if($_GET['setupclientui']) {
	$to = "/var/www/prod/bizfiles/biz_$id/clientui";
	ensureDirectory($to);
	copyDir("/var/www/prod/bizfiles/starterclientui", $to);
}


$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);

// ASSUMPTION: DB is leashtimecustomers, $biz array is set
// SETS globals: $ltclientid, $goldstar,$ltclient, $ltClientDescription, $bizPrefs, $activated, $bizDescription, $managers, $totalDescription
// maint-biz-description-INCLUDE.php CALLED in maint-edit-biz.php, leashtime-customer-details.php
require_once "maint-biz-description-INCLUDE.php";
/*
$ltclientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = {$biz['bizid']} LIMIT 1");
if($ltclientid) $goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
if($ltclientid) {
	require_once "client-flag-fns.php";
	$ltclient = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $ltclientid LIMIT 1");
	foreach(explode(',', 'fname,lname,email,homephone,cellphone,workphone,city,state') as $f) 
		$ltClientDescription[$f] = "<b>$f: </b>{$ltclient[$f]}";
	$ltClientDescription = '<b><u>LT Client:</u></b>'
													.clientFlagPanel($ltclientid, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=true)
													.'<p>'.join('<br>', $ltClientDescription);
}
else $ltClientDescription = '<b><u>LT Client:</u></b><p>No LeashTime Client record associated with this business.';

reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
$bizPrefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
$biz['phone'] = $bizPrefs['bizPhone'];
$biz['bizEmail'] = $bizPrefs['bizEmail'];

foreach(explode(',', 'bizName,shortBizName,bizPhone,bizEmail,bizAddress,bizHomePage,timeZone') as $f) $bizPrefDescription[$f] = "<b>$f: </b>".addslashes($bizPrefs[$f]);
$bizPrefDescription = '<b><u>Prefs:</u></b><p>'.join('<br>', $bizPrefDescription);

include "common/init_db_common.php";
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
$activated = $biz['activated']; // ? date('Y-m-d', $biz['activated']) : '--'; 
$bizDescription = "<b><u>Biz table:</u></b><p>DB: {$biz['db']}<br>State: {$biz['state']}<br>Country: {$biz['country']}<br>Time Zone: {$biz['timeZone']}<br>Activated: $activated<p>";
$managers = fetchAssociations("SELECT * FROM tbluser WHERE bizptr = $id AND rights LIKE 'o-%' AND ltstaffuserid = 0");
foreach($managers as $man) {
	$class = $man['isowner'] ? 'boldfont' : '';
	$ownerLabel = $man['isowner'] ? 'Owner: ' : '';
	$mans[] = addslashes("<span class='$class'>$ownerLabel{$man['fname']} {$man['lname']} - {$man['email']}</span>");
	$bizDescription .= join('<br>', $mans);
}
$totalDescription = "<h2>{$biz['bizname']}</h2>$bizDescription<hr>$bizPrefDescription<hr>$ltClientDescription";
*/

$bizptr = $id;
$sort = $sort ? $sort : 'rights ASC, lname ASC, fname ASC';
$orderBy = ($sort && strpos($sort, 'name') !== 0)? "ORDER BY ".str_replace('_', ' ', $sort) : '';


$users = fetchAssociations("SELECT *, left(rights,1) as role FROM tbluser WHERE bizptr = '$id' $orderBy");
$roles = explodePairsLine('o|Owner||c|Client||p|Sitter||z|Leashime Maintainer||d|Dispatcher');
$allRights = fetchAssociationsKeyedBy("SELECT * FROM tblrights", 'key');

if($dbExists) {
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$clients = fetchKeyValuePairs("SELECT userid, clientid FROM tblclient WHERE userid IS NOT NULL AND userid > 0");
	$provs = fetchKeyValuePairs("SELECT userid, providerid FROM tblprovider WHERE userid IS NOT NULL AND userid > 0");
	$prefs = fetchPreferences();
	foreach($provs as $k => $v) $knownUserIds[$k] = $v;
	foreach($clients as $k => $v) $knownUserIds[$k] = $v;
}
$columns = explodePairsLine('userid|User ID||loginid|Login ID||pwdmsg| ||name|Name||role|Role||email|Email||active|Status||rights|Rights||agreement|PSA||valid|Login Valid');
$columnSorts = array('userid'=>null, 'bizname'=>null, 'active'=>null, 'loginid'=>null, 'role'=>null, 'email'=>null, 'name'=>null);

$windowTitle = "Business {$biz['bizname']} ({$db})";
$bizdb = $biz['db'];
include 'frame-maintenance.php';

$bizUsers = $dbExists ? getBizUsers($biz) : array();
foreach($users as $i => $user) {
	$rights = $users[$i]['rights'];
	if(!isset($user['fname'])) {
		if(strpos($rights, 'o-') === FALSE && strpos($rights, 'd-') === FALSE) {
			$users[$i]['fname'] = $bizUsers[$user['userid']]['fname'];
			$users[$i]['lname'] = $bizUsers[$user['userid']]['lname'];
			$users[$i]['email'] = $bizUsers[$user['userid']]['email'];
		}
		$users[$i]['name'] = $bizUsers[$user['userid']]['name'];
	}
	else {
		$users[$i]['name'] = "{$user['fname']} {$user['lname']}";
		if(strpos($rights, 'o-') === 0) {
			if($users[$i]['isowner']) $firstOwner = $users[$i];
		}
	}
}
//if(mattOnlyTEST()) print_r($users);

if(strpos($sort, 'name') === 0) {
	usort($users, 'namesortX');
	if(strpos(strtoupper($sort), 'DESC'))
		$users = array_reverse($users);
}

if(strpos($sort, 'email') === 0) {
	usort($users, 'emailsort');
	if(strpos(strtoupper($sort), 'DESC'))
		$users = array_reverse($users);
}

function emailsort($a, $b) { return strcmp($a['email'], $b['email']);}

function namesortX($a, $b) {
	if($x = strcmp($a['lname'], $b['lname'])) return $x;
	if($x = strcmp($a['fname'], $b['fname'])) return $x;
	if($x = strcmp($a['loginid'], $b['loginid'])) return $x;
	return 0;
}

function num($role, $status) { 
	global $allUsers;
	$s = $allUsers[$role][$status];
	return $s ? $s : '0'; 
}

foreach($users as $user) {
	$row = $user;
	$row['userid'] = prefsLink($user['userid']);
	$row['loginid'] = userLink($user['loginid'], $user['userid']);
	//$row['name'] = $user['name'] ? $user['name'] : 
	$row['bizname'] = bizLink($row['bizname'], $row['bizid']);
	$row['active'] = $row['active'] ? 'active' : 'INACTIVE';
	$row['role'] = $roles[$row['role']];
	if($user['isowner']) $row['role'] = "<b>{$row['role']}</b>";
	$row['agreement'] = $user['agreement'] ? 'Yes' : '-';
	$magLink = "<span onclick='magRights(\"{$row['rights']}\")'>&#128269;</span>";
	$row['rights'] = rightsLink($user).' '.$magLink;
	//$row['valid'] = $knownUserIds[$user['userid']] || !in_array($row['role'], array('Client','Provider')) ? 'Yes' : '<font color=red>No</font>';
	$row['valid'] = 
		$row['role'] == 'Client' && $clients[$user['userid']] ? 'Yes' : (
		$row['role'] == 'Provider' && $provs[$user['userid']] ? 'Yes' : (
		!in_array($row['role'], array('Client','Provider')) ? 'Yes' : '<font color=red>No</font>'));

	if($user['temppassword']) {
		$un = addslashes($user['loginid']);
		$pw = addslashes($user['temppassword']);
		$em = addslashes($user['email']);
		$row['pwdmsg'] = fauxLink('[P]', "loginInstructions(\"$un\", \"$pw\", \"$em\")", 1, "Login instructions");
	}

	
	$even = strpos($rowClass, 'EVEN') == FALSE ? 'EVEN' : '';
	$rowClass =	($row['active'] == 'INACTIVE' ? 'canceledtask' : 'futuretask').$even;
	if($row['role'] == 'Client') {
		if($staffonly) continue;
		$row['agreement'] =$row['agreementptr'] ? "v.{$row['agreementptr']} {$row['agreementdate']}" : "No";
		$rowClass .= ' CLIENT';
	}
	$rowClasses[] =	$rowClass;
	$role = $user['ltstaffuserid'] ? 'Staff' : $row['role'];
	
	$allUsers[$role][$row['active']]++;
	$rows[] = $row;
}


$noCustomClientUI = !file_exists("bizfiles/biz_$id/clientui/style.css") ? '(No custom client UI)' : '';
if(!$noCustomClientUI)
	if(!file_exists("bizfiles/biz_$id/stage/style.css") ||
		(file_get_contents("bizfiles/biz_$id/clientui/style.css") != file_get_contents("bizfiles/biz_$id/stage/style.css")))
	$noCustomClientUI .= "<span style='font-style:small-caps;color:red'title='Installed style is not the same as the stage style.'>Nonstandard</span> ";
$noCustomClientUI .= " ".echoButton('', 'Edit Client UI', "document.location.href=\"maint-setup-client-ui.php?bizid=$id\"", null, null, 1);
	//$noCustomClientUI .= " ".echoButton('', 'Set Up Client UI', "document.location.href=\"maint-edit-biz.php?id=$id&setupclientui=1\"", null, null, 1);
	
	
$activated = "Created {$biz['activated']}";
$flag  = $biz['country'] == 'US' ? '' : " <img src='art/world-flag-{$biz['country']}.gif' height=20> ";
$goldstar = $goldstar ? '<img src="art/flag-yellow-star.jpg"> ' : '';
$detailsLink = fauxLink('[details]', "descriptionBox()", 1, "Client details");
echo "<div style='position:relative;left:35px;'>$goldstar<span class='titleheader'>{$biz['bizname']} $detailsLink ".$flag.loginLink($biz['bizid'])."</span> $activated <span style='background:lightgrey'><a href='client-ui-test.php?bizid=$id'>Preview Client UI</a> <a target=mockup href='client-ui-mockup.php?bizid=$id'>[mockup]</a> $noCustomClientUI</span></div><p>";
echo "Owner: {$firstOwner['name']} ({$firstOwner['email']}) {$biz['phone']} Biz Email: {$prefs['bizEmail']}<p>";
if($message) echo "<p class='tiplooks'>$message</p>";
?>
<style>
.titleheader {font-weight: bold; font-size: 18px;}
</style>

<table><tr><td valign=top>


<table border=1 bordercolor=black>
<tr><td colspan=2><b>Ready to Eat</b>
<? $custloginurl = "https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=$id"; ?>
<tr><td colspan=2>Custom login: <a href=<?= $custloginurl ?>><?= $custloginurl ?></a>
<? $url = "https://{$_SERVER["HTTP_HOST"]}/login-page.php?qrcode=1&bizid=$id"; ?>
<tr><td colspan=2>Custom login with Mobile Tag: <a href=<?= $url ?>><?= $url ?></a>
<? $url = "https://{$_SERVER["HTTP_HOST"]}/prospect-request-form-custom.php?bizid=$id"; ?>
<tr><td colspan=2 style='text-decoration:line-through'>Prospective Client Page: <a href=<?= $url ?>><?= $url ?></a>
<? $prospecturl = "https://{$_SERVER["HTTP_HOST"]}/prospect-request-form-custom.php?templateid=fulladdress&bizid=$id"; ?>
<tr><td colspan=2>Prospective Client, separate address fields: <a href=<?= $prospecturl ?>><?= $prospecturl ?></a>
<tr><td onclick='$("#blurb").toggle()'><span style='font-size:1.5em;font-weight:bold'><u>Blurb</u></span><td><span id='blurb' style='display:none'>
Your Business ID is <?= $id ?><p>Custom Login Page:<br> <?= $custloginurl ?><p>Prospective Client Page:<br> <?= $prospecturl ?>
<p>Embeddable Login Page:<br>https://leashtime.com/login-embeddable.php?preview=1&bizid=<?= $id ?>
<p>Embeddable Prospective Client Page:<br>https://leashtime.com/prospect-embeddable.php?preview=1&bizid=<?= $id ?>
<p>Prospctive Client Form FAQ: <br>How can I add a "Contact Us" form for new clients to my website?<br>https://leashtime.com/phpmyfaq-2.7.5/index.php?action=artikel&cat=4&id=5
</span></td></tr>


<tr><td colspan=2><b>Embeddable</b>
<tr><td>Login&nbsp;Form&nbsp;HTML 
<td>
<a href="login-embeddable.php?bizid=<?= $id ?>" target=html>[view html]</a>
<a href="login-embeddable.php?save=1&bizid=<?= $id ?>" target=html>[save]</a>
<a href="login-embeddable.php?preview=1&bizid=<?= $id ?>" target=html>[preview]</a>
<tr><td width='10'>Prospective&nbsp;Client&nbsp;Form&nbsp;HTML
<td>
<a href="prospect-embeddable.php?bizid=<?= $id ?>" target=html>[view html]</a>
<a href="prospect-embeddable.php?save=1&bizid=<?= $id ?>" target=html>[save]</a>
<a href="prospect-embeddable.php?preview=1&bizid=<?= $id ?>" target=html>[preview]</a>
</table>

<td valign=top>

<table border=1 bordercolor=black style='margin-left:20px;'>
<tr><th>Users<th>Active<th>Inactive
<tr><td>Client<td><?= num('Client', 'active') ?><td><?= num('Client', 'INACTIVE') ?>
<tr><td>Sitter<td><?= num('Sitter', 'active') ?><td><?= num('Sitter', 'INACTIVE') ?>
<tr><td>Manager<td><?= num('Owner', 'active') ?><td><?= num('Owner', 'INACTIVE') ?>
<tr><td>Dispatcher<td><?= num('Dispatcher', 'active') ?><td><?= num('Dispatcher', 'INACTIVE') ?>
<tr><td>LT Staff<td><?= num('Staff', 'active') ?><td><?= num('Staff', 'INACTIVE') ?>
</table>


</table>


<form name='bizform' method='POST' enctype='multipart/form-data'>
<?
hiddenElement('saveBiz', 1);
labeledCheckbox('Business is active', 'activebiz', $biz['activebiz']); 
echo "<img src='art/spacer.gif' width=30px>";
labeledCheckbox('TEST Business ', 'test', $biz['test']);
if(TRUE) {
echo "<img src='art/spacer.gif' width=30px>";
fauxLink('Change Business Name', "this.style.display=\"none\";$(\"#bizNameDiv\").toggle();", false, "Edit the business name.  You must Save Changes afterwards.");
echo "<div id='bizNameDiv' style='display:inline;'>";
labeledInput('Business name:', 'bizName', $biz['bizname'], $labelClass=null, $inputClass='Input45Chars', $onBlur=null, $maxlength=null, $noEcho=false);
echo " <span class='tiplooks'>Remember to Save Changes</span></div>";
}

echo "<br>";
$freeuntil = $biz['freeuntil'] == '1970-01-01' ? 'forever' : $biz['freeuntil'];
labeledInput('Subscription free until', 'freeuntil', $freeuntil); echo " <font color='darkgreen'>Blank=not free, a date, or \"forever\"</font>";
echo "<br>";
labeledInput('Rate scale', 'rates', $biz['rates'], null, 'VeryLongInput'); echo " <font color='darkgreen'>Up to N sitters=X,Up to M sitters=Y,... If there is no \"0=X,\" entry, then zero sitters is free.  Include \"0=X\" to make them pay.</font>";

echo "<table border=0 bordercolor=black style='margin-top:3px;background:url(art/bgtile1.jpg)'><tr>";
echo "<td>";
//labeledCheckbox('Business Receives customer support', 'supportactive', $biz['supportactive']); 
//echo "<br>";
labeledCheckbox('Key Management Module is Active', 'mod_securekey', $prefs['mod_securekey']); 
echo "<p>";
labeledCheckbox('Mobile Sitter App Enabled', 'mobileSitterAppEnabled', $prefs['mobileSitterAppEnabled']); 
echo "</td><td style='border:solid black 1px;vertical-align:top;'>";
		if($uploaderror) echo "<font color=red>$uploaderror</font><br>";	
		$PRODF = '/var/www/prod';
		echo "<input type='file' name='logofile'> <input type=submit value=Upload> ";
		fauxLink('Drop Logo', "if(confirm(\"Drop logo?\")) document.location.href=\"maint-edit-biz.php?droplogo=$id&droplogotoken={$_SESSION['DROPLOGOTOKEN']}\"");
		echo " Max: 386 x 90";
		if($headerBizLogo = getHeaderBizLogo("bizfiles/biz_$id/")) 
			echo "<img valign=top src='$headerBizLogo'>";
		else echo "No Logo found";
echo "</td></tr></table>";
echo "<p>";
if($biz['eulasigned']) {
	$eulaDate = date('m/d/Y', strtotime($biz['eulasigned']));
	$eulaLink = "<a href='javascript:showEula({$biz['eulaversion']})'>$eulaDate</a>";
}
echo "EULA signed: ".($biz['eulasigned'] ? "<font color=green><b>Yes</b></font>: $eulaLink<br>" : 'No').' ';
if($biz['eulasigned']) {
	labeledCheckbox('<b>Unsign EULA</b>', 'unsign', 0); 
	echo "<== Do not check this box unless you wish to force the manager to sign the EULA again";
}



echoButton('', 'Save', 'document.bizform.submit()');
if(mattOnlyTest()) {
	echo "<img src='art/spacer.gif' width=80 height=1>";
	echoButton('', 'Edit Prefs', 'editPrefs()');
}
?>
</form>
<?
echo '<p>'.userLink("New User", 0);
echo "  ";
fauxLink('Toggle Clients', 'toggleClients()');
echo "  ";
fauxLink('Toggle Canceled', 'toggleCanceled()');
echo "<p>";
?>
<style>
.biztable td {padding-left:10px;}
</style>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function editPrefs() {
	$.fn.colorbox({href:"maint-edit-prefs.php?id=<?= $id ?>", width:"800", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}

function showEula(version) {
	$.fn.colorbox({href:"maint-edit-biz.php?showEULA="+version, width:"800", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}


function loginInstructions(username, password, email) {
	var passwordtable = 
		"<table bizid=<?= $id ?> cellspacing='10' align='center' bgcolor='lightblue'><tbody><tr><td>Username:</td><td bgcolor='white'><strong>"+username+"</strong></td></tr>"
		+"<tr><td>Temp Password:</td><td bgcolor='white'><strong>"+password+"</strong></td></tr>"
		+"<tr><td bgcolor='white'>Email Address:</td><td bgcolor='white'>"+email+"</td></tr></tbody></table>"
	var html = 
	"Here are your LeashTime login credentials.  Please save this note.<p>"
	+"Login page: https://<?= $_SERVER["HTTP_HOST"] ?>/login<br>"
	+"Username: <b>"+username+"</b><br>"
	+"Temp Password: <b>"+password+"</b><br>"
	+"Email Address: <b>"+email+"</b><p>"
	+"The password is temporary; the very next time you try to login "
	+"(whether using this password or not), this password will be erased.  When you login with this password, "
	+"you will be asked to supply a new permanent password.<p>"
	+"If your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: "
	+"<a href='https://<?= $_SERVER["HTTP_HOST"] ?>/login'>https://<?= $_SERVER["HTTP_HOST"] ?>/login</a> using the forgotten password link.<p>"
	+"To obtain a new password, you will need to supply your username ("+username+") and this email address ("+email+").  "
	+"Once you do, a new temporary password will be emailed immediately to that email address."
	+"<hr><pre>"
	+passwordtable.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
	+"</pre>"
	;
	
	$.fn.colorbox({html:html, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function descriptionBox() {
	var html = "<div style='font-size:1.1em'><?= str_replace('"', '&quot;', $totalDescription) ?></div>"; // '
	$.fn.colorbox({html:html, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function doOnReady() {
	toggleClients();
	$('#bizNameDiv').toggle();
}

var showCanceled = true;
var showClients = true;

function toggleCanceled() {
	showCanceled = !showCanceled;
	//$(".canceledtaskEVEN,.canceledtask").toggle()
	// this method is faster than toggle() and takes hidden clients into account
	var clients = document.getElementsByTagName('tr');
	var show = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	for(var i=0;i<clients.length;i++) {
		if(clients[i].className.indexOf('CLIENT') != -1 && !showClients) continue;
		if(clients[i].className.indexOf('canceledtask') != -1) {
//if(!confirm(clients[i].className)) return;
			clients[i].style.display = clients[i].style.display == 'none' ? show : 'none';
		}
	}
}

function toggleClients() {
	showClients = !showClients;
	//$('.CLIENT').toggle();
	// this method is faster than toggle()
	var clients = document.getElementsByTagName('tr');
	var show = '<?= $_SESSION['tableRowDisplayMode'] ?>';
	for(var i=0;i<clients.length;i++) {
		if(clients[i].className.indexOf('canceledtask') != -1 && !showCanceled) continue;
		if(clients[i].className.indexOf('CLIENT') != -1) {
//if(!confirm(clients[i].className)) return;
			clients[i].style.display = clients[i].style.display == 'none' ? show : 'none';
		}
	}
}

function stafflogin(bizid) {
	ajaxGetAndCallWith("lt-staff-login.php?bizptr="+bizid, confirmLogin, bizid)
}

function confirmLogin(bizid, response) {
	if(response.indexOf("FAIL") > -1) alert(response);
	else if(response == "SUCCESS") document.location.href='index.php';
	else if(confirm(response))
		ajaxGetAndCallWith("lt-staff-login.php?confirmed=1&bizptr="+bizid, confirmLogin, bizid)
}

function magRights(rights) {
	//alert(encodeURIComponent(rights));
	$.fn.colorbox({href:"?magrights="+ encodeURIComponent(rights), width:"800", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}
	
	
	
function update() {refresh();}

$(document).ready(doOnReady);
</script>
<?

tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'

include "refresh.inc";
function prefsLink($id) {
	global $bizptr;
	return fauxLink($id, "openConsoleWindow(\"logineditor\", \"maint-user-preference-list.php?id=$id\", 650,400)", 1);
}

function userLink($name, $id) {
	global $bizptr;
	return fauxLink($name, "openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid=$id&bizptr=$bizptr\", 600,400)", 1);
}

function bizLink($name, $id) {
	return fauxLink($name, "document.location.href=\"maint-edit-biz.php?id=$id\"", 1);
}

function namesort($a, $b) {
	return strcmp($a['lname'], $b['lname']) 
				|| strcmp($a['fname'], $b['fname']) 
				|| strcmp($a['name'], $b['name']);
}

function rightsSummary($rights) {
	global $allRights;
	$out = array();
	foreach(explode(',', substr($rights, 2)) as $right)
		$out[] = $allRights[$right]['label'];
	return join(', ', $out);
}

function rightsLink($user) {
	$title = rightsSummary($user['rights']);
	$rightsLabel = str_replace(',', ', ', $user['rights']);
	return "<a href='maint-edit-rights.php?id={$user['userid']}' title='$title'>$rightsLabel</a>";
}

function loginLink($id) {
	global $dbExists;
	return $dbExists ? "<img src='art/branch.gif' onclick='stafflogin($id)'> " : ''; 
}

function ensureDirectory($dir) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, 0775); // group needs x for matt to be able to edit the dir contents
  chgrp($dir, 503 /* www-access */ );
}

function copyDir($from, $to) {
	foreach(glob("$from/*") as $f) copy($f, "$to/".basename($f));
}

