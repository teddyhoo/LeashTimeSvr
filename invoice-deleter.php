<? // invoice-deleter.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
$locked = locked('o-');

$start = $_GET['start'];
if(!$start) $error = "NO START";
if(!strtotime($start)) $error = "BAD START";
$start = date('Y-m-d', strtotime($start));

if($error) {
	echo $error;
	exit;
}

if($_GET['kill']) {
	$id = $_GET['kill'];// $_GET['kill']
	$found = fetchFirstAssoc(
			"SELECT invoiceid, clientptr
				FROM tblinvoice 
				WHERE invoiceid = $id");
	if(!$found) echo "Invoice LT$id NOT FOUND!";
	else {
		$clientname = fetchRow0Col0(
			"SELECT CONCAT_WS(' ', fname, lname) 
				FROM tblclient 
				WHERE clientid = {$found['clientptr']}");
		deleteTable('tblinvoice', "invoiceid=$id", 1);
		echo "Deleted $clientname's invoice LT$id<br>";
		foreach(explode(',', 'relinvoicecan,relinvoicecredit,relinvoiceitem,relinvoicerefund') as $table) {
			deleteTable($table, "invoiceptr=$id", 1);
			echo "Deleted ".mysqli_affected_rows()." $table rows<br>";
		}
		update('tblbillable', array('invoiceptr'=>null), "invoiceptr = $id", 1);
	}
	echo "<hr>";
		
}

$invoices = fetchAssociations(
	"SELECT CONCAT_WS(', ', lname, fname) as sortname, inv.*
		FROM tblinvoice inv
		LEFT JOIN tblclient ON clientptr = clientid
		WHERE date >= '$start'
		ORDER BY sortname, date");
		
foreach($invoices as $inv) {
	if($inv['sortname'] != $lastName)
		$rows[] = array('#CUSTOM_ROW#'=>
			"<tr><td colspan=7><hr><a target='CLIENTWIN' href='client-edit.php?tab=account&id={$inv['clientptr']}'>{$inv['sortname']}</a></td></tr>");
	$lastName = $inv['sortname'];
	//Curr Inv	Amount Due	Status
	$row = array('date'=>shortDate(strtotime($inv['date'])), 'pastbalancedue'=>$inv['pastbalancedue'], 
									'origbalancedue'=>$inv['origbalancedue'], 'balancedue'=>$inv['balancedue'],
									'creditsapplied'=>$inv['creditsapplied']);
	$row['delete'] = fauxLink('DELETE', "kill({$inv['invoiceid']})", 1, 'Delete this invoice'); //document.location.href='invoice-deleter.php?start=$start&kill=
	$row['invoiceid'] = fauxLink('LT'.$inv['invoiceid']	, "viewInvoice({$inv['invoiceid']})", 1, 'View this invoice');
	$rows[] = $row;
}
$columns = explodePairsLine(
	'date|InvoiceDate||invoiceid|Invoice||origbalancedue|Orig Bal Due'
	.'||pastbalancedue|Prior Bal||balancedue|Curr Bal Due||creditsapplied|Credit Appl'
	.'||delete|');
	
require_once "frame-bannerless.php";
echo "<a href='index.php'>Home</a>";
echo "<h2>Delete Invoices for {$_SESSION['bizname']}</h2><p>Invoices from ".shortDate(strtotime($start))." onward shown.";
?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="common.js"></script>
<script type="text/javascript">
function kill(invoiceid) {
	if(confirm("Kill LT"+invoiceid+"?"))
		document.location.href = "https://leashtime.com/invoice-deleter.php?start=<?= $start ?>&kill="+invoiceid;
	else alert("Not deleted.");
}

function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}
</script>

<?
if(!$rows) echo "<p>No invoices from $start onward.";
else  tableFrom($columns, $rows, $attributes="width=600", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
