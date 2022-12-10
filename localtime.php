<? // localtime.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($_POST) {
	$localTime = date('m/d/Y H:i:s', strtotime($_POST['timestamp'])); // D m/d/Y H:i:s
	$pastResults = $_POST['pastResults']."<br><span style='color:gray'>{$_POST['timestamp']}:</span> $localTime";
}
?>
<form method='POST'>
Time Stamp: <input name='timestamp'><input type='submit' value='Submit'>
<input type='hidden' name='pastResults' value="<?= $pastResults ?>">
</form>
<p><?= $pastResults ?>
