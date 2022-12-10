<?  // convert-servicetypes-sg.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

exit;

locked('o-');

if(!dbTEST('savinggrace')) {echo "BUZZZ!"; exit;}

$oldnewmap = array(
	66 => 58, // Mid Day Dog Walk 3x => Mid-Day Dog Walk
	/*
	55 => 82, // Evening Walk => AM/PM Walk
	75 => 82, // Evening Walk 2 dogs => AM/PM Walk
	63 => 82, // Morning Walk => AM/PM Walk
	76 => 82, // Morning Walk 2 Dogs => AM/PM Walk
	
	59 => 58, // Mid Day Dog Walk 2 Dogs => Mid-Day Dog Walk
	68 => 58, // Mid Day Dog Walk 3 Dogs 3x => Mid-Day Dog Walk
	
	48 => 95, // Mid Day Walk => Pet Sit Visit
	57 => 95, // Mid Day Walk 2 Dogs => Pet Sit Visit
	
	67 => 94, // Mid Day Dog Walk 2 dogs 2x => On-Call Walk
	
	90 => 89, // Extra Pet x2 => Extra Pet
	
	88 => 50  // Boarding 2 dogs => 	Boarding*/
	
);

$firstday = '2012-05-01';

foreach($oldnewmap as $old => $new) {
	$datetest = "date >= '$firstday'";
	// updateTable (table, changes, where clause)
	// update birthmark to prevent unwanted duplicsates at rollover time
	updateTable('tblappointment', 
		array('servicecode'=>$new, 'birthmark'=>sqlVal("CONCATENATE(LEFT(birthmark, LOCATE('_', birthmark)), $new)"), 
		"servicecode=$old AND $datetest", 1);
	echo mysql_affected_rows()." rows changed in tblappointment.<br>";
	updateTable('tblservice', array('servicecode'=>$new), "servicecode=$old", 1);
	echo mysql_affected_rows()." rows changed in tblservice.<br>";
	
	$rows = fetchAssociations("SELECT * FROM relclientcharge WHERE servicetypeptr=$old");
	foreach($rows as $row) {
		$row['servicetypeptr'] = $new;
		insertTable('relclientcharge', $row, 1);
		$n++;
	}
	echo "$n rows added to relclientcharge.<br>";
	$n = 0;
	
	$rows = fetchAssociations("SELECT * FROM relproviderrate WHERE servicetypeptr=$old");
	foreach($rows as $row) {
		$row['servicetypeptr'] = $new;
		$row['note'] = sqlVal('');
		$alreadySet = fetchCol0("SELECT providerptr FROM relproviderrate WHERE servicetypeptr=$new");
		if(!in_array($row['providerptr'], $alreadySet)) {
			insertTable('relproviderrate', $row, 1);
			$n++;
		}
	}
	echo "$n rows added to relproviderrate.<br>";
}
		
