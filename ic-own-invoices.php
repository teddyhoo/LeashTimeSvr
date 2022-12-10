<? // ic-own-invoices.php


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "request-fns.php";
require_once "preference-fns.php";

locked('p-');

if(!getPreference('sittersCanSendICInvoices')) $error = 'This function is not enabled.';
if(!$error) {
	$invoices = fetchAssociations(
	"SELECT * FROM tblclientrequest
		WHERE providerptr = {$_SESSION["providerid"]}
			AND requesttype = 'ICInvoice'
		ORDER BY received DESC", 1);
	foreach($invoices as $i => $inv) {
		$extraFields = getExtraFields($inv);
		$invoices[$i]['submitted'] = shortDateAndTime(strtotime($inv["received"]));
		$invoices[$i]['starting'] = shortDate(strtotime($extraFields["x-label-Starting"]));
		$invoices[$i]['ending'] = shortDate(strtotime($extraFields["x-label-Ending"]));
		$invoices[$i]['label'] = fauxLink("#{$inv["requestid"]}", "showInvoice({$inv['requestid']})", 'noecho');
	}
}


$pageTitle = "Invoices Submitted to {$_SESSION["bizname"]}";

include "frame.html";
// ***************************************************************************
if($error) echo "<span class='warning'>$error</span>";
else if(!$invoices) echo "No invoices were found.";

else {
	$columns = explodePairsLine('submitted|Submitted||label|Invoice||starting|Starting||ending|Ending');
	tableFrom($columns, $invoices, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}
// ***************************************************************************
?>
<script>
function showInvoice(reqid) {
	$.fn.colorbox({href:"request-review.php?lightbox=colorbox&id=-"+reqid, iframe: "true", width:"750", height:"650", scrolling: true, opacity: "0.3"});
}
</script>
<?
include "frame-end.html";
