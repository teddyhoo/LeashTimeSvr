<? // message-archive-setup.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "archive-fns.php";
set_time_limit(300);
locked('o-');
$is_set_up = 	in_array('tblmessagearchive', fetchCol0("SHOW TABLES"));

if($_GET['defrag']) {
	$checksql = "SELECT (data_length+index_length)/power(1024,1) tablesize_kb
					FROM information_schema.tables
					WHERE table_schema='$db' and table_name='tblmessage';";
	$before = fetchRow0Col0($checksql);
	$_SESSION['DEFRAGNOTE'] = "<hr>Defragging tblmessage.  Before: ".number_format($before)." KB ";
	doQuery("ALTER TABLE tblmessage ENGINE=INNODB");
	$after = fetchRow0Col0($checksql);
	if($is_set_up) {
		$checksql = "SELECT (data_length+index_length)/power(1024,1) tablesize_kb
					FROM information_schema.tables
					WHERE table_schema='$db' and table_name='tblmessagearchive';";
		$archiveSize = fetchRow0Col0($checksql);
	}
	$_SESSION['DEFRAGNOTE'] .= " After: ".number_format($after)." KB"
			." + archive table (".number_format($archiveSize)
			.($is_set_up ? ") = ".number_format($after+$archiveSize)." KB." : '')
			."<hr>";
	globalRedirect("message-archive-setup.php");
	
}

echo "<h2>Business: {$_SESSION['preferences']['bizName']}</h2>";
echo "<a href='index.php'>Home</a> -- <a href='optional-business-features.php'>Optional Features</a>";
echo " -- <a href='message-archive-setup.php?defrag=1'>DEFRAG</a><p>";
echo "<p style='color:red'>REMEMBER TO DEFRAG!</p>";
echo "enableMessageArchiveCron: ".($_SESSION['preferences']['enableMessageArchiveCron'] ? 'ON' : 'off');
echo " - enableMessageArchiveFeature: ".($_SESSION['preferences']['enableMessageArchiveFeature'] ? 'ON' : 'off')."<p>";

echo $_SESSION['DEFRAGNOTE'];
unset($_SESSION['DEFRAGNOTE']);

$days = $_SESSION['preferences']['archiveMessageDaysOld']
				? $_SESSION['preferences']['archiveMessageDaysOld']
				: 540;
$days = "-$days days";

if($_POST['go']) {
	if($db != $_POST['db']) { 
		echo "WRONG DB! (not {$_POST['db']})"; 
		exit;
	}
	$date = date('Y-m-d', strtotime($days)).' 00:00:00';
	if($is_set_up) echo fetchRow0Col0("SELECT count(*) FROM tblmessagearchive")." already in archive.<p>";
	echo fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime < '$date'")." visits to archive.<p>";
	$t0 = microtime(1);
	$results = archiveMessagesBefore($days, $_POST['maxCount'], 'deleteAlso');
	echo "<hr>
				$db messages archived: {$results['added']} errors: ".($results['errors'] ? $results['errors'] : '0')
			." last datetime: {$results['lastdate']} Backup time: ".(microtime(1)-$t0)." secs<hr>";
}

if($_POST['godelete']) {
	if($db != $_POST['db']) { 
		echo "WRONG DB! (not {$_POST['db']})"; 
		exit;
	}

	$t0 = microtime(1);
	$results = safeDeleteMessagesBefore($days, $_POST['maxCount']);
	echo "$db messages deleted: {$results['deleted']} errors: ".($results['errors'] ? $results['errors'] : '0')
			." Safe Deletion time: ".(microtime(1)-$t0)." secs<hr>";

}
$maxCount = 10000;

$regularMessages = fetchRow0Col0("SELECT count(*) FROM tblmessage");
$sql = 
"SELECT (data_length+index_length)/power(1024,1) tablesize
	FROM information_schema.tables
	WHERE table_schema='$db' and table_name='#TAB#';";
$msgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessage', $sql));

echo "Threshold: $days days<p>";
echo "Active messages: $regularMessages (".number_format($msgsizeKB, 0)." KB)<p>";
echo "Message archive is set up: ".($is_set_up ? 'yes' : 'no')."<p>";
if($is_set_up) {
	$archMessages = fetchRow0Col0("SELECT count(*) FROM tblmessagearchive");
	$archmsgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessagearchive', $sql));
	echo "Archived messages: $archMessages (".number_format($archmsgsizeKB, 0)." KB)<p>";
}
$date = date('Y-m-d', strtotime($days)).' 00:00:00';
echo "<p>Messages to be archived (before $date): ".fetchRow0Col0($sql = "SELECT count(*) FROM tblmessage WHERE datetime < '$date'")."<p>";
//echo "<p>$sql";

echo "<p>Will attempt to archive $maxCount messages.<p>";
?>
<form name='archiveform' method='POST'>
<input type='hidden' name='go' id='go'>
<input type='hidden' name='db' value="<?= $db ?>">
<input type='hidden' name='maxCount' id='maxCount' value="<?= $maxCount ?>">
<input type='button' onclick='doArchive()' value='Archive Messages'>
</form>
<hr>
<hr>
<form name='deleteform' method='POST'>
<input type='hidden' name='godelete' id='godelete'>
<input type='hidden' name='db' value="<?= $db ?>">
<input type='hidden' name='maxCount' id='maxCount' value="<?= $maxCount ?>">
<input type='button' onclick='doDelete()' value='Safe Delete Messages'>
</form>




<script language='javascript'>
function doArchive() {
	if(confirm('About to archive <?= $maxCount ?> messages.  Proceed?')) {
		document.getElementById('go').value=1;
		document.archiveform.submit();
	}
	else alert('Archive aborted.');
}
function doDelete() {
	if(confirm('About to delete <?= $maxCount ?> messages.  Proceed?')) {
		document.getElementById('godelete').value=1;
		document.deleteform.submit();
	}
	else alert('Deletion aborted.');
}
</script>

<?


exit;

if(!dbTEST('dogslife')) { echo "WRONG DB!"; exit;}

$days = "-730 days";

$t0 = microtime(1);
$results = safeDeleteMessagesBefore($days);
echo "$db messages deleted: {$results['deleted']} errors: ".($results['errors'] ? $results['errors'] : '0')
		." Safe Deletion time: ".(microtime(1)-$t0)." secs<hr>";
