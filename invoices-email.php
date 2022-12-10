<? //invoices-email.php
$listInvoices=1;
$tab = 'email';
include "invoices-top.php";

// for each client collect:
//	last invoice, if any, or array(clientid)
$clientIds = array();

if($linitial && ($linitial != 'ALL'))
	foreach($clientDetails as $k => $client)
		if(strpos(strtoupper($client['lname']), strtoupper($linitial)) === 0)
			$clientIds[] = $k;
$filter = $clientIds ? "WHERE clientptr IN (".join(',', $clientIds).")" : '';
$invoices = fetchAssociationsKeyedBy("SELECT * FROM tblinvoice $filter ORDER BY date, invoiceid", 'clientptr');
foreach(array_keys($clientDetails) as $clientid)
	if(!isset($invoices[$clientid])) $invoices[$clientid] = array('clientptr'=>$clientid);

?>
<style>
.highlightedinitial {background:darkblue;color:white;font-weight:bold;flow:inline;padding-left:5px;padding-right:5px;}
</style>
<?
echo "<p><div class='bluebar'>Invoices to Email</div></p>";
echo "<p align=center>";
if(!isset($linitial) || (isset($linitial) && $linitial == 'ALL')) echo "<span class='highlightedinitial'>All Clients</span> - ";
else echo " <a class='fauxlink' onClick='changeAsOfDate(\"ALL\")'>All Clients</a> - ";

for($i = ord('A'); $i <= ord('Z'); $i++) {
  $c = chr($i);
  //echo " <a href=client-picker.php?linitial=$c&target=$target>$c</a>";
  if(isset($linitial) && $linitial == $c) echo "<span class='highlightedinitial'>$c</span>";
  else echo " <a class='fauxlink' onClick='changeAsOfDate(\"$c\")'>$c</a>";
  if($c != 'Z') echo " - ";
}



echo "<div style='position:relative;float:right';>";
echoButton('','Generate & Email Invoices to Selected Clients','emailSelectedInvoices()');
echo "</div>";
echo fauxLink('Select All', "selectAll(\"invoicestoEmail\", 1)", 'Select all current invoices for printing.');
echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>";
echo fauxLink('Deselect All', "selectAll(\"invoicestoEmail\", 0)", 'Clear all current invoice selections.');
echo "<p>";
$nullChoice = 'email';
$sortedInvoices = array();
foreach($clientDetails as $clientid => $client) {
	if($linitial && ($linitial != 'ALL') && strpos(strtoupper($client['lname']), strtoupper($linitial)) !== 0) continue;
	$clientInvoice = $invoices[$clientid];
	if(($showAllClients
			  || (($clientInvoice && $clientInvoice['balancedue'] && $clientInvoice['balancedue'] > 0) 
				 		|| ($uninvoicedCharges[$clientid] && $uninvoicedCharges[$clientid] > 0)
				 		|| $incompleteJobCounts[$clientid] > 0))
		 && ($client['invoiceby'] == 'email' || (!$client['invoiceby'] && $nullChoice == 'email'))) {
		$sortedInvoices[$clientid] = $clientInvoice;
	}
}

invoiceListTable($sortedInvoices, $throughDateInt, null, 'invoicestoEmail', $uninvoicedCharges, 'checkEmail');

// ***************************************************************************
include "invoices-bottom.php";