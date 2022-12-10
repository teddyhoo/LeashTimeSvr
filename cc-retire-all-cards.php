<? // cc-retire-all-cards.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "cc-processing-fns.php";

locked('o-'); // add *cm and the login challenge if this is published

if($_POST) {
	foreach($_POST as $key=>$unused) {
		if(strpos($key, 'gateway_') === 0) {
			$gateway = substr($key, strlen('gateway_'));
			if(strpos($gateway, 'Authorize_net') !== FALSE)  $gateway = str_replace('Authorize_net', 'Authorize.net', $gateway);
			//echo "$gateway is to be deactivated.<p>";
			if($gateway == 'UnknownNotSolveras') $conditions[] = "(vaultid IS NULL AND gateway IS NULL)";
			else if($gateway == 'Solveras') $conditions[] = "(vaultid IS NOT NULL)";
			else if($gateway) $conditions[] = "(gateway = '$gateway')";
		}
	}
	updateTable('tblcreditcard', array('active'=>'0'), join(' OR ', $conditions), 1);
	echo "Deactivated ".mysql_affected_rows()." credit cards.<hr>";
	//echo "updateTable('tblcreditcard', array('active'=>'0'), ".join(' OR ', $conditions).", 1)<br>";
}

$counts = fetchKeyValuePairs(
	"SELECT IFNULL(gateway, 
						if(vaultid IS NULL, 'UnknownNotSolveras', 'Solveras')), count(*) 
					FROM tblcreditcard WHERE active = 1 GROUP BY gateway");

if(!$counts) {
	echo "No active cards found.";
	exit;
}
?>
Cards to deactivate in <b><?= $_SESSION['bizname'] ?></b>:
<form name='deactivator' method='POST'>
<? 
$counts['Sage'] += $counts['SAGE'];
if(!$counts['Sage']) unset($counts['Sage']);
foreach($counts as $gateway => $count) {
	$label = $gateway == 'UnknownNotSolveras' ? 'Unknown(but not Solveras)' : $gateway;
	$gatewayStrings[$gateway] = $label;
	echo "<br><input type=checkbox id='gateway_$gateway' name='gateway_$gateway'> $label: $count";
}
foreach((array)$gatewayStrings as $gateway => $label) 
	$gatewayStrings[$gateway] = "gateway_".str_replace('.','_', $gateway).":\"$label\"";

?><p>
<input type='button' value='Deactivate Cards' onClick='deactivate()'>
</form>
<p><a href='index.php'>Home</a>
<script language='javascript'>
var gateways = {<?= join(',', $gatewayStrings) ?>};
function deactivate() {
	var els = document.deactivator.elements;
	var found= new Array();
	for(var i=0; i<els.length;i++) {
		if(els[i].id && els[i].id.indexOf('gateway_') == 0 && els[i].checked)
			found[found.length] = gateways[els[i].id.replace('.', '_')];
	}
	if(found.length == 0) {
		alert('Please select cards to deactivate.');
	}
	else if(confirm('Deactivate all of these cards: '+found.join(' and ')+'?'))
		document.deactivator.submit();
}
</script>
