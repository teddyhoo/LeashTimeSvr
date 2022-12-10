<? // value-pack-link.php
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "value-pack-fns.php";
include "preference-fns.php";
locked('o-');

$apptid = $_REQUEST['apptid'];
$clientptr = $_REQUEST['clientptr'];
$vpptr = $_REQUEST['vpptr'];
$link = $_REQUEST['link'];
//$clientptr = $_REQUEST['clientptr'];
//$vpid = $_REQUEST['vpid'];

$extraBodyStyle = "background-image:none;";

require_once "frame-bannerless.php";



if($link) {
	// link is a composite: vpptr|apptid
	list($vpptr, $apptid) = explode('|', $link);
	$errorArrayOrLabel = applyToken($vpptr, $apptid);
	// if both parts are present, link them
	if($vpptr && $apptid) {
		// first make sure valuepack is eligible
		if(packageStatus($vpptr) != 'active')
			$error = 'Value pack is ineligible.';
		// next make sure appt does not have a prior token
		else if(fetchRow0Col0("SELECT value FROM tblappointmentprop WHERE appointmentptr = $apptid AND property = 'vpptr' LIMIT 1", 1))
			$error = 'Visit already has a token.';
		if(!$error) {
			$appt = fetchFirstAssoc(
				"SELECT a.*, b.paid
					FROM tblappointment a 
					LEFT JOIN tblbillable b ON itemtable = 'tblappointment' AND itemptr = $apptid AND superseded = 0");
			if($appt['canceled']) $error = "Value pack tokens cannot be applied to canceled visits.";
		}
	}
	// otherwise break the link
	else ;
	echo "<script language='javascript'>
		if(parent.updateValuePackToken) {
			parent.updateValuePackToken('$label');
			parent.$.fn.colorbox.close();
		}
		</script>";

}
else if($vpptr) {
	valuepackDescription($vpptr);
}
else if($apptid) {
	$vpptr = getAppointmentProperty($apptid, 'vpptr');
	if($vpptr) {
		echo "This visit is paid for with...";
		valuepackDescription($vpptr);
	}
	else valuepackPicker($apptid, $clientptr);
}
else valuepackPicker(null, $clientptr);
