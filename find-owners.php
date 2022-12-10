<?  // find-owners.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";

locked('z-');
$sql = "
SELECT loginid, fname, lname, userid, bizname, bizptr
FROM `tbluser`
LEFT JOIN tblpetbiz ON bizid = bizptr
WHERE ltstaffuserid =0
AND rights LIKE 'o-%'
AND test = 0
ORDER BY bizname, userid ASC";
echo "<table border=1>";
foreach(fetchAssociations($sql) as $u) {
	if($bizptr != $u['bizptr'] && $u['userid'] != 2148) doQuery("update tbluser set isowner=1 where userid = {$u['userid']} LIMIT 1",1);
	$style = $bizptr != $u['bizptr'] ? 'style="background:yellow"' : '';
	$bizptr = $u['bizptr'];
	echo "<tr $style><td>{$u['loginid']}<td>{$u['fname']}<td>{$u['lname']}
				<td>{$u['userid']}<td>{$u['bizname']}<td>{$u['bizptr']}";
}
echo "</table>";

/*
Who's the owner:
dogcentric - NOT 2148
omidog - erin markish (3525) also?
*/