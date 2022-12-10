<? // provider-availability-ajax.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "appointment-fns.php";
$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-');
extract(extractVars('date,provids', $_REQUEST));
$date = date('Y-m-d', strtotime($date));
$offs = fetchAssociations($sql =
	"SELECT ifnull(nickname, CONCAT_WS(' ', p.fname, p.lname)) as providername, timeofday 
		FROM tbltimeoffinstance
		LEFT JOIN tblprovider p ON providerid = providerptr
		WHERE '$date' = `date` AND active");

if($provids) 
	$appts = fetchAssociations(
		"SELECT starttime, timeofday, label, 
			CONCAT_WS(' ', c.fname, c.lname) as clientname, CONCAT_WS(' ', c.lname, ',', c.fname) as csortname,
			ifnull(nickname, CONCAT_WS(' ', p.fname, p.lname)) as providername
		FROM tblappointment
		LEFT JOIN tblclient c ON clientid = clientptr
		LEFT JOIN tblprovider p ON providerid = providerptr
		LEFT JOIN tblservicetype s ON servicetypeid = servicecode
		WHERE date = '$date' AND canceled IS NULL AND providerptr IN ($provids)
		ORDER BY providername, starttime, csortname", 1);

ob_start();
ob_implicit_flush(0);
echo "<response>";
echo "<appts>";
foreach((array)$appts as $appt)
	echo "<appt><p><![CDATA[{$appt['providername']}]]></p><t>".briefTimeOfDay($appt)."</t><s><![CDATA[{$appt['label']}]]></s></appt>";
echo "</appts>";
echo "<offs>";
foreach((array)$offs as $off) {
	$tod = $off['timeofday'] ? briefTimeOfDay($off) : 'All day';
	echo "<off><p><![CDATA[{$off['providername']}]]></p><t>$tod</t></off>";
}
echo "</offs>";
echo "</response>";

$descr = ob_get_contents();
ob_end_clean();

echo $descr;