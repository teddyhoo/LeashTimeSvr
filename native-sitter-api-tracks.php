<? // native-sitter-api-tracks.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$loginid = $_POST['loginid'] ? $_POST['loginid'] : 'tonyb@aol.com';
$date = $_POST['date'] ? $_POST['date'] : date('m/d/Y');
//print_r(fetchCol0("SHOW tables"));
if(!dbTEST('dogslife')) {
	echo "<p><b>Login to dogslife.  You are logged in to {$_SESSION['bizname']}.</b>";
	exit;
}
?>
<form method='POST' name='tracker'>
Login ID: <input id=loginid name=loginid value='<?= $loginid ?>' size=60><br>
Date: <input id=date name=date value='<?= $date ?>'><br>
<input type=button value='Go' onclick='checkAndSubmit()'>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function checkAndSubmit() {
	if(MM_validateForm('loginid', '', 'R',
											'date', '', 'isDate'))
			document.tracker.submit();
	
}
</script>
<?

if($_POST['loginid']) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$userptr = fetchRow0Col0("SELECT userid FROM tbluser WHERE loginid = '{$_POST['loginid']}' LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
	if($_POST['date']) $date = "AND date LIKE '".date('Y-m-d', strtotime($_POST['date']))."%' ";
	$tracks = fetchAssociations("SELECT * FROM tblgeotrack WHERE userptr = $userptr $date ORDER BY date");
	quickTable($tracks, $extra='border=1');
}

