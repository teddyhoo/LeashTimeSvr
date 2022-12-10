<? // check-paypal.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";

locked('o-');
$delimiter = ',';

	if($_REQUEST['raw']) {
		echo "<style>.quicktableheaders {font-weight:bold;}</style>";
		
	function stripQuotes($s) {
		if($s[0] != '"') return $s;
		return substr($s, 1, strlen($s)-2);
	}
	function tedTransactionsTable($file) {
		if(!$file) {
			echo "No data";
			return;
		}
		$strm = fopen($file, 'r');
		$index = 0;
		while($row = /*fgetcsv*/mygetcsv($strm)) {
			if($row[3] != 'Edward Hooban') continue;
//print_r($row);echo "<br>";			
			$dump = '"'.date('m/d/Y', strtotime($row[0])).'"'
							.',"","","","",'
							.str_replace(",", "", $row[7]) // amount
							.',TED,"","","PayPal '
							.$row[4].' ' // General Payment
							.$row[12].'"<br>';
			echo $dump;
		}
		if(FALSE) while($row = /*fgets*/mygetcsv($strm)) {
			//$row = explode(',', trim($row));
			if($row[3] != '"Edward Hooban"') continue;
			$dump = '"'.date('m/d/Y', strtotime(stripQuotes($row[0]))).'"'
							.',"","","","",'
							.stripQuotes($row[7])
							.',TED,"","","PayPal \"'
							.stripQuotes($row[4])."\\\" "
							.stripQuotes($row[12]).'"<br>';
			echo $dump;
		$index++;
		}
		fclose($strm);
	}
		
		
		tedTransactionsTable($_REQUEST['raw']);
		csvTable($_REQUEST['raw'], $extra='border=1', $style=null, $repeatHeaders=10);
		exit;
	}
	if($_FILES['upload']) {
	//echo "BANG: $dir";
	  if($failure = $_FILES['upload']['error']) {
			if($failure == 1) $uploaderror = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
			else if($failure == 2) $uploaderror = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
			else if($failure == 3) $uploaderror = "The uploaded file was only partially uploaded.";
			else if($failure == 4) $uploaderror = ''; //"No file was uploaded.";
			else if($failure == 6) $uploaderror = "Missing a temporary folder.";
			else if($failure == 7) $uploaderror = "Failed to write file to disk.";
			else if($failure == 8) $uploaderror = "File upload stopped by extension.";
		}
		else {
			$originalName = $_FILES['upload']['name'];
			$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
			if(strtoupper($extension) != 'CSV') 
				$uploaderror = "Only CSV's are allowed. ";
			if(!$uploaderror) {
				$tempDir = "{$_SESSION['bizfiledirectory']}attachments";
				ensureAttachmentDirectory("$tempDir", ($rights = 0773)); // root needs access to read and remove the file
				$randName = realpath("$tempDir")."/paypaldata.$extension";
				if(file_exists($randName)) unlink($randName);
				if(!move_uploaded_file($_FILES['upload']['tmp_name'], $randName)) {
					$uploaderror = "There was an error uploading the file. Please try again!";
				}
				else {
					chmod($randName, $rights);
					$uploadedFiles = array(array('path'=>$randName, 'file'=>$_FILES['upload']['name']));
				}
			}
	//echo "BANG: ".print_r($uploadedFiles, 1);	
		}
	}
	if(!$randName) $error = $uploaderror;
	else {
		$delimiter = strpos($randName, '.xls') ? "\t" : ',';
		$strm = fopen($randName, 'r');
		//$line0 = trim(fgetcsv($strm, 0, $delimiter)); 
		$row = fgetcsv($strm, 0, $delimiter);
		$dataHeaders = array_map('trim', $row);// consume first line (field labels)
		$titleColumn = headerIndex('Item Title') ? headerIndex('Item Title') : headerIndex('Subject');
		$noteColumn = headerIndex('Note');
//echo "Headers: ".print_r($row, 1)."<p>titleColumn: $titleColumn";
		while($row = getCSVRow($strm, 0, $delimiter)) {
			$n++;
			$dts[] = date("Y-m-d", strtotime(rowAtHeader($row, 'Date'))).' '.rowAtHeader($row, 'Time');
			$grossFloat = floatval(rowAtHeader($row, 'Gross'));
//echo "ROW: ".print_r($row, 1)."<br>";			
//echo "ROW (".rowAtHeader($row, 'Gross')." = $grossFloat: ".("$grossFloat" == rowAtHeader($row, 'Gross'))."): ".print_r($row, 1)."<br>";			

			if("$grossFloat" != rowAtHeader($row, 'Gross') || $grossFloat < 0) {
				continue;
			}
			$rowType = rowAtHeader($row, 'Type');
			if(!($title = $row[$titleColumn]) // Subject is usually "LeashTime Service (@...)"
				&& strpos(($note = $row[$noteColumn]), '@') === FALSE
				&& !in_array($rowType, array('Mobile Payment', 'General Payment'))
				&& ("$grossFloat" != rowAtHeader($row, 'Gross') || $grossFloat < 0)
				) { continue;}
//echo "ROW: ".print_r($row, 1)."<br>";			
			if(strpos($title, "(@") === FALSE && strpos($row[$noteColumn], '@') === FALSE) 
				$id = "-".rowAtHeader($row, 'Transaction ID');
			else if(strpos($title, "(@") !== FALSE) 
				$id = substr($title, 
										($start = strpos($title, "(@")+2),
										(strpos($title, ")", $start) - $start));
			else if(strpos($note, "@") !== FALSE) { // when client does not use invoice, but DOES include her client ID
				$id = substr($note, strpos($note, "@")+1);
				$end = null;
				if($id) {$zoop=$end;
					for($i=0;$i<strlen($id);$i++)
						if(!is_numeric($id[$i])) {$end = $i; break;}
					if(!$end) $end = strlen($id);
					$id = substr($id, 0, $end);
//echo "<hr>BONK! $note [$zoop] [$end]: $id<hr>";			
					$title .= "NOTE: $note";
				}
			}
			$paypal[$id][] = 
				array(
					'date'=>rowAtHeader($row, 'Date'),
					'issuedate'=>($sortDate = 
							date('Y-m-d H:i:s', 
										strtotime(
											rowAtHeader($row, 'Date')
											.' '
											.'00:00:00' //.date('H:i:s', strtotime(rowAtHeader($row, 'Time')))
											//.' '
											//.rowAtHeader($row, 'Time Zone')
											))),
					'amount'=>rowAtHeader($row, 'Gross'),
					'transactionid'=>rowAtHeader($row, 'Transaction ID'),
					'status'=>rowAtHeader($row, 'Status'),
					'payer'=>(rowAtHeader($row, 'Name') ? rowAtHeader($row, 'Name') : rowAtHeader($row, 'Type')),
					'email'=>rowAtHeader($row, 'From Email Address'),
					'type'=>rowAtHeader($row, 'Type'),
					'title'=>$title,
					'clientid'=>$id);
//echo "<p>Row: [$n] title $title<br>]n".print_r($row, 1);
			//$earliest[$id] = $sortDate;
			$entryDates[$id][] = $sortDate;
//echo "<hr>".print_r($entryDates, 1).'<br>';			
		}
		foreach($entryDates as $id=>$dates) {
			sort($dates);
			$earliest[$id] = $dates[0];
		}
		sort($dts);
//exit;
		fclose($strm);
		if($paypal) {
			$clientids = array();
			foreach($paypal as $key => $v) if($key > 0) $clientids[] = $key;
			$clients = getClientDetails($clientids, $additionalFields=null, $sorted=true);
			foreach($clients as $clientid => $client) {
				$clients[$clientid]['paypal'] = $paypal[$clientid];
				$clients[$clientid]['leashtime'] = 
					fetchAssociations($sql = "SELECT *, externalreference as transactionid
															FROM tblcredit
															WHERE clientptr = $clientid
																AND issuedate >= '{$earliest[$clientid]}'
																AND voided IS NULL
															ORDER BY issuedate DESC");
			}
		}
	}
//	

$pageTitle = "Check PayPal";
$extraHeadContent = "<style>.highlight {background:yellow;} .paidup {background:lightgrey;} .balancedue {background:white;} .client th {border-top:solid black 1px; padding-top:9px;}</style>";
include "frame.html";
// ***************************************************************************
if($error) {
	echo "<p style='color:red'>$error</p>";
}
?>
<div class='fontSize1_1em'>
<b>Step 1: </b><a href='https://business.paypal.com/merchantdata/reportHome?reportType=DLOG' target='paypal'>Download PayPal Transactions as a CSV</a>
<p>
<b>Step 2: </b>Analyze them
<p>
<form name='analysis' method='POST' enctype='multipart/form-data'>
<input type='file' name='upload' id='upload'>
<p>
<? if(!$error && $_FILES['upload']['name']) echo "Currently showing ".basename($_FILES['upload']['name'])."<p>"; ?>
<input type=submit value='Show Payments'>
<input type=button onclick='location.reload();' value='Refresh'>
<input type=button onclick='viewRaw("<?= $randName ?>");' value='View Raw'>
</form>
<hr>
<?
echo "Earliest entry: ".date('m/d/Y H:i a', strtotime($dts[0]))
			." Latest entry: ".date('m/d/Y H:i a', strtotime($dts[count($dts)-1]))
			.'<br>';
echo "<br><span style='background:black'><img src='art/add-surcharge.gif' width=15 height=15></span>
= a LeashTime payment has already been recorded for this transaction ID<br>
<img src='art/add-surcharge.gif' width=15 height=15> = Click to record this PayPal payment in LeashTime";
echo "<table width='95%'>";
//echo "<tr><td>".print_r($paypal, 1);
if($paypal) foreach($paypal as $clientid => $row) {
	if($clientid > 0) continue;
	echoUnknownRow($row);
}
if($clients)	foreach($clients as $client) {
		echoClientRows($client);
	}
echo "</table>";
?>
</div>
<script language='javascript'>
function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function openClientEditor(id) {
	openConsoleWindow('clienteditor', "<?= globalURL("client-edit.php?id=") ?>"+id, 600, 500);
}

function viewRaw(file) {
	openConsoleWindow('rawpaypal', 'check-paypal.php?raw='+file, 800,500);
}

function toggle(el) {
	//alert(el);
	$(el).toggleClass('highlight');
}

</script>

<?
require "frame-end.html";

function cmpissuedate($a, $b) { return 0 - strcmp($a['issuedate'], $b['issuedate']);}
	

function echoUnknownRow(&$row) {
	//echo "<tr><td colspan=5>".print_r($row, 1)."</td></tr>";
	require_once "invoice-fns.php";
	$row = $row[0];
	$email = $row['email'];
	$payer = mysql_real_escape_string($row['payer']);
	$payer = $payer ? $payer : print_r($row,1);
//if(mattOnlyTEST()) echo print_r($email,1)."<br>";	
	$possible = fetchAssociations(
			"SELECT CONCAT_WS(' ', fname, lname) as name, clientid 
				FROM tblclient 
				WHERE active=1 AND (fname = '$payer' OR lname = '$payer' OR email = '$email')", 1);
	foreach($possible as $client) $clients[$client['clientid']] = 
		'Maybe: '.fauxLink("{$client['name']} ($email) (bal: ".getAccountBalance($client['clientid'], $includeCredits=true, $allBillables=false).")?",
							"openClientEditor({$client['clientid']})", 'noecho', 'edit client in separate window');

	echo "<tr><td colspan=5 style='color:red;font-weight:bold;padding-top:7px;'>No LT Client ID supplied for: {$payer} "
				."<span style='color:grey;'><br>{$clients[$client['clientid']]}</span></td></tr>";
				//."<span style='color:grey;'><br>".join(', ', (array)$clients)."</span></td></tr>";
				
	$entered = fetchFirstAssoc($sql = "SELECT creditid, issuedate, clientptr, CONCAT_WS(' ', fname, lname) as client
												FROM tblcredit
												LEFT JOIN tblclient ON clientid = clientptr
												WHERE externalreference = '{$row['transactionid']}' LIMIT 1");
	if($entered) {
		$transactionidNote = "title='entered on {$entered['issuedate']}'";
		$transactionidStyle = "style='font-weight:bold;text-decoration:underline;'";
		$clientLink = 
			" <a target='ltclient' href='client-edit.php?tab=account&id={$entered['clientptr']}'>"
			."{$entered['client']} (@{$entered['clientptr']})</a>";
	}
				
	echo "<tr><td>{$row['date']}</td><td>PayPal</td><td>{$row['amount']}</td><td colspan=2 $transactionidStyle $transactionidNote>{$row['transactionid']}$clientLink</td></tr>";
}

function echoClientRows(&$client) {
//echo "client: ".print_r($client, 1)."<br>";		
	require_once "invoice-fns.php";
	$balanceDollars = dollarAmount($balance = getAccountBalance($client['clientid'], $includeCredits=true));
	$balanceclass = "class='".($balance <= 0 ? 'paidup' : 'balancedue')."'";
	$clientclass = "class='".($balance <= 0 ? 'paidup' : 'balancedue')." client'";
	echo "<tr $clientclass><th colspan=5>{$client['clientname']} ({$client['clientid']}) Balance: $balanceDollars</th></tr>";
	echo "<tr $balanceclass><th>Date</th><th>Source</th><th>Amount</th><th>Transaction ID</th><th>Note</th></tr>";
	$allRows = array_merge($client['paypal'], $client['leashtime']);
	usort($allRows, 'cmpissuedate');
//echo "<tr><td colspan=4>".print_r($allRows, 1);	
	foreach($allRows as $row) 
		$transactionidCounts[$row['transactionid']] += 1;
	foreach($allRows as $row) {
		if(!$row['date']) $row['date'] = date('n/j/Y', strtotime($row['issuedate']));
		$ccpayment = strpos($row['externalreference'], 'CC:') === FALSE ? null : ' [CC payment]';
		$style = $row['creditid'] ? 
			(!$ccpayment ? 'style="color:blue"' :  'style="color:gray"') : '';
		echo "<tr $balanceclass $style><td>{$row['date']}</td><td>";
		echo $row['creditid'] ? 'LeashTime'.($row['payment'] ? $ccpayment : ' credit') : 'PayPal';
		$transactionid = $row['transactionid'];
		if(!$row['creditid']) { // PayPal
			$payDate = substr($row['issuedate'], 0 , strlen('0000-00-00'));
			$url = "payment-edit.php?client={$client['clientid']}&amount={$row['amount']}&issuedate=$payDate"
							."&externalreference=$transactionid&sourcereference=PayPal&payby=paypal&reason=Payment+made";
			$buttonbg = $transactionidCounts[$transactionid] > 1 ? "style='background:black'" : '';
			$buttonIMG = "<img title='Click to record this PayPal payment in LeashTime' src='art/add-surcharge.gif' width=15 height=15
														onclick='openConsoleWindow(\"paypalpaymenteditor\", \"$url\",600,500)'>";
			if($row['status'] != 'Completed') $buttonIMG = "<span class='warning'>{$row['status']}</span>";
			$transactionid = "$transactionid <span $buttonbg>$buttonIMG</span>";
		}
		else if(strpos(strtoupper("{$row['sourcereference']}"), 'PAYPAL') !== FALSE)
			$transactionid = "$transactionid <span title='Source marked as PayPal'>&#10004;</span>"; // add check mark

		if($row['creditid'] && $row['payment']) {
			$url = "payment-edit.php?id={$row['creditid']}";
			$transactionid = fauxLink($transactionid, "openConsoleWindow(\"paymenteditor\", \"$url\", 600, 600)", 1, 'Edit payment');
		}
			
		echo "</td><td>{$row['amount']}</td><td onclick='toggle(this)'>$transactionid</td><td>";
		echo $row['creditid'] ? $row['reason'] : "({$row['payer']}) {$row['title']}";
		echo "</td><tr>";		
	}
}

function ensureAttachmentDirectory($dir, $rights=0765) {
  if(file_exists($dir)) return true;
  ensureAttachmentDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, $rights);
}

function headerIndex($header) {
	global $dataHeaders;
	return array_search($header, $dataHeaders);
}

function rowAtHeader($row, $header) {
	return trim($row[ headerIndex($header)]);
}

function getCSVRow($strm) {
	return $_POST['mutiline'] ? mygetcsv($strm) : fgetcsv($strm);
}

function mygetcsv($strm) {  // handles EOLS inside quotes, as long as quotes balance
	global $delimiter;
	$quoteCount = 0;
	$totalCSV = array();
	do {
		$line = fgets($strm);
		for($i=0; $i < strlen($line); $i++) if($line[$i] == '"') $quoteCount++;
		$sstrm = fopen("data://text/plain,$line" , 'r');
		$csv = fgetcsv($sstrm, 0, $delimiter);
		if(!$totalCSV) $totalCSV = $csv;
		else {
			$totalCSV[count($totalCSV)-1] .= "\n".substr($csv[0], 0, strlen($csv[0])-1);
			for($i=1; $i < count($csv); $i++) $totalCSV[] = $csv[$i];
		}
	}
	while($quoteCount % 2 == 1);
	return $totalCSV;
}
