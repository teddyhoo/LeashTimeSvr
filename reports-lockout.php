<? // reports-lockout.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";
require_once "client-flag-fns.php";
require_once "preference-fns.php";

locked('-o');
if(!$_SESSION['staffuser']) {
	echo "This report is for LeashTime Staff only.";
}

// find all locked out businesses
$lockedbizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE lockout IS NOT NULL", 'bizid');
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz", 'bizid');

require_once "common/init_db_petbiz.php";

$lockedOrMarkedLockedOrMarkedToBeLocked = fetchAssociationsKeyedBy(
	"SELECT clientptr, tblclient.*
		FROM tblclientpref
		LEFT JOIN tblclient ON clientptr = clientid
		WHERE garagegatecode IN (".join(',', array_keys($lockedbizzes)).") 
		OR property IN ('billing_flag_4', 'billing_flag_4')
		ORDER BY fname", 'clientptr');
		
		
$pageTitle = "Lockouts";
include "frame.html";
	
	// ***************************************************************************
?>
<style>
.lockie td {font-size:1.1em;padding-left:10px;padding-top:10px;}
</style>
<?
echo "<table class='lockie'>";
echo "<tr><th>Client<th>Flags<th>Database Lock</tr>";
foreach($lockedOrMarkedLockedOrMarkedToBeLocked as $client) {
	$biz = $bizzes[$client['garagegatecode']];
	$class = $biz['activebiz'] ? 'futuretask' : 'canceledtask';
	echo "<tr class='$class'><td><b>{$client['fname']}</b> {$client['lname']}</td><td>";
	echo clientFlagPanel($client['clientid'], $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, $includeBillingFlags=true);
	echo "</td>";
	$lockoutDate = $biz['lockout'] ? "<img src='art/lockout-red.gif'> ".date('m/d/Y', strtotime($biz['lockout'])) 
		: "<img src='art/lockout-white.gif'>";
	echo "<td>$lockoutDate</td>";
	echo "</tr>";
}
echo "</table>";
?>
<script language='javascript'>
function editFlags(clientid) {
	$.fn.colorbox({href: "client-flag-picker.php?clientptr="+clientid, width:"600", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}
function update(target, value) {
	document.location.href='reports-lockout.php';
}
</script>
<?
include "frame-end.html";
