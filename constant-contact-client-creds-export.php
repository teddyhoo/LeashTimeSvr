<? // constant-contact-client-creds-export.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";

locked('o-');
$users = fetchAssociationsKeyedBy($sql = 
	"SELECT userid, loginid, temppassword 
		FROM tbluser 
		WHERE bizptr = {$_SESSION["bizptr"]}
			AND rights LIKE 'c-%'
			AND temppassword IS NOT NULL", 'userid');
//print_r(fetchAssociations($sql));		
//echo "$sql";
require_once "common/init_db_petbiz.php";

$sql =
	"SELECT email, fname, lname, userid 
		FROM tblclient 
		WHERE active=1 AND email IS NOT NULL AND userid IS NOT NULL";
//
if(!($result = doQuery($sql))) return null;
while($row = mysql_fetch_assoc($result)) {
	$user = $users[$row['userid']];
	if(!$user) continue;
	unset($row['userid']);
	unset($user['userid']);
	$row = array_merge($row, $user);
	dumpCSVRow($row);
}

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
			$row = $nrow;
		}
		echo join(',', array_map('csv',$row))."\n";
	}
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}
