<?
// client-agreement-signed.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "invoice-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";
require_once "cc-processing-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = {$_REQUEST["id"]}", 1);
if(!$client) $error = "Bad client ID supplied.";
else if(!$client['userid']) $error = "Client {$client['name']} has no LeashTime login ID.";
if($error) {
	echo $error;
	exit;
}
$userid = $client['userid'];

require_once "agreement-fns.php";

$currentAgreement = clientAgreementSigned($userid);

if($showhistory) {
	$extraBodyStyle = "background-image:none;";
	$customStyles = ".dateColClass {width: 180px;}";
	require "frame-bannerless.php";
	echo "<h2>Service Agreement History</h2>";

	$agreements = agreementHistory($userid);
	foreach($agreements as $dtime => $version) {
		$ag = getServiceAgreement($version, $withoutTerms=1);
		$rows[] = array('datetime'=>shortDateAndTime(strtotime($dtime)), 
									'label'=>fauxLink($ag['label'], "document.location.href=\"?id=$id&datesigned=$dtime\"", 1, "View this signed agreement."));
		if($currentAgreement['agreementdate'] == $dtime) $currentAgreementShownAlready = 1;
	}
	if($currentAgreement && !$currentAgreementShownAlready) 
			$rows[] = array('datetime'=>shortDateAndTime(strtotime($currentAgreement['agreementdate'])), 
										'label'=>fauxLink($currentAgreement['label'], "document.location.href=\"?id=$id\"", 1, "View this signed agreement."));

	$columns = explodePairsLine('datetime|Signed||label|Version');
	$colClasses = array('datetime'=>'dateColClass');
	tableFrom($columns, $rows, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
	exit;
}

if($datesigned) { // datetime passed in to view a historical entry
	$signing = 	fetchFirstAssoc("SELECT property, value 
			FROM tbluserpref 
			WHERE userptr = $userid AND property = 'agreement_$datesigned'", 1);
	$clientAgreement = array('agreementptr'=>$signing['value'], 'agreementdate'=>$datesigned);
}
else $clientAgreement = $currentAgreement;
$agreement = getServiceAgreement($clientAgreement['agreementptr'], 0);
if(!$agreement) {
	$error = "Client {$client['name']} has not signed an agreement.";
	echo $error;
	exit;
}
$agreementTerms = filterString($agreement['terms']);
$agDate = shortNaturalDate(strtotime($clientAgreement['agreementdate']))." at ".date('g:i a', strtotime($clientAgreement['agreementdate'])).".";
$signedAgreementsFound = fetchKeyValuePairs($sql =
	"SELECT property, value 
		FROM tbluserpref 
		WHERE userptr = $userid AND property LIKE 'agreement_%'
		ORDER BY property", 1);
require "common/init_db_common.php";
$loginid = fetchRow0Col0("SELECT loginid FROM tbluser WHERE userid = $userid LIMIT 1", 1);
?>
<h2>Service Agreement</h2>
Client <?= $client['name'] ?> accepted the following agreement after logging in to LeashTime as <?= $loginid ?> on <?= $agDate ?>
<?
if(mattOnlyTEST()) {
	if($signedAgreementsFound) {
		echo "<p>";
		echo "<a href='client-agreement-signed.php?id=$id&showhistory=1'>View All Signed Agreements</a>";
		//fauxLink("View All Signed Agreements", "document.location.href=\"client-agreement-signed.php?id=$id&showhistory=1", 0);
	}
}
?>
<p>
<div style='background:#eeeeee;border: solid black 1px;padding:5px;overflow:auto;'>
<?= $agreement['html'] ? $agreementTerms : htmlizeAgreementText($agreementTerms) ?>
<p style='font-style:italic;font-size:80%'>Agreement version date: <?= shortDateAndTime(strtotime($clientAgreement['date'])) ?></p>
</div>
<span style='font-style:italic;font-size:100%'>
Client <?= $client['name'] ?> accepted the agreement shown above after logging in to LeashTime as <?= $loginid ?> on <?= $agDate ?>
</span>
<?
exit;
