<? //wisconsinfix.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";

echo "DISABLED";
exit;

if($db != 'wisconsinpetcare') {
	echo "wrong db: $db";
	exit;
}

// Changes since 1:38 am Dec 8
$changes = fetchAssociations("SELECT *
FROM `tblchangelog`
WHERE `time` > '2014-12-08 01:38%'
AND itemtable = 'tblappointment'
AND note NOT LIKE 'status: canceled [up%'");

foreach($changes as $ch) {
	$apptid = $ch['itemptr'];
	$user = $ch['user'];
	$time = $ch['time'];
	$note = $ch['note'];
	//echo print_r($ch,1)."<br>";
	$actual = getAppointment($apptid);
	echo "$apptid/$user/$time/$note<br>";
	if(!$actual) {
		echo "<b>Unknown visit: $apptid</b><br>";
		continue;
	}
	//else echo "--- ".print_r($actual, 1)."<br>";
	$completed = $canceled = null;
	$mod = null;
	if(strpos($note, 'status: completed') !== FALSE) {
		$completions += 1;
		$mod = array(
			'modified' =>$time,
			'completed' =>$time,
			'canceled' =>null,
			'modifiedby' => $user);
	}
	else if(strpos($note, 'status: canceled') !== FALSE) 
		$mod = array(
			'modified' =>$time,
			'canceled' =>$time,
			'completed' =>null,
			'modifiedby' => $user);
	else if(strpos($note, 'status: incomplete') !== FALSE) 
		$mod = array(
			'modified' =>$time,
			'canceled' =>null,
			'completed' =>null,
			'modifiedby' => $user);
	else if(strpos($note, 'QuickMods: timeofday') !== FALSE) {
		$note = explode('=>', $note);
		$note = $note[1];
		$timeofday = substr($note, 0, strlen($note)-1);
		$mod = array(
			'modified' =>$time,
			'modifiedby' => $user,
			'timeofday' =>$timeofday);
	}
	else if(strpos($note, 'QuickMods: providerptr') !== FALSE) {
		$note = explode('=>', $note);
		$note = $note[1];
		$providerptr = substr($note, 0, strlen($note)-1);
		$mod = array(
			'modified' =>$time,
			'modifiedby' => $user,
			'providerptr' =>$providerptr);
	}
	if(FALSE /* $mod && $mod['completed']*/) ;
	else if($mod && $mod['providerptr']) echo "<font color=purple>MOD: ".print_r($mod, 1).'</font><BR>';
	else if($mod && $mod['canceled']) echo "<font color=red>MOD: ".print_r($mod, 1).'</font><BR>';
	else if($mod && !$mod['completed']) echo "<font color=blue>MOD: ".print_r($mod, 1).'</font><BR>';
	else if($mod && $mod['completed']) echo "<font color=green>MOD: ".print_r($mod, 1).'</font><BR>';
	else echo "<b>NO MOD</b><br>";
	
	
	if($mod['completed']) ; // ignore
	else if(FALSE && $mod) {
		updateTable('tblappointment', $mod, "appointmentid=$apptid", 1);
		echo "<b>DONE</b><br>";
	}
}

echo "Completions NOT done: $completions";