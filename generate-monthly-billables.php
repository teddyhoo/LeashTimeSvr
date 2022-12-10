<? // generate-monthly-billables.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('o-');
if(!$_SESSION['staffuser']) { echo 'Staff Use Only.'; exit; }


require_once"invoice-fns.php";

$maxBillableId = fetchRow0Col0("SELECT max(billableid) FROM tblbillable");

billingCron($force=true);

$billables = fetchAssociations(
	"SELECT CONCAT_WS(' ', fname, lname) as name, monthyear 
		FROM tblbillable 
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE monthyear IS NOT NULL AND billableid > $maxBillableId
		ORDER BY lname, fname");

echo "Created ".count($billables)." billables:<p>";
foreach($billables as $b) echo "<br>{$b['name']} for ".date('M Y', strtotime($b['monthyear']));