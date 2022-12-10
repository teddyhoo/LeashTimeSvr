<? // maint-lockout.php  ?bizId
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
if(FALSE && adequateRights('b')) {
	echo "Insufficient rights.";
	exit;
}
extract(extractVars('bizId,lockout,lockoutnote,getlogins', $_REQUEST));

if($getlogins) {
	showLogins($bizId);
}
else {

	if(isset($_REQUEST['lockout'])) {
		$lockout = $lockout ? date('Y-m-d', strtotime($lockout)) : sqlVal('null');
		updateTable('tblpetbiz', array('lockout'=>$lockout), "bizid = '$bizId'", 1);
		deleteTable('tblpetbizpref', "property = 'lockoutnote' AND bizptr = '$bizId'", 1);
		if($lockoutnote) replaceTable('tblpetbizpref', array('property'=>'lockoutnote', 'value'=>$lockoutnote, 'bizptr' => $bizId), 1);
		//echo "lockout set to $lockout where bizid = '$bizId'<p>";print_r($_GET);
		if(mysql_error()) echo mysql_error();
		else echo 'OK';
		exit;
	}
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizId LIMIT 1");
	$lockout = $biz['lockout'];
	$lockoutnote = fetchRow0Col0("SELECT value from tblpetbizpref WHERE bizptr = $bizId AND property = 'lockoutnote' LIMIT 1", 1);
	include "frame-bannerless.php";
	$lockout3am = strtotime("$lockout 03:00:00");
	$today3am = strtotime(date('Y-m-d')." 03:00:00");
	$state = 'No Lockout';
	if($lockout) {
		$delta = ($lockout3am - $today3am) / (24*3600);
		$state = $delta > 0 ? 'Lockout Pending' : 'Locked out';
		$delta = $delta == 0 ? 'today' : (
						 $delta == 1 ? 'tomorrow' : (
						 $delta == -1 ? 'yesterday' : (
						 $delta < 0 ? abs($delta)." days ago" : (
						 abs($delta)." days from now"))));
	}

	echo "<h2>Business: {$biz["bizname"]} (".($lockout ? "<font color=red>$state</font>" :  $state).")</h2>";
	echoButton('', "Remove Lock", "go(0)");
	echo "<p>";
	echoButton('', "Lock Business Out", "go(1)", 'HotButton', 'HotButtonDown');
	echo " ";
	calendarSet("on date: ", 'lockout', $lockout);
	echo "<p>";
	labeledInput('Note:', 'lockoutnote', $lockoutnote, $labelClass=null, $inputClass='input600');
	?>
	<span id='fromnow'><?= $lockout ? "$lockout is ".$delta : ''?></span>
	<?
	
	$summary = getAccountInfo($bizId);
	if(is_string($summary)) echo "<p>$summary";
	else {
		echo "<p>Balance: ".dollarAmount($summary['balance']);
		echo ($summary['lastPayment'] ? "<br>Last Payment: ".creditSummary($summary['lastPayment']) : '');
		echo ($summary['lastCredit'] ? "<br>Last Credit: ".creditSummary($summary['lastCredit']) : '');
	}

	echo "<p>";
	fauxLink('Show Recent Logins', "getLogins()");
	echo "<p><table><tr><td id='loginstable'></td><td valign=top id='detail'></td></tr></table>";
?>


<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('lockout','Lockout date');
function getLogins() {
	$('#loginstable').html("Please wait...");
	$.ajax({
	  url: "maint-lockout.php?getlogins=1&bizId=<?= $bizId ?>"
	}).done(function(data) { 
	  $('#loginstable').html(data);
	});
}

function go(lock) {
	if(lock) {
		lock = $('#lockout').val();
		if(!MM_validateForm('lockout','','isDate')) return;
		if(!confirm('Lock out <?= safeValue($biz["bizname"]) ?>\non '+lock+'?')) return;
	}
	$.ajax({
	  url: "maint-lockout.php?bizId=<?= $bizId ?>&lockout="+lock+"&lockoutnote="+encodeURIComponent($('#lockoutnote').val())
	}).done(function(data) { 
	  if(data == 'OK') {
			window.parent.$.fn.colorbox.close();
			if(window.parent) window.parent.update();
			return true;
		}
		else alert(data);
	});
	
}
$('#lockout').mouseup(function() {$('#fromnow').html('');});
$('#lockout').change(function() {$('#fromnow').html('');});

<? dumpPopCalendarJS() ?>
</script>
<?
} // if NOT getLogins

function showLogins($bizId) {
	$result = doQuery($sql = "SELECT tbllogin.loginid, success, rights, failurecause, bizid, bizname, 	remoteaddress, browser, LastUpdateDate as time
	FROM tbllogin
	LEFT JOIN tbluser ON tbluser.loginid = tbllogin.loginid
	LEFT JOIN tblpetbiz ON bizid = bizptr
	WHERE bizid = $bizId
	ORDER BY LastUpdateDate DESC LIMIT 30");
	echo "<p><table><tr><td><table class=biztable>";
	$failures = explodePairsLine("0|Ok||L|Locked out||P|Bad password||U|Unknown user||I|Inactive User||R|RightsMissingOrMismatched||F|No Business found||B|Business inactive||M|Missing organization||O|Organization inactive||C|No cookie||D|Logins disabled for this role");

	$roles = explodePairsLine("p|P||o|O||c|C||d|D");
	$titles = explodePairsLine("p|P = Sitter||o|O = Owner / Manager||c|C = Client||d|D = Dispatcher");
	echo join(' - ', $titles);
	if($result) while($line = mysql_fetch_assoc($result)) {
		$time = date('D m/d/Y H:i:s', strtotime($line['time']));
		$color = $line['failurecause'] ? "style='background:pink'" : '';
		$failure = $line['failurecause'] ? $line['failurecause'] : '0';
		$failure = $failures[$failure];
		if(!$failure) $failure = "??$failure??";
		$link = fauxLink($line['loginid'], "ajaxGet(\"maint-logins.php?loginid={$line['loginid']}\", \"detail\")", 1);
		$role = $roles[$line['rights'] ? substr($line['rights'], 0, 1) : ''];
		//$title = $titles[$line['rights'] ? $titles[substr($line['rights'], 0, 1)] : ''];
		$bizName = $bizdb != -1 ? '' : "<td $color>{$line['bizname']}";
		unset($line['remoteaddress']);
		unset($line['browser']);
		echo "<tr><td $color>$time<td $color>$role<td $color>$link<td $color>$failure$bizName<td $color>{$line['remoteaddress']}<td class='browsertoggle' $color>{$line['browser']}";
	//print_r($line);	
	}
	if(!$role) echo "<tr><td>Nothing found: $sql";
	echo "</table>";
}

function getAccountInfo($bizId) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1", 1);
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');
	$clientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = '$bizId' LIMIT 1", 1);
	if(!$clientid) return "No client found for biz ID $bizID";
	require_once "invoice-fns.php";
	$summary['balance'] = getAccountBalance($clientid);
	$summary['lastPayment'] = fetchFirstAssoc(
		"SELECT * FROM tblcredit 
		WHERE clientptr = '$clientid' 
			AND payment = 1
		ORDER BY issuedate DESC
		LIMIT 1", 1);

	$summary['lastCredit'] = fetchFirstAssoc(
		"SELECT * FROM tblcredit 
		WHERE clientptr = '$clientid' 
			AND payment = 0
		ORDER BY issuedate DESC
		LIMIT 1", 1);
	return $summary;
}

function creditSummary($credit) {
	$voidedAmount = $credit['voidedamount'] ? "<span class='warning'>VOIDED ".dollarAmount($credit['voidedamount'])."</span>" : '';
	return shortDate(strtotime($credit['issuedate']))." ".dollarAmount($credit['amount'])."$voidedAmount {$credit['note']}";
}