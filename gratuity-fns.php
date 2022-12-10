<? // gratuity-fns.php
require_once "gui-fns.php";
require_once "provider-fns.php";

function createGratuities($data, $clientptr, $issuedate, $paymentptr=null) {
	foreach($data as $key => $value) {
		if(strpos($key, 'dollar_') === 0 && $value) {
			$index = substr($key, strlen('dollar_'));
			$gratuity = array('paymentptr'=>$paymentptr, 'tipnote'=>$data['tipnote'], 'clientptr'=>$clientptr,
												'issuedate'=>$issuedate, 'amount'=>$value, 'providerptr'=>$data["gratuityProvider_$index"]);
			insertTable('tblgratuity', $gratuity, 1);

			// notify sitter
			makeGratuityNoticeMemo($gratuity); // gated by enableSitterTipMemos
		}
	}
}

function makeGratuityNoticeMemo($gratuity) {
	//$acceptAlways = $_SESSION['preferences']['enableSitterTipMemos']; //mattOnlyTEST() && dbTEST('dogslife');
	require_once "preference-fns.php";
	// for now, this is not per-sitter, but...
	$notifyOfGratuities = getProviderPreference($gratuity['providerptr'], 'enableSitterTipMemos'); 
	if(!$notifyOfGratuities) return;
	// notify sitter
	require_once "provider-memo-fns.php";
	require_once "preference-fns.php";
	require_once "prov-schedule-fns.php";
	$clientptr = $gratuity['clientptr'];
	$displayMode = getPreference('provuisched_client');
	$displayMode = $displayMode ? $displayMode : 'fullname/pets';	
	$clientLabel = clientDisplayName($clientptr, $displayMode);
	$note = "Gratuity received: ".dollarAmount($gratuity['amount'])." from $clientLabel.";
	if($gratuity['tipnote']) $note .= "<br>Note: {$gratuity['tipnote']}";
	makeProviderMemo($gratuity['providerptr'], "tipnotice|$note", $clientptr, $preprocess=1, $acceptAlways);
}

function gratuityValidationArgs($paymentptrOrIssueDate) {
	if($paymentptrOrIssueDate) return '"","",""';  // no validation necessary since only note will be saved
	
	$args[] = "'gratuity','', 'R','gratuity','', 'UNSIGNEDFLOAT', nonZeroGratuityMessage(), '', 'MESSAGE', noProvidersSelected(), '', 'MESSAGE',"
						."incorrectAllocationMessage(), '', 'MESSAGE'";
	for($i=1; $i <= 5; $i++)
		$args[] = "allOrNothing($i), '', 'MESSAGE', 'dollar_$i', '', 'UNSIGNEDFLOAT'";
		
	$args[] = "'amount', 'gratuity', '>='"; 
		
	return join(', ', $args);
}

function gratuitySection($clientid, $totalPaymentAmountElementId, $tipGroup=null, $date=null) {
	// use POSTed values for INPUTs, if available, for 'back' action
	$currencyMark = getCurrencyMark();
	$tableOnLoad = $tipGroup ? '' : "onLoad='totalPaymentField = \"$totalPaymentAmountElementId\";'";
	echo "<table $tableOnLoad>";
	echo "<tr><td id='status' colspan=2></td></tr>";
	if($date) labelRow('Date:', '', shortDate($date), null, null, null, null, 'raw');
	if($tipGroup) labelRow('Gratuity Amount:', '', dollarAmount($tipGroup['total']), null, null, null, null, 'raw');
	else inputRow('Gratuity Amount:', 'gratuity', $_POST['gratuity'], null, 'dollarinput', null,  null, 'updateGratuityAmounts(this)');
	$gratuityTotal = $tipGroup['total'] ? $tipGroup['total'] : $_POST['gratuity'];
	hiddenElement('gratuityTotal', $gratuityTotal);
	$activeProviders = availableProviderSelectElementOptions($clientid, $date=null, '-- No Selection --', $noZIPSection=true); 
	$options = array('-- No Selection --'=>0, '-- Unassigned --'=>-1);
	$keys = array_keys($activeProviders);
	foreach($activeProviders as $key=>$val)
		if($val) $options[$key] = $val;
	$activeProviders = $options;
/*	$activeProviders = getActiveProviderSelections(); */
	if($tipGroup) {
		labelRow('Paid to:', '', '');
		foreach($tipGroup['providers'] as $provider => $amount) {
			labelRow("$provider", '', 
								dollarAmount($amount)
								.' ('.percentageDisplay($amount / $tipGroup['total'] * 100).')'
								." {$tipGroup['payouts'][$provider]}",
								null, null, null, null, 'raw');
		}
		//labelRow("Note:", '', $tipGroup['tipnote']);
	}
	else {
		
		for($i=1;$i <= 5; $i++) {
			echo "<tr><td>";
			gratuityProviderSelectElement($i, $clientid, $activeProviders, $_POST["gratuityProvider_$i"]);
			echo "</td><td><b>$currencyMark</b>";
			labeledInput('', "dollar_$i", $_POST["dollar_$i"], null, 'dollarinput', 'updateGratuityAmounts(this)');
			echo "<span id='info_$i'></span></td>";
			echo "</tr>\n";
		}
		//countdownInputRow(45, 'Note:', 'tipnote', '', '', 'Input45Chars', null, null, null, 'afterlabel');
	}
	$tipnote = $tipGroup['tipnote'] ? $tipGroup['tipnote'] : $_POST['tipnote'];
	countdownInputRow(45, 'Note:', 'tipnote', $tipnote, '', 'Input45Chars', null, null, null, 'afterlabel');
	echo "</table>";
	echo "<div id='gratuitySummary'></div>";
	
}

function percentageDisplay($val) {
	return (fmod($val, 10) ? sprintf("%.2f", $val) : $val).'%'; // have to use fmod for modulus
}

function gratuityProviderSelectElement($index, $clientid, $activeProviders, $choice) {
	selectElement('Sitter', "gratuityProvider_$index", $choice, $activeProviders, $onChange="setGratuityProvider(this)");
/*	$pastProviders = fetchCol0("SELECT DISTINCT providerptr FROM tblappointment WHERE clientptr = $clientid AND canceled IS NULL");
	$preferredSet = array();
	foreach($activeProviders as $name => $id) {
		if(in_array($id, $pastProviders)) {
			$preferredSet[$name] = $id;
			unset($activeProviders[$name]);
		}
	}
	$options = array("\n\t<option value='0' ".(!$choice ? 'SELECTED' : '').">-- No Selection --</option>\n");
	$options[] = "\n\t<option value='-1' ".($choice == -1 ? 'SELECTED' : '')." style='color:red;'>-- Unassigned --</option>\n";
	if($preferredSet) {
		$options[] = "<OPTGROUP label='Past Sitters' style='font-weight:bold;'>\n";
		foreach($preferredSet as $optLabel => $optValue) {
				$checked = $optValue == $choice ? 'SELECTED' : '';
				$optValue = safeValue($optValue);
				$options[] = "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
		}		
		$options[] = "</OPTGROUP>\n";
	}
	$options[] = "<OPTGROUP label='Other Sitters' style='font-weight:bold;'>\n";
	foreach($activeProviders as $optLabel => $optValue) {
			$checked = $optValue == $choice ? 'SELECTED' : '';
			$optValue = safeValue($optValue);
			$options[] = "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
	}
	$options[] = "</OPTGROUP>\n";
	
	selectElement('Sitter', "gratuityProvider_$index", $choice, join("\n", $options), $onChange="setGratuityProvider(this)");
*/	
}

function getIndependentGroup($client, $issueDateTime) {
	$gratuities = fetchAssociations("SELECT * FROM tblgratuity WHERE clientptr = $client AND issuedate = '$issueDateTime'");
	if(!$gratuities) return null;
	return makeTipGroup($gratuities);
}


function getPaymentTipGroup($paymentptr) {
	$gratuities = fetchAssociations("SELECT * FROM tblgratuity WHERE paymentptr = $paymentptr");
	if(!$gratuities) return null;
	return makeTipGroup($gratuities);
}

function makeTipGroup($gratuities) {
	$providers = getProviderShortNames();
	$providers[0] = '--Unassigned--';
	foreach($gratuities as $gratuity) {
		if(!$tipGroup) {
			$tipGroup = array('clientptr' => $gratuity['clientptr'],
												'issuedate' => $gratuity['issuedate'],
												'paymentptr' => $gratuity['paymentptr'],
												'tipnote' => $gratuity['tipnote'],
												'total' => 0);
		}
		$tipGroup['providers'][$providers[$gratuity['providerptr']]] = $gratuity['amount'];
		$hasBeenPaid = fetchRow0Col0($sql = 
				"SELECT paid 
					FROM tblpayable 
					WHERE itemtable = 'tblgratuity' AND itemptr = {$gratuity['gratuityid']}
					LIMIT 1");
		$tipGroup['payouts'][$providers[$gratuity['providerptr']]] = $hasBeenPaid != 0.0 ? 'PAID OUT' : '';
		$tipGroup['total'] += $gratuity['amount'];
	}
	return $tipGroup;
}
	

function gratuityListTable($gratuities, $oneClient=false) {
	if(!$gratuities) {
		echo "No gratuities found.";
		return;
	}
	$clientIds = array();
	foreach($gratuities as $gratuity) $clientIds[] = $gratuity['clientptr'];
	$clients = getClientDetails($clientIds);
	$columns = explodePairsLine('issuedate|Date||amount|Amount||providers|Sitters||client|Client||tipnote|Note');
	$colSorts = $oneClient ? array('date'=>null) : array();
	if($oneClient) {
		unset($columns['client']);
	}
	
	$providers = getProviderShortNames();
	$providers[0] = '--Unassigned--';
	$lastClient = 0;
	$lastIssuedate = 0;
	$lastPaymentptr = 0;
	$tipGroups = array();
	foreach($gratuities as $gratuity) {
		if($lastClient != $gratuity['clientptr'] ||  $lastIssuedate != $gratuity['issuedate'] || $lastPaymentptr != $gratuity['paymentptr']) {
			if($lastClient) $tipGroups[] = $tipGroup;
			$tipGroup = array('clientptr' => $gratuity['clientptr'],
												'issuedate' => $gratuity['issuedate'],
												'paymentptr' => $gratuity['paymentptr'],
												'tipnote' => $gratuity['tipnote'],
												'total' => 0);
			$lastClient = $gratuity['clientptr'];
			$lastIssuedate = $gratuity['issuedate'];
			$lastPaymentptr = $gratuity['paymentptr'];
		}
		$tipGroup['providers'][] = 	$providers[$gratuity['providerptr']];
		$tipGroup['total'] += $gratuity['amount'];
	}
	if($tipGroup) $tipGroups[] = $tipGroup;
	
	$colClasses = array('amount'=>'dollaramountcell');
	$rows = array();
	foreach($tipGroups as $tipGroup) {
		$row = array();
		$row['issuedate'] = shortDate(strtotime($tipGroup['issuedate']));
		$row['client'] = fauxLink($clients[$tipGroup['clientptr']]['clientname'], "viewClient({$tipGroup['clientptr']})", 'View this client', 1);
		$row['amount'] = gratuityLink($tipGroup);
		// if refund for a payment, link to payment here
		$row['tipnote'] = $tipGroup['tipnote'];  
		$row['providers'] = count($tipGroup['providers']) > 2 
			? (count($tipGroup['providers'])).' providers'
			: join(', ', $tipGroup['providers']); 
		
		$rows[] = $row;
	}
	//$colClasses['amount'] = 'amountcolumn';
	$colClasses = array('amount'=>'dollaramountheader');
	//echo "<style>.amountcolumn {width: 150px;}</style>\n";
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,null, $colClasses, 'sortInvoices');
}

function gratuityLink($gratuity) {	
	if(!$gratuity['paymentptr']) {
		//$time = strtotime($gratuity['issuedate']);
		return fauxLink(dollarAmount($gratuity['total']), 
			"editGratuity({$gratuity['clientptr']}, escape(\"{$gratuity['issuedate']}\"))", 1, "View this gratuity.");
	}
	else return fauxLink(dollarAmount($gratuity['total']), "editCredit({$gratuity['paymentptr']}, 1)", 1, "View this gratuity and its payment");
}



// ##############################################
if($_REQUEST['test']) {
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
extract(extractVars('client', $_REQUEST));

// Verify login information here
locked('o-');

$windowTitle = 'Gratuity Editor';
require "frame-bannerless.php";

gratuitySection($client, $totalPaymentAmountElementId=null);
?>

<script language='javascript' src='gratuity-fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<?
}
?>