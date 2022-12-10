<? // maint-emergency.php 
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');

if($_POST) {
	reconnectPetBizDB($db, $dbhost, 'root', $_POST['p'], $force=true);
	$rows = fetchAssociations("SHOW FULL PROCESSLIST");
	$columns = explodePairsLine('Id|Id||User|User||db|db||Command|Command||Time|Time||State|State||Info|Info');
	tableFrom($columns, $rows, "WIDTH=600",null,null,null,null,$colSorts,$rowClasses, $colClasses);

}

require_once "common/init_db_common.php";
?>
<form method="POST">
<input name=p> <input type=submit>
<input type=hidden name=restart>
</form>
<p>/sbin/service mysqld restart