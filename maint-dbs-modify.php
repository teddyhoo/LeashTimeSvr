<? // maint-dbs-modify.php
// use this script by hand to modify all LT biz databases
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
require_once "gui-fns.php";


// exit;



$locked = locked('z-');
$scriptStart = microtime(1);
$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz ", 'db'); // WHERE activebiz=1
foreach($bizzes as $biz) {
	if($biz['db'] == 'leashtimecustomers') $ltBiz = $biz;
	else $allBizzesLeashTimeFirst[] = $biz;
}

// GOLD STARS
$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
$clientsByBizptr = array_flip($clients);
/*$trials = array_merge($clients);
$deadtrials = array_merge($clients);
$greystars = array_merge($clients);
$goldstars = array_merge($clients);*/
$trials = array($clients);
$deadtrials = array();
$greystars = array();
$goldstars = array();
foreach($clients as $ltclientid => $garagegatecode) {
	$goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
	if($goldstar) $goldstars[$ltclientid] = $garagegatecode;
}

foreach($clients as $ltclientid => $garagegatecode) {
	$trial = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '1|%'");
	if($trial) $trials[$ltclientid] = $garagegatecode;
}

foreach($clients as $ltclientid => $garagegatecode) {
	$deadtrial = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '8|%'");
	if($deadtrial) $deadtrials[$ltclientid] = $garagegatecode;
}

foreach($clients as $ltclientid => $garagegatecode) {
	$greystar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '21|%'");
	if($greystar) $greystars[$ltclientid] = $garagegatecode;
}

// FORMER CLIENTS greystar(21), deadlead(8)
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
foreach($clients as $ltclientid => $garagegatecode) {
	$former = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' 
													AND (value like '8|%' OR value like '21|%')");
	if(!$former) unset($clients[$ltclientid]);
}
$formerclients = $clients; // ltclientid => bizid

require "common/init_db_common.php";
// END GOLD STARS



function cmpDb($a, $b) {return strcmp($a['db'], $b['db']);}

usort($allBizzesLeashTimeFirst, 'cmpDb');

$allBizzesLeashTimeFirst = array_merge(array('leashtimecustomers'=>$ltBiz), $allBizzesLeashTimeFirst);

foreach($allBizzesLeashTimeFirst as $bizCount => $biz) {
	//echo "<font color=gray>$bizCount / ".(count($allBizzesLeashTimeFirst)-2)."</font><br>";
	if($bizCount == count($allBizzesLeashTimeFirst)-2) $lastBiz = true;  // why "2"?
	if(!in_array($biz['db'], $databases)) {
		//echo "<br><font color=gray>DB: {$biz['db']} not found.<br></font>";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysqli_select_db($db);
	if(mysqli_error()) echo mysqli_error();
	$tables = fetchCol0("SHOW TABLES");
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$ltclientid = $clientsByBizptr[$bizptr];
	
	// #########################################################################################################
	
	/*$result = mysqli_query("SELECT description from relinvoiceitem limit 1");
	$field = mysqli_fetch_field($result, 0);
		echo "$db<br><pre>
	max_length:   $field->max_length
	name:         $field->name
	not_null:     $field->not_null
	type:         $field->type
</pre><p>";*/

//TOP

	if(TRUE) { // Question: Who uses ACH?
		//echo "<p><b>\"$bizName\" ($db) </b> goldstars: [{$goldstars[$ltclientid]}] activebiz: [{$biz['activebiz']}]<br>";
		if(!$goldstars[$ltclientid]) continue;
		if(!$biz['activebiz']) continue;
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('gatewayOfferACH') LIMIT 1");
		if($prefs) {
			$activeACHClientsCount =
				fetchRow0Col0(
					"SELECT count(*) 
						FROM tblecheckacct e
						LEFT JOIN tblclient c ON clientid = clientptr
						WHERE  e.active = 1 AND c.active = 1 AND primarypaysource = 1");
			echo "<p><b>\"$bizName\" ($db) </b> offerACHOption=true active clients with ACH: $activeACHClientsCount<br>";
			//echo "<hr>";
		}

	}
	
	if(FALSE) { // Question: Who uses the sittersPaidHourly?
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('sittersPaidHourly') LIMIT 1");
		if($prefs) {
			echo "<p><b>\"$bizName\" ($db)</b><br>";
			foreach($prefs as $k=>$v) 
				echo "$k: $v<br>";
			echo "<hr>";
		}
	}
	
	if(FALSE) { // Question: How many emails sent through LT's SMTP server yesterday?
		if($host = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'smtphost' LIMIT 1")) {
			echo "<p><b>\"$bizName\" ($db)</b> : $host<br>";
			continue;
		}
		$emails = fetchRow0Col0("SELECT COUNT(*) FROM tblmessage
															WHERE datetime >= '2021-04-27 00:00:00' AND datetime <= '2021-04-28 00:00:00'");
		if($emails) {
			echo "<p><b>\"$bizName\" ($db)</b> : $emails<br>";
			$total += $emails;
			$bizcount += 1;
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $total, $bizcount;
				echo "TOTAL ({$bizcount}) {$total}<p>";
			}
		}

	}
	
	if(FALSE) { // Question: Who uses the Unassigned Visits Schedule email?
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('unassignedemail', 'unassigneddailyvisitsemail','unassignedweeklyvisitsemail') LIMIT 1");
		if($prefs) {
			echo "<p><b>\"$bizName\" ($db)</b><br>";
			foreach($prefs as $k=>$v) 
				echo "$k: $v<br>";
			echo "<hr>";
		}
	}
	
	if(FALSE) { // erase matt's postal address and phone numbers
		// tblclientrequest tblcreditcardinfo tblecheckacctinfo tblclient tblclinic tblprovider 
		// tblvet tblcreditcardinfo tblecheckacctinfo tblecheckacct
		$results = array();
		$targetAddress = '606 birch st';
		
		if(0 && fetchRow0Col0("SELECT address FROM tblclientrequest WHERE address IS NOT NULL LIMIT 1")) {
			$sql = "SELECT address FROM tblclientrequest WHERE address LIKE '%$targetAddress%'";
			$reqAdds = fetchCol0($sql);
			if($reqAdds) {
				$results['tblclientrequest.address'] = $reqAdds;
			}
		}
		
		$sql = "SELECT street1 FROM tblclientrequest WHERE street1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblclientrequest.street1'] = $reqAdds;
		}
		
		$sql = "SELECT x_address FROM tblcreditcardinfo WHERE x_address LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblcreditcardinfo.x_address'] = $reqAdds;
		}
		
		$sql = "SELECT x_address FROM tblecheckacctinfo WHERE x_address LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblecheckacctinfo.x_address'] = $reqAdds;
		}
		
		$sql = "SELECT street1 FROM tblclient WHERE street1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblclient.street1'] = $reqAdds;
		}
		
		$sql = "SELECT mailstreet1 FROM tblclient WHERE mailstreet1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblclient.mailstreet1'] = $reqAdds;
		}
		
		$sql = "SELECT street1 FROM tblclinic WHERE street1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblclinic.street1'] = $reqAdds;
		}
		
		$sql = "SELECT street1 FROM tblprovider WHERE street1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblprovider.street1'] = $reqAdds;
		}
		
		$sql = "SELECT street1 FROM tblvet WHERE street1 LIKE '%$targetAddress%'";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblvet.street1'] = $reqAdds;
		}
		
		$sql = "SELECT CONCAT(x_first_name, ' ', x_last_name, ' >  ', x_address) as name FROM tblcreditcardinfo WHERE x_first_name LIKE '%matt%' AND x_last_name LIKE '%lindenfelser%' ";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblcreditcardinfo.name'] = $reqAdds;
		}
		
		$sql = "SELECT CONCAT(acctname) as name FROM tblecheckacct WHERE acctname LIKE '%matt%lindenfelser%' ";
		$reqAdds = fetchCol0($sql);
		if($reqAdds) {
			$results['tblecheckacct.acctname'] = $reqAdds;
		}
		
		$phones = explode(',', '703-242-1964,703-537-5055,571-328-6550,(703) 242-1964,(703) 537-5055,(571) 328-6550');
		$inPhones = "IN ('". join("', '", $phones)."')";
		$idflds = explode(',', 'clientid,providerid');
		foreach(explode(',', 'tblclient,tblprovider') as $i => $tab) {
			foreach(explode(',', 'homephone,cellphone,workphone,cellphone2') as $k) {
				if($k == 'cellphone2' && $tab != 'tblclient') continue;
				$sql = "SELECT CONCAT_WS(' ', {$idflds[$i]}, fname, lname, $k) FROM $tab WHERE $k $inPhones";
				$reqAdds = fetchCol0($sql);
				foreach($reqAdds as $a)
					$results["$tab.$k"][] = $a;
			}
		}
		$sql = "SELECT CONCAT_WS(' ', 'requestid', phone) FROM tblclientrequest WHERE phone $inPhones";
		$reqAdds = fetchCol0($sql);
		foreach($reqAdds as $a) {
			$results["tblclientrequest.phone"][] = $a;
		}

		$idflds = explode(',', 'clinicid,vetid');
		foreach(explode(',', 'tblclinic,tblvet') as $i => $tab) {
			foreach(explode(',', 'homephone,cellphone,officephone,fax') as $k) {
				$sql = "SELECT CONCAT_WS(' ', {$idflds[$i]}, fname, lname, $k) FROM $tab WHERE $k $inPhones";
				$reqAdds = fetchCol0($sql);
				foreach($reqAdds as $a)
					$results["$tab.$k"][] = $a;
			}
		}
		
		foreach(explode(',', 'homephone,cellphone,workphone') as $k) {
			$sql = "SELECT CONCAT_WS(' ', contactid, name) FROM tblcontact WHERE $k $inPhones";
			$reqAdds = fetchCol0($sql);
			foreach($reqAdds as $a)
				$results["tblcontact.$k"][] = $a;
		}
		
		$sql = "SELECT CONCAT_WS(' ', ccptr, x_first_name, x_last_name, x_phone) FROM tblcreditcardinfo WHERE x_phone $inPhones";
		$reqAdds = fetchCol0($sql);
		foreach($reqAdds as $a)
			$results["tblcreditcardinfo.$k"][] = $a;

		$sql = "SELECT CONCAT_WS(' ', acctptr,  x_phone) FROM tblecheckacctinfo WHERE x_phone $inPhones";
		$reqAdds = fetchCol0($sql);
		foreach($reqAdds as $a)
			$results["tblcreditcardinfo.$k"][] = $a;

		
		if($results) {
			echo "<p><b>\"$bizName\" ($db)</b><br>";
			foreach($results as $k=>$lst) 
				foreach($lst as $v) echo "$k: $v<br>";
			echo "<hr>";
		}
	}

	if(FALSE) { // DONE! erase matt's email address
		// relstaffnotification,tblclient,tblclinic,tblprovider,tblvet
		$results = array();
		$targetEmails = explode(',', 'matt@leashtime.com,thule@aol.com'); // 
		
		//$inPhrase = "IN ('".join("', ", $targetEmails)."')";
		foreach($targetEmails as $email) {
			$inPhrase = "IN ('$email')";
			
			$clients = array();
			$sql = "SELECT clientid FROM tblclient WHERE email $inPhrase";
			$clients = fetchCol0($sql);
			$sql = "SELECT clientid FROM tblclient WHERE email2 $inPhrase";
			$clients = array_merge((array)$clients, fetchCol0($sql));
			if($clients) {
				$results['clients.'.$email] = array_unique($clients);
				updateTable('tblclient', array('email'=>"lhajkhgshgskjd@gshgskj.com"), "email $inPhrase");
				echo "$db : tblclient updates: ".leashtime_affected_rows()."<br>";
				updateTable('tblclient', array('email2'=>"lhajkhgshgskjd@gshgskj.com"), "email2 $inPhrase");
				echo "$db : tblclient updates: ".leashtime_affected_rows()."<br>";
			}

			$providers = array();
			$sql = "SELECT providerid FROM tblprovider WHERE email $inPhrase";
			$providers = fetchCol0($sql);
			if($providers) $results['providers.'.$email] = array_unique($providers);
			if($providers) {
				$results['clients.'.$email] = array_unique($clients);
				updateTable('tblprovider', array('email'=>"lhajkhgshgskjd@gshgskj.com"), "email $inPhrase");
				echo "$db : tblprovider updates: ".leashtime_affected_rows()."<br>";
			}

			$notes = array();
			$sql = "SELECT * FROM relstaffnotification WHERE email $inPhrase";
			$notes = fetchCol0($sql);
			if($notes) {
				$results['notes.'.$email] = array_unique($notes);
				updateTable('relstaffnotification', array('email'=>"lhajkhgshgskjd@gshgskj.com"), "email $inPhrase");
				echo "$db : relstaffnotification updates: ".leashtime_affected_rows()."<br>";
			}


			$clinics = array();
			$sql = "SELECT * FROM tblclinic WHERE email $inPhrase";
			$clinics = fetchCol0($sql);
			if($clinics) {
				$results['clinics.'.$email] = $clinics;
				updateTable('tblclinic', array('email'=>"lhajkhgshgskjd@gshgskj.com"), "email $inPhrase");
				echo "$db : tblclinic updates: ".leashtime_affected_rows()."<br>";
			}

			$vets = array();
			$sql = "SELECT * FROM tblvet WHERE email $inPhrase";
			$vets = fetchCol0($sql);
			if($vets) {
				$results['vets.'.$email] = $vets;
				updateTable('tblvet', array('email'=>"lhajkhgshgskjd@gshgskj.com"), "email $inPhrase");
				echo "$db : tblvet updates: ".leashtime_affected_rows()."<br>";
			}
		}
		if($results) {
			echo "<p><b>\"$bizName\" ($db)</b><br>";
			foreach($results as $k=>$v) echo "$k: ".count($v)."<br>";
			echo "<hr>";
		}
	}

		
		


	if(FALSE) { // fix prob
		require_once "appointment-fns.php";
		if(!in_array('tblpreference', $tables) || $biz['test']  || !$biz['activebiz'] ) continue;
		$mods = fetchAssociations("SELECT * FROM tblchangelog WHERE itemtable = 'tblappointment' AND operation = 'm' AND note like  '%EZ Schedule times change to %'");
		$appts = array();
		$started = null;
		foreach($mods as $mod) {
			$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$mod['itemptr']}");
			if($appt && ($note = correctStartAndEndTimes($appt))) {
				$c = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname, CONCAT('(@', clientid, ')')) FROM tblclient WHERE clientid = {$appt['clientptr']}");
				if(!$started) echo ($started = "<p><u>$bizName</u><br>");
				echo shortDate(strtotime($appt['date']))." {$appt['timeofday']} $c - $note<br>";
			}
		}
	}
	if(FALSE) {
		if(!in_array('tblpreference', $tables) || $biz['test']  || !$biz['activebiz'] ) continue;
		$bizAdd = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress' LIMIT 1");
		if(strpos($bizAdd, 'Aptos')) echo "$db address: $bizAdd<p>";
	}
	if(FALSE) {
		if(!in_array('tblpreference', $tables) || $biz['test']  || !$biz['activebiz'] ) continue;
		$invoiceHeader = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'invoiceHeader'", 1);
		if(strpos(strtoupper("$invoiceHeader"), 'PAYPAL')) {
			$nn += 1;
			//echo "$nn $db<br>";
				$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = 'Invoice Payment with PayPal' LIMIT 1", 1);
				if($template) continue;
				$paypaltarget = strpos($invoiceHeader, '/business=');
				if($paypaltarget === FALSE) continue;
				$paypaltarget += strlen('/business=');
				$paypaltarget = substr($invoiceHeader, $paypaltarget, strpos($invoiceHeader, '&', $paypaltarget)-$paypaltarget);
				require_once "field-utils.php";
				if($badtarget = !isEmailValid($paypaltarget)) {
					if(strlen($paypaltarget) < 90) $paypaltarget = "\n\n<font color=red>bad target</font> ($paypaltarget)";
					else $paypaltarget = "<font color=red>bad target</font> (".strlen($paypaltarget).')';
					echo "$db target: [$paypaltarget]<br>";
				}
				if($badtarget) continue;
				$template = array('label'=>'Invoice Payment with PayPal', 
														'subject'=>'Invoice Payment with PayPal', 
														'targettype'=>'client', 'personalize'=>0, 
													'salutation'=>'', 'farewell'=>sqlVal("''"), 'active'=>1,
													'extratokens' => sqlVal("''"),
													'body'=>"Hi #FIRSTNAME#, 

It looks like PayPal is having trouble processing invoices, but you can still use the \"Send Money\" feature to make a payment to us  on your account without using the invoice.

Payment should be made to $paypaltarget.  Please put the phrase

Pet Sitting Services (@#CLIENTID#) 

in the note field when you do so, to ensure your payment is properly registered to your LeashTime account.

Warm Regards,

#BIZNAME#");
				$id = insertTable('tblemailtemplate', $template, 1);
				echo "$db inserted template #$id for target: [$paypaltarget]<br>";
				//exit;
		}
	}
	if(FALSE) { 
		//if(!in_array('tblclient', $tables)) continue;
		if(!in_array('tblecheckacctinfo', $tables)) continue;
		//$as = fetchAssociations("SELECT fname, lname FROM tblprovider WHERE lname LIKE '%lindenfelser%'", 1);
		//$as = fetchAssociations("SELECT name FROM tblcontact WHERE name LIKE '%lindenfelser%'", 1);
		//if(!in_array('tblecheckacct', $tables)) continue;
		//$as = fetchAssociations("SELECT acctname FROM  tblecheckacct WHERE acctname LIKE '%lindenfelser%'", 1);
		//if(!in_array('tblcreditcardinfo', $tables)) continue;
		//$as = fetchAssociations("SELECT x_first_name, x_last_name, x_address FROM  tblecheckacct WHERE x_last_name LIKE '%lindenfelser%'", 1);
		$as = fetchAssociations("SELECT x_address FROM  tblecheckacctinfo WHERE x_address LIKE '%606 birch s%'", 1);
		if($as) {
			echo "<span style='color:red;'>$db</span><br>";
			foreach($as as $a)
				echo "{$a['x_address']}<br>";
		}
	}
			
	if(FALSE) { 
		if(!in_array('tblmessage', $tables) || $biz['test']  || !$biz['activebiz'] ) continue;
		// compare two month's revs
		$month1 = '2020-02-01';
		$month1end = date('Y-m-t', strtotime($month1));
		$month2 = '2020-05-01';
		$month2end = date('Y-m-t', strtotime($month2));
		$rev1 = fetchRow0Col0($sql1 = "SELECT SUM(amount) FROM tblcredit WHERE payment=1 AND issuedate BETWEEN '$month1' AND '$month1end'", 1);
		$rev2 = fetchRow0Col0($sql2 = "SELECT SUM(amount) FROM tblcredit WHERE payment=1 AND issuedate BETWEEN '$month2' AND '$month2end'", 1);
		//echo "$sql1<br>$sql2";exit;
		if((int)$rev1) {
			$rev1Total += $rev1;
			$rev2Total += $rev2;
			$drop = 1 - $rev2/$rev1;
			$myBizCount += 1;
			$color = 
				$drop > .7 ? 'darkred' : (
				$drop > .6 ? 'red' : (
				$drop > .4 ? 'pink' : (
				$drop > .3 ? 'orange' : (
				$drop > .3 ? 'blue' : (
				$drop > .2 ? 'green' : 'black')))));
			$colors[$color] += 1;
			echo "<span style='color:$color;'>$db,$rev1,$rev2,$drop</span><br>";
		}
		//else echo "$db,$rev1,$rev2,-1<br>";
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $rev1Total, $rev2Total, $myBizCount, $colors;
				$dropTotal = 1 - $rev2Total/$rev1Total;
				echo "TOTAL ($myBizCount),$rev1Total,$rev2Total,$dropTotal<p>";
				asort($colors);
				foreach($colors as $k => $v) echo "$k: $v<br>";
			}
		}
	}
	
	if(FALSE) { 
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		$status = null;
		foreach(explode(',', 'invoicing,betaBilling,betaBilling2') as $k)
			if($z = fetchPreference("{$k}Enabled")) $status[$k] = $z;
		$bizName = $bizName ? $bizName : $biz['bizname'];
		if(!$status) $nada[] = "\"$bizName\",$db";
		else if($status['betaBilling2']) $b2[] = "\"$bizName\", ($db) ,".join(' ', array_keys($status));
		else if($status['betaBilling']) $b1[] = "\"$bizName\", ($db) ,".join(' ', array_keys($status));
		if($status['invoicing']) $inv[] = "\"$bizName\", ($db) , ".join(' ', array_keys($status));
		
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $b1, $b2, $inv;
				sort($b1);
				sort($b2);
				sort($inv);
				echo "<b>Beta Billing 2</b><br>".join("<br>", $b2);
				echo "<p><b>Beta Billing</b><br>".join("<br>", $b1);
				echo "<p><b>Invoicing</b><br>".join("<br>", $inv);
			}
		}
	}
		
	if(FALSE) { // Add a zero-sitter rate to each business that lacks one
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!$biz['rates']) continue;
		$firstComma = strpos($biz['rates'], ',');
		$firstRate = explode('=', substr($biz['rates'], 0, $firstComma));
		$firstCharge = 0+$firstRate[1];
		$status = in_array($bizptr, $goldstars) ? 'gold' : (
							in_array($bizptr, $greystars) ? 'grey' : (
							in_array($bizptr, $deadtrials) ? 'deadtrial' : (
							in_array($bizptr, $trials) ? 'trial' : '')));
		$rateLine = "\"$bizName\",$db,$status, \"{$biz['rates']}\"<br>";
		if($firstRate[0] == '0') {
			if($firstCharge > 9.95)
				echo "<font color=red>\"HAS HIGHER zero rate ($firstCharge)\",$rateLine</font>";
			else if($firstCharge == 9.95) echo "<font color=blue>\"HAS STANDARD zero rate ($firstCharge)\",$rateLine</font>";
			else echo "<font color=red>\"HAS LOWER zero rate ($firstCharge)\",$rateLine</font>";
		}
		else if($firstRate[0] != '0') {
			$zeroRate = number_format(min(9.95, $firstCharge), 2);
			$newRates = "0=$zeroRate,{$biz['rates']}";
			$rateLine = "\"$bizName\",$db,$status, \"$newRates\"<br>";
			//echo "\"WILL ADD ZERO RATE OF \$$zeroRate\",$rateLine";
			require "common/init_db_common.php";

			//updateTable('tblpetbiz', array('rates'=>$newRates), "bizid ={$biz['bizid']}", 1);
			echo "\"CHANGED RATES FOR,\"$bizName\",$db,$status,$newRates<br>";
			//if($firstCharge < 9.95) 
			//	echo "\"WILL ADD ZERO RATE OF \$$firstCharge\",$rateLine";
			//else echo "\"WILL ADD ZERO RATE OF $9.95\",$rateLine";
			
		}
		//echo "WILL CHANGE: \"$bizName\",$db,\"{$biz['rates']}\"<br>";
	}
	
	if(FALSE) { // Goldstars with no zero rate
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) continue;
		if(strpos($biz['rates'], '0') == 0)
			echo "\"$bizName\",$db,\"{$biz['rates']}\"<br>";
	}
	
	if(FALSE) { // Cancellations in the last n days
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allCancels;
				ksort($allCancels);
				echo "Date (#businesses) cancellations<br>";
				foreach($allCancels as $date=>$bizzes)
					echo date('m/d/Y', strtotime($date))." (".count($bizzes).") ".array_sum($bizzes)."<br>";
				echo "<hr>Date,cancellations,#businesses<br>";
				foreach($allCancels as $date=>$bizzes)
					echo date('m/d/Y', strtotime($date)).",".array_sum($bizzes).",".count($bizzes)."<br>";
			}
		}
		if(!$started) $allCancels = array();
		$started = true;
		
		$daysBack = 60;
		$starting = date("Y-m-d 00:00:00", strtotime("-$daysBack days"));
		/*$cancellations = fetchCol0(
			"SELECT canceled 
				FROM tblappointment
				WHERE canceled >= '$starting'", 1);*/
		$startingDate =  date("Y-m-d", strtotime("-$daysBack days"));
		$cancellations = fetchCol0(
			"SELECT date 
				FROM tblappointment
				WHERE date >= '$startingDate'
				AND canceled IS NOT NULL", 1);
		foreach($cancellations as $dtime)
			$allCancels[date('Y-m-d', strtotime($dtime))][$db] += 1;
		foreach((array)$allCancels as $date=>$bizzes)
			foreach((array)$bizzes as $biz)
				if($biz == $db) ; // ... how to handle separate bizzes?
	}
		


	if(FALSE) { // Schedule request in the last 7 days
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if($_REQUEST['csv'] && !$headerRow) 
			echo "Database,".($headerRow = "cancel,change,General,Schedule,uncancel")."<br>";
		if(!function_exists('requestFrequency')) {
			function postProcess() {
				global $totals;
				echo "<hr>{$totals['BIZZES']} businesses, {$totals['ALL']} requests total.<p>";
				foreach($totals as $type => $freq) {
					if(in_array($type, explode(',', 'BIZZES,ALL'))) continue;
					echo "$type: $freq [".number_format($freq/$totals['ALL']*100, 1)."%]<p>";
				}
			}
	
			function requestFrequency($daysAgo= 30) {
				$requestTypes = 'cancel,change,uncancel,Schedule,General,schedulechange';
				$sql = "SELECT requesttype, COUNT(*)
								FROM tblclientrequest
								WHERE requesttype IN ('".join("','", explode(',', $requestTypes))."')
									AND received >= '".date('Y-m-d 00:00:00', strtotime("-$daysAgo days"))."'
								GROUP BY requesttype
								ORDER BY requesttype";
				return fetchKeyValuePairs($sql, 1);
			}
		}
		if($freqs = requestFrequency(60)) {
			$parts = array();
			foreach($freqs as $type => $freq) {
				$totals[$type] += $freq;
				$totals['ALL'] += $freq;
				$parts[] = "$type=$freq";
				$nums[$type] = "$freq";
			}
			$cols = null;
			foreach(explode(',', $headerRow) as $col) $cols[] = $nums[$col];
			$totals['BIZZES'] += 1;
			if($_REQUEST['csv']) echo "$db,".join(',', $cols)."<br>";
			else echo "$db: ".join(', ', $parts)."<br>";
		}
	}
		
	if(FALSE) { // Schedule request in the last 7 days
		if(!in_array('tblservice', $tables)) continue;
		if(fetchPreference('sittersPaidHourly')) {
			echo "$bizName [$bizptr] ($db)<br>";
		}
	}
		
	if(FALSE) { // Schedule request in the last 7 days
		if(!in_array('tblservice', $tables)) continue;
		if(!$started) {
			//echo "greystars: [".count($greystars)."] ".print_r($greystars, 1);
			$period = 7;
			echo "<h2>Schedule Requests in the last $period days</h2>";
		}
		$started = true;
		$date = date('Y-m-d 00:00:00', strtotime("-$period DAYS"));
		$bcount = fetchRow0Col0("SELECT COUNT(*) FROM tblclientrequest WHERE requesttype = 'Schedule' AND received >= '$date'", 1);
		if($bcount) {
			echo "[@$ltclientid] $bizName [$bizptr] ($db): $bcount<br>";
			$sbizzes[$bizName] = $bcount;
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $sbizzes;
				echo "<hr>".count($sbizzes)." businesses, ".array_sum($sbizzes). " requests total.";
			}
		}
	}
	
	if(FALSE) { // who bills with PayPal currently?
		if(!in_array('tblmessage', $tables)  || !$biz['activebiz']
			|| in_array($bizptr, $greystars) || in_array($bizptr, $deadtrials) ) continue;
		$header = fetchPreference('invoiceHeader');
		if(!$started) {
			//echo "greystars: [".count($greystars)."] ".print_r($greystars, 1);
			echo "<h2>Bill with PayPal?</h2>";
		}
		$started = true;
		if(strpos(strtoupper($header), 'PAYPAL') !== false)  echo "[@$ltclientid] $bizName [$bizptr] ($db)<br>";
	}
	
	if(FALSE) { // make multi week recurring schedules possible for all
		if(!in_array('tblservice', $tables)) continue;
		$cols = fetchAssociationsKeyedBy("SHOW COLUMNS FROM tblservice", 'Field', 1);
		if($cols['week']) {echo "Skipping $db.<br>"; continue;}
		echo "Updating $db...<br>";
		doQuery("ALTER TABLE `tblservice` ADD `week` INT NULL COMMENT 'for multiweek recurring' AFTER `surchargenote` ;");
		doQuery("ALTER TABLE `tblrecurringpackage` ADD `weeks` INT NULL COMMENT 'for multiweek recurring' AFTER `effectivedate` ,
 							ADD `firstsunday` DATE NULL COMMENT 'for multiweek recurring - unused for now' AFTER `weeks` ;");
	}
	
	if(FALSE) { // change label on email templates from #STANDARD - Send Client Request to Sitter = > #STANDARD - Send Client Schedule Request to Sitter
		if(!in_array('tblmessage', $tables)) continue;
		$oldValue = "#STANDARD - Send Client Request to Sitter";
		$newValue = "#STANDARD - Send Client Schedule Request to Sitter";
		updateTable('tblemailtemplate', array('label'=>$newValue), "label = '$oldValue'", 1);
		if(mysqli_affected_rows()) echo "$db: updated.<br>";
		echo "$db: NO CHANGE.<br>";
	}

	if(FALSE) { // switch every manager with emailFromLabel over to managerNickname
		if(!in_array('tblmessage', $tables)) continue;
		$mgrs = fetchKeyValuePairs("SELECT userptr, value FROM tbluserpref WHERE property = 'emailFromLabel' AND value IS NOT NULL AND value != ''", 1);
		if($mgrs) {
			foreach($mgrs as $userptr => $value) {
				replaceTable('tbluserpref', array('userptr'=>$userptr, 'property'=>'managerNickname', 'value'=>$value), 1);
				echo "$db: $value<br>";
			}
			echo "<br>";
		}
	}
	if(FALSE) { // Find biznames with banned sitters AND preferred sitters
		require_once "provider-fns.php";

		if(!in_array('tblmessage', $tables)  || !$biz['activebiz']) continue;
		$ids = fetchCol0("SELECT clientptr  FROM `tblclientpref` WHERE `property` LIKE 'preferredproviders'");
		$results =  array();
		foreach($ids as $id) {
			$results[] = "@$id"; continue;
			$banned = providerIdsWhoWillNotServeClient($id);
			foreach($banned as $provid)
				if($count = fetchRow0Col0("SELECT COUNT(active) FROM tblprovider WHERE providerid IN (".join(',', $banned).") AND active=1 LIMIT 1", 1))
					$results[] = "@$id ($count banned)";
		}
		if($results) echo "<p><u>$bizName</u><br>".join('<br>', $results);

	}
	
	if(FALSE) { // Find biznames with a preference turned on
		if(!in_array('tblmessage', $tables)  || !$biz['activebiz']) continue;
		if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableSitterTipMemos' AND value != '0' LIMIT 1")) {
			$n++;
			echo "$bizName ($n)<br>";
		}
		//else "$bizName ($n) NOPE<br>";

	}
	
	if(FALSE) { // Find biznames without visit report features turned on
		if(!in_array('tblmessage', $tables)  || !$biz['activebiz'] || in_array($bizptr, $greystars)) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $n;
				echo "<hr>$n businesses";
			}
		}
		/*$props = explode(',', 'enhancedVisitReportArrivalTime,enhancedVisitReportCompletionTime'
											.',enhancedVisitReportVisitNote,enhancedVisitReportMoodButtons'
											.',enhancedVisitReportPetPhoto,enhancedVisitReportRouteMap');
		*/
		$props = array('mod_securekey');
		$vals = explode(',', "arrival,completion,note,mood buttons,photo,map");
		//$lookup = array_combine($props, $vals);
		
		$props = join("', '", $props);
		//if($used = fetchCol0("SELECT property FROM tblpreference WHERE property IN ('$props') AND value != '0'")) {
		if(!($used = fetchCol0("SELECT property FROM tblpreference WHERE property IN ('$props') AND value != '0'"))) {
			$n++;
			//$used = count($used)." selected: ".join(", ", $used);
			$vals = null;
			foreach($used as $k) $vals[] = $lookup[$k];
			$vals = count($vals)." selected: ".join(", ", $vals);
			
			echo "$bizName [$vals]<br>";
		}

	}
	
	if(FALSE) { // Find biznames with a certain dog breed
		if(!in_array('tblpet', $tables)) continue;
		$found = fetchCol0("SELECT breed FROM tblpet WHERE type LIKE '%dog%' AND breed LIKE '%KLEE%'", 1);
		if($found) {
			echo "$db: ".count($found).': '.print_r(join(', ', $found), 1)."<br>";
			$total += count($found);
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $total;
				echo "<hr>$total dogs";
			}
		}
		
	}


	if(FALSE) { // Find number of visits arrived since ...
		$minuteLimit = 15;
		if(!in_array('tblmessage', $tables)  || !$biz['activebiz'] || in_array($bizptr, $greystars)) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $activeVisitBizzes, $minuteLimit, $activeVisits;
				echo "<hr>Bizzes with visits in the last $minuteLimit minutes: $activeVisitBizzes ($activeVisits visits).";
			}
		}
		setLocalTimeZone($biz['timeZone']);
		
		$date = '2019-06-04';
		$timeThreshold = date('H:i:s', strtotime("-$minuteLimit minutes"));
		$apptids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE date = '$date'", 1);
		if($apptids) {
			$apptmvs = fetchCol0("SELECT DISTINCT appointmentptr FROM tblgeotrack 
				WHERE appointmentptr IN (".join(',', $apptids).")
					AND date > '$date $timeThreshold'", 1);
			//if($apptmvs) echo "<p>$db: ".count($apptmvs);
		}
		$apptids = fetchCol0(
				"SELECT appointmentid FROM tblappointment 
					WHERE date = '$date' 
					AND completed IS NOT NULL
					AND completed > '$date $timeThreshold'", 1);
		foreach($apptids as $apptid)
			$apptmvs[] = $apptid;
		if($apptmvs) $apptmvs = array_unique($apptmvs);
		if($apptmvs) {
			echo "<p>$db [{$biz['state']}]: ".count($apptmvs);
			$activeVisitBizzes += 2;
			$activeVisits += count($apptmvs);
		}
		//else echo "<p>$db: NONE";
	}

// ALTER TABLE `tbltimeoffinstance` CHANGE `providerptr` `providerptr` INT( 10 ) NOT NULL DEFAULT '0' 
	if(FALSE) { // Find latest profile Android Visits and provide links to coord pages
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) continue;
		require_once "preference-fns.php";
		if($ids = fetchCol0(
			"SELECT appointmentid, value
				FROM tblappointment
				LEFT JOIN tblappointmentprop ON appointmentptr = appointmentid AND property = 'native'
				WHERE date = '2018-10-10' AND value = 'AND'", 1)) { // if reported Android visits
			echo "<p>$bizName ($n)<br>";
			foreach($ids as $id) {
				$tracks = fetchRow0Col0("SELECT COUNT(*) FROM tblgeotrack WHERE appointmentptr = $id", 1);
				if($tracks > 2)
					echo "<a target='_blank' href='https://leashtime.com/visit-map.php?id=$id&showcoords=1'>$id</a><br>";
			//https://leashtime.com/visit-map.php?id={$appt['appointmentid']}&showcoords=1
			}
		}

	}
	
	
	if(FALSE) { // Find latest profile change requests
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) continue;
		if($n = fetchRow0Col0("SELECT COUNT(*) FROM tblclientrequest WHERE requesttype = 'Profile' AND received > '2018-10-10 00:00:00' AND resolved = 0")) { //  AND resolved = 0 AND resolved IS NULL
			echo "$bizName ($n)<br>";
		}

	}
	
		
	if(FALSE) { // examine older businesses
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] | $bizptr > 300 || !in_array($bizptr, $goldstars)) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $bizstats, $userids;
				foreach($userids as $bizptr=>$clientids)
					foreach((array)$clientids as $clientuserid) {
						$reverseuserids[$clientuserid] = $bizptr;
						$alluserids[] = $clientuserid;
					}
				require "common/init_db_common.php";
				$loginids = fetchKeyValuePairs("SELECT TRIM(loginid) as loginid, userid FROM tbluser WHERE userid IN (".join(',', $alluserids).")", 1);
				$startdate = date('Y-m-d', strtotime("-14 days"));
				$result = doQuery($sql = "SELECT loginid, COUNT(*) as logins
														FROM tbllogin
														WHERE LastUpdateDate >= '$startdate  00:00:00'
														AND loginid IN ('".join("','", array_keys($loginids))."')
														GROUP BY loginid", 1);
//echo "$sql<br>";														
				while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
					$bizptr = $reverseuserids[$loginids[trim($row['loginid'])]];
//echo "{$row['loginid']} ({$loginids[trim($row['loginid'])]}) biz: $bizptr LOGINS: {$row['logins']}<br>";					
					$bizstats[$bizptr]["logins since $startdate"] += $row['logins'];
				}
				foreach($bizstats as $i => $row) {
					if(!$bizstats[$i]["logins since $startdate"])
						$bizstats[$i]["logins since $startdate"] = 0;
					$website = $bizstats[$i]['website'];
					unset($bizstats[$i]['website']);
					$bizstats[$i]['website'] = $website;
					$bizPhone = $bizstats[$i]['bizPhone'];
					unset($bizstats[$i]['bizPhone']);
					$bizstats[$i]['bizPhone'] = $bizPhone;
				}

				if($_REQUEST['csv']) {
					//dumpCSVRow(array_keys($bizstats[$bizptr]));
					foreach($bizstats as $row) {
						if(!$go) dumpCSVRow(array_keys($row));
						$go = 1;
						dumpCSVRow($row);
					}
				}
				else quickTable($bizstats, $extra="border=1", $style=null, $repeatHeaders=0);
			}
			
			function dumpCSVRow($row, $cols=null) {
				if(!$row) echo "\n";
				if(is_array($row)) {
					if($cols) {
						$nrow = array();
						if(is_string($cols)) $cols = explode(',', $cols);
						foreach($cols as $k) $nrow[] = $row[$k];
						$row = $nrow;
					}
					echo join(',', array_map('csv',$row))."\n";
				}
				else echo csv($row)."\n";
			}
			
			function csv($val) {
			  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
			  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
			  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
				return "\"$val\"";
			}

		}

		$row['bizid'] = $bizptr;
		$row['bizName'] = $bizName;
		$row['db'] = "$db";
		require_once "preference-fns.php";
		$row['accepts prospects'] = fetchPreference('acceptProspectRequests') ? 'yes' : 'no';
		$row['prospect requests'] = fetchRow0Col0("SELECT COUNT(*) FROM tblclientrequest WHERE requesttype = 'prospect'", 1);
		$userids[$bizptr] = fetchCol0("SELECT userid FROM tblclient WHERE userid IS NOT NULL", 1);
		$row['client usernames'] = count($userids[$bizptr]);
		$row['welcome date'] = fetchRow0Col0("SELECT received FROM tblclientrequest WHERE extrafields = 'Welcome to LeashTime!' LIMIT 1", 1);
		$row['website'] = fetchPreference('bizHomePage');
		$row['bizPhone'] = fetchPreference('bizPhone');
		$bizstats[$bizptr] = $row;
	}

	if(FALSE) { // Find service types matching a pattern
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		
		$pattern = '%Group%';
		$types = fetchCol0($sql = 
			"SELECT label 
				FROM tblservicetype
				WHERE label LIKE '$pattern'");
		if(!$zoop) echo "Service types matching [$pattern]<p>";
		$zoop = 1;
		if($types) echo "<p><b>$db</b><br>".join('<br>', $types);		
	}
	if(FALSE) { // Who uses custom prospect forms?
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		
		$pattern = '%Socialized%';
		$prospects = fetchRow0Col0($sql = 
			"SELECT COUNT(*) 
				FROM tblclientrequest
				WHERE 
					requesttype = 'prospect'
					AND extrafields IS NOT NULL
					AND extrafields LIKE '$pattern'");
		if(!$zoop) echo str_replace("\n", "<br>", str_replace("<", "&lt;", $sql))."<p>";
		$zoop = 1;
		if($prospects) echo $db.": $prospects with '$pattern'<br>";		
	}

	if(FALSE) { // Who uses custom prospect forms?
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		
		$pattern = '%"form_referer"><![CDATA[https://leashtime.com/prospect-request-form-custom.php%';
		$alienProspects = fetchRow0Col0($sql = 
			"SELECT COUNT(*) 
				FROM tblclientrequest
				WHERE 
					requesttype = 'prospect'
					AND extrafields LIKE '%form_referer%' 
					AND extrafields NOT LIKE '$pattern'");
		if(!$zoop) echo str_replace("\n", "<br>", str_replace("<", "&lt;", $sql))."<p>";
		$zoop = 1;
		if($alienProspects) echo $db.": $alienProspects alien prospects<br>";		
	}

	if(FALSE) {
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if($bad = fetchRow0Col0("SELECT COUNT(*) FROM tblprovidermemo WHERE note = 'schedule|'")) {
			echo $db.": $bad<br>"; // x_aux_key x_login  9245672983
			deleteTable('tblprovidermemo', "note = 'schedule|'", 1);
		}
	}

	if(FALSE) {
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'providersScheduleRetrospectionLimit' LIMIT 1"))
			echo $db.'<br>'; // x_aux_key x_login  9245672983
	}

	if(FALSE) {
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		require_once "encryption.php";
		if('9217423069' == lt_decrypt(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'x_login' LIMIT 1")))
			echo $db; // x_aux_key x_login  9245672983
	}

	if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {
				unset($_SESSION["bizptr"]);
			}
		}
		require_once "preference-fns.php";
		require_once "remote-file-storage-fnsCANDIDATE.php";
		
		$credentials = getRemoteStorageCredentials();

		$_SESSION["bizptr"] = $bizptr;
		$dbs =
		/*"inhomepetsitters,napps2017,kramerspetsitting,firstratepet,db203pet,bestinshowpetsitting,careypet,creaturecomfort,azcreaturecomforts"
		.",dogslife,fivepawsdelco,db4pawspetsitting,gwinnettpetwatchers,itsadogslifeny,mypetsbuddy,pawfessionaltouch,peaceofmindpet,tonkatest"
		.",tlcpetsitter,thepetgurl,wisconsinpetcare,windycitypaws,woofnpurrpetsit";
		*/
		/*ERRORS:   */
				"peaceofmindpet";
		
		$dbs = explode(',', $dbs);
		
		if(in_array($db, $dbs) && in_array('tblremotefile', fetchCol0("SHOW TABLES"))) {
			echo "<hr>$db<p>";
			foreach(fetchCol0("SELECT remotepath FROM tblremotefile WHERE ownertable = 'tblclient'", 1) as $remotePath) {
				$absPath = absoluteRemotePath($remotePath);
				if(!checkAWSErrorFAST($absPath, $credentials=null)) echo ".";
				else echo "<br>missing: $absPath";
			}
		}
	}

	if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {
				unset($_SESSION["bizptr"]);
			}
		}
		require_once "preference-fns.php";
		require_once "remote-file-storage-fnsCANDIDATE.php";
		$_SESSION["bizptr"] = $bizptr;
		$dbs =
/*		"inhomepetsitters,napps2017,kramerspetsitting,firstratepet,db203pet,bestinshowpetsitting,careypet,creaturecomfort,azcreaturecomforts"
		.",dogslife,fivepawsdelco,db4pawspetsitting,gwinnettpetwatchers,itsadogslifeny,mypetsbuddy,pawfessionaltouch,peaceofmindpet,tonkatest"
		.",tlcpetsitter,thepetgurl,wisconsinpetcare,windycitypaws,woofnpurrpetsit";
*/
		"tonkatest"
		.",tlcpetsitter,thepetgurl,wisconsinpetcare,windycitypaws,woofnpurrpetsit";
		
		/*ERRORS:
				"peaceofmindpet";
		*/
		$dbs = explode(',', $dbs);
		
		if(in_array($db, $dbs) && in_array('tblremotefile', fetchCol0("SHOW TABLES"))) {
			echo "<hr>$db<p>";
			relocateRemoteFiles();
		}
	}


	if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {
				echo "<hr>";
				global $packrat, $doomedClients;
				foreach($doomedClients as $clientptr=>$unused) {
					if(count($packrat[$clientptr]) != count(array_unique($packrat[$clientptr]))) $color="style='color:red'";
					else $color="";
					echo "<p $color>$clientptr: ".join(', ', $packrat[$clientptr]);
				}
				
			}
		}
		$dbs =
		"inhomepetsitters,napps2017,kramerspetsitting,firstratepet,db203pet,bestinshowpetsitting,careypet,creaturecomfort,azcreaturecomforts"
		.",dogslife,fivepawsdelco,db4pawspetsitting,gwinnettpetwatchers,itsadogslifeny,mypetsbuddy,pawfessionaltouch,peaceofmindpet,tonkatest"
		.",tlcpetsitter,thepetgurl,wisconsinpetcare,windycitypaws,woofnpurrpetsit";
		$dbs = explode(',', $dbs);
		
		if(in_array($db, $dbs) && in_array('tblremotefile', fetchCol0("SHOW TABLES"))) {

			$clients = fetchKeyValuePairs("SELECT DISTINCT ownerptr, 1 FROM tblremotefile WHERE ownertable = 'tblclient'");

			foreach($clients as $clientptr=>$unused) {
				foreach(fetchCol0("SELECT remotepath FROM tblremotefile WHERE ownerptr = $clientptr") as $f)
					$packrat[$clientptr][] = $f;
				if($allclients[$clientptr]) {
					$doomedClients[$clientptr] = 1;
					echo "$clientptr: [".join(', ', $allclients[$clientptr]).", $db]<br>";
				}
				$allclients[$clientptr][] = $db;
			}
		}

	}


	if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allMgrs;
				unset($_SESSION["bizptr"]);
				echo "<hr><hr>".join(', ', $allMgrs)."<p>";
			}
		}
		$dbs =
		"inhomepetsitters,napps2017,kramerspetsitting,firstratepet,db203pet,bestinshowpetsitting,careypet,creaturecomfort,azcreaturecomforts"
		.",dogslife,fivepawsdelco,db4pawspetsitting,gwinnettpetwatchers,itsadogslifeny,mypetsbuddy,pawfessionaltouch,peaceofmindpet,tonkatest"
		.",tlcpetsitter,thepetgurl,wisconsinpetcare,windycitypaws,woofnpurrpetsit";
		$dbs = explode(',', $dbs);
		
		if(in_array($db, $dbs)) {
			$_SESSION["bizptr"] = $bizptr;
			$mgrs = getManagers();
			if($mgrs) {
				echo "<hr>$db<p>";
				foreach($mgrs as $mgr) if($mgr['active'] && $mgr['email']) {
					$allMgrs[] = $mgr['email'];
					echo $mgr['email'].'<br>';
				}
			}
		}
		
		/*$clients = fetchKeyValuePairs("SELECT DISTINCT ownerptr, 1 FROM tblremotefile WHERE ownertable = 'tblclient'");
		
		foreach($clients as $clientptr)
			if($allclients[$clientptr]) $doomedClients[$clientptr] += 1*/

	}




	if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $fails, $success;
				echo "$fails failures  $success successes.";
			}
		}
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		$todaysRunTime = fetchRow0Col0(
			"SELECT time 
			FROM tblchangelog 
			WHERE itemptr = 999 AND itemtable = 'providerschedules' 
			ORDER BY time desc LIMIT 1", 1);
	//logChange(999, 'providerschedules', 'c', "Queued up provider schedules: [$numsent].");
		if(!$todaysRunTime || date('Y-m-d', strtotime($todaysRunTime)) != date('Y-m-d')) {
			$bizAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress' LIMIT 1", 1);
			$address = explode(' | ', (string)$bizAddress);
			$cityState = "{$address[2]} {$address[3]}";
			echo "<font color=red>$bizName [$db] did not get schedules today.</font> [$cityState]<br>";
			$fails += 1;
		}
		else {
			echo "<font color='#D3D3D3'>$bizName [$db] ".date('H:i:s', strtotime($todaysRunTime))."</font><br>";
			$success += 1;
		}
		
	}

// ALTER TABLE `tblchangelog` ADD INDEX `item` ( `itemtable` , `itemptr` ) 
	if(FALSE) { // ACTIVE BIZ COUNTRIES
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $count;
				echo $count." businesses.";
			}
		}
		$count += fetchRow0Col0("SELECT 1 FROM tblpreference WHERE property = 'ccGateway' AND value ='Authorize.net' LIMIT 1", 1);
	}

	if(FALSE) { // find all spoofers
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) continue;
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('emailHost', 'emailFromAddress')");
		if(strpos($prefs['emailHost'], 'utlook')) {
			echo "$bizName &lt;{$prefs['emailHost']}&gt;<br>";
		}
	}

	if(FALSE) { // ACTIVE BIZ COUNTRIES
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $countries, $paying;
				echo "<h2>Active Businesses by Country</h2>.";
				echo count($countries)." businesses. * = Paying<p>";
				foreach($countries as $country=>$bizzes) {
					if(!$paying[$country]) $paying[$country] = 'none';
					echo "<hr><u>$country</u> ".count($bizzes)." ({$paying[$country]} paying)<p>".join(', ', $bizzes);
				}
			}
		}
		$countries[$biz['country']][] = in_array($bizptr, $goldstars) ? "*$bizName" : $bizName;
		if(in_array($bizptr, $goldstars)) $paying[$biz['country']] += 1;
	}
	
	if(FALSE) { // TOTAL MESSAGES YESTERDAY
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $total, $emailbizzes, $date;
				echo "<hr>Total visits on $date: $total send by $emailbizzes businesses.";
			}
			echo "<h2>Visits on $date</h2>";
		}
		$date = '2017-12-31';
		$count = fetchRow0Col0(
				"SELECT COUNT(*) 
					FROM tblappointment 
					WHERE canceled IS NULL AND date = '$date'", 1);
		$total += $count;
		if($count) {
			$emailbizzes += 1;
			echo "$bizName ($db): $count<br>";
		}
	}
				
	if(FALSE) {
		if(!in_array('tblproviderpref', $tables)) {echo "$bizName ($db) lacks tblproviderpref.<p>";exit;}
		$choices[fetchRow0Col0("SELECT value FROM tblpreference WHERE property= 'holidayVisitLookaheadPeriod'")] += 1;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $choices;
				print_r($choices);
			}
		}
	}
	
	if(FALSE) { // TOTAL MESSAGES YESTERDAY
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $total, $emailbizzes;
				echo "<hr>Total messages: $total send by $emailbizzes businesses.";
			}
			echo "<h2>Yesterday's email totals</h2>";
		}
		if($_REQUEST['days']) {
			$days = explode('-', $_REQUEST['days']);
			$day0 = date('Y-m-d', strtotime("- {$days[0]} day"));
			$dayN = date('Y-m-d', strtotime("- {$days[1]} day"));
		}
		else {
			$yesterday = date('Y-m-d', strtotime("-1 day"));
			$day0 = $yesterday;
			$dayN = $yesterday;
		}
		
		$count = fetchRow0Col0(
				"SELECT COUNT(*) 
					FROM tblmessage 
					WHERE inbound=0 AND datetime >= '$day0 00:00:00' AND datetime <= '$dayN 23:59:59'", 1);
		$total += $count;
		if($count) {
			$emailbizzes += 1;
			echo "$bizName ($db): $count<br>";
		}
	}
				

	
	
	if(FALSE) {
		require_once "remote-file-storage-fns.php";
		if(!in_array('tblproviderpref', $tables)) {echo "$bizName ($db) lacks tblproviderpref.<p>";exit;}
		
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $totalBefore, $totalAfter, $totalExcess, $threshold;
				echo "<hr>Total before: ".number_format($totalBefore,1).", after: ".number_format($totalAfter,1)
					.", diff = ".number_format($totalBefore-$totalAfter,1)."<br>";
				echo "total files > $threshold: $totalExcess";
			}
			function photoFileUse() {
				global $db;
				foreach(fetchAssociations("SELECT localpath, existslocally FROM tblfilecache") as $file) {
					if(file_exists($file['localpath'])) {
						$bizTotal += round(filesize($file['localpath'])/1024);
						$count += 1;
					}
					//else if($file['existslocally']) echo "[$db] MISSING: {$file['localpath']}<br>";
				}
				return array($bizTotal, $count);
			}
		}
		$usage = photoFileUse();
		$countBefore = $usage[1];
		$bizTotalBefore = $usage[0];
		$totalBefore += $usage[0];
		checkCacheLimits();
		$usage = photoFileUse();
		$countAfter = $usage[1];
		$bizTotalAfter = $usage[0];
		$totalAfter += $bizTotalAfter;
		$line = "$bizName ($db) before: ($countBefore) ".number_format($bizTotalBefore,1).", after:  ($countAfter) ".number_format($bizTotalAfter,1)
					.", diff = ".number_format($bizTotalBefore-$bizTotalAfter,1);
		$threshold = 80;
		$color = $countBefore > $threshold ? 'red' : ($countBefore  ? 'black' : 'gray');
		$totalExcess += max(0, $countBefore - $threshold);
		echo "<span style='color:$color;'>$line</span><br>";
	}
		

if(FALSE) { // find all spoofers
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) continue;
		if(strpos($bizName, 'Canine Adventure') === 0) continue;
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('emailHost', 'emailFromAddress')");
		if($prefs['emailFromAddress'] && $prefs['emailFromAddress'] != 'notice@leashtime.com' && !$prefs['emailHost']) {
			if($prefs['emailFromAddress'] == 'info@annapolisdogwalkers.com') $bizName = 'Annapolis Dog Walkers';
			$bn = str_replace("\"", "", $bizName);
			echo "$bn &lt;{$prefs['emailFromAddress']}&gt;<br>";
		}
	}
	if(FALSE) { // look for dup visit reports today
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
			}
		}
		require_once "request-fns.php";
		$today = date('Y-m-d 00:00:00');
		$found = array();
		$vrs = fetchAssociations("SELECT * FROM tblclientrequest WHERE received >= '$today' AND requesttype = 'VisitReport'");
		echo "$bizName ($db): ".count($vrs)."<br>";
		$first = 1;
		foreach($vrs as $vr) {
			$hash = sha1($vr['extrafields']);
			if($found[$hash]) {
				$client = fetchRow0Col0("SELECT CONCAT(fname, ' ', lname) FROM tblclient WHERE clientid = {$vr['clientptr']} LIMIT 1");
				$xtraFields = getExtraFields($vrs);
				$apptid = $xtraFields['x-appointmentptr'];
				$tod = fetchRow0Col0("SELECT timeofday FROM tblappointment WHERE appointmentid = $apptid LIMIT 1");
				if($first) echo "$bizName ($db)<br>";
				$first = 0;
				echo "$client $tod sitter: {$xtraFields['x-providername']}<br>";
			}
		}
	}
			
	if(FALSE) { // longest service names
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $names;
				usort($names, 'cmName');
				echo "<h2>Longest client service names</h2>";
				foreach($names as $i => $nm) echo "($i) $nm (".strlen($nm).")<br>"; // ($i) 
			}
			function cmName($a, $b) {
				$a = strlen($a);
				$b = strlen($b);
				return $a < $b ? 1 : ($a > $b ? -1 : 0);
			}
		}
		$names[] = fetchRow0Col0(
			"SELECT SUBSTR(value, 1, LOCATE('|', value)-1) as label, LENGTH(SUBSTR(value, 1, LOCATE('|', value))) as n 
			FROM tblpreference
			WHERE property LIKE 'client_service_%'
			ORDER BY n DESC LIMIT 1");
//format: label|servicecode|description
//$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'client_service_%'");

	}
			
	if(FALSE) { // bizzes by name length
		if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $names;
				usort($names, 'cmShortName');
				echo "<h2>Longest names</h2>";
				foreach($names as $nm) echo "{$nm['bizName']} (".strlen($nm['bizName']).") ({$nm['shortBizName']})<br>";
			}
			function cmName($a, $b) {
				$a = strlen("{$a['bizName']}");
				$b = strlen("{$b['bizName']}");
				return $a < $b ? 1 : ($a > $b ? -1 : 0);
			}
			function cmShortName($a, $b) {
				$a = strlen("{$a['shortBizName']}");
				$b = strlen("{$b['shortBizName']}");
				return $a < $b ? 1 : ($a > $b ? -1 : 0);
			}
		}
		$names[] = array('bizName'=>$bizName,'shortBizName'=>fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'shortBizName' LIMIT 1"));

	}
			


	if(FALSE) { // TOTAL MASTER SCHEDULES
		if(!in_array('tblmessage', $tables)) continue;
		$cutoff = date('Y-m-d 00:00:00', strtotime('- 60 days'));
		$masterScheduleMass = fetchRow0Col0("SELECT SUM(length(body)) FROM tblmessage WHERE datetime < '$cutoff' AND subject = 'Master Schedule'");
		$masterScheduleMassTotal += $masterScheduleMass;
		if($masterScheduleMass) {
			echo "\"$bizName\" ($db) ".number_format($masterScheduleMass/1024/1024, 2)." MB<br>";
			if($_GET['deleteolder']) {
				deleteTable('tblmessage', "datetime < '$cutoff' AND subject = 'Master Schedule'", 1);
				doQuery("OPTIMIZE TABLE `tblmessage`");
				$postDeleteMass = fetchRow0Col0("SELECT SUM(length(body)) FROM tblmessage WHERE subject = 'Master Schedule'");
				$postDeleteMassTotal += $postDeleteMass;
				echo "... after deletion: ".number_format($postDeleteMass/1024/1024, 2)." MB<br>";
			}
		}
		if(!function_exists('postProcess')) {
			$t0 = time();
			function postProcess() {
				global $masterScheduleMassTotal, $t0, $postDeleteMassTotal;
				echo "<hr>Total: ".number_format($masterScheduleMassTotal/1024/1024, 2)." MB";
				if($_GET['deleteolder']) echo "<p>Total: ".number_format($postDeleteMassTotal/1024/1024, 2)." MB";
				echo "<p".((time() - $t0)/60)." seconds.";
			}
		}
	}
	
	if(FALSE) { // count overdue visit notices by business
		if(!in_array('tblprovider', $tables)) continue;
		$start = date('Y-m-d 00:00:00', strtotime("- 6 months"));
		$count = fetchRow0Col0("SELECT COUNT(*) FROM tblclientrequest WHERE received > '$start' AND extrafields IS NOT NULL AND extrafields LIKE '<extrafields>%odappts%'", 1);
		if(!$started) echo "Overdue visit notifications since: $start<p>";
		$started = 1;
		if($count) echo "$bizName ($db): $count<br>";
	}

	if(FALSE) {
		$dir = "bizfiles/biz_{$biz['bizid']}/clientui/";
		if(file_exists($dir."Header.jpg")) echo "<img src='{$dir}Header.jpg'><br>";
	}
	if(FALSE) {
		if($biz['test']) echo "<p>$bizName ($db)<br>";
	}
	if(FALSE) { // find sitters with logins that are not p- user role
		if(!in_array('tblprovider', $tables)) continue;
		$userids = fetchCol0("SELECT userid FROM tblprovider WHERE userid IS NOT NULL");
		if($userids) {
			require "common/init_db_common.php";
			$sql = "SELECT userid, loginid, rights FROM tbluser WHERE userid IN (".join('', $userids).") AND rights NOT LIKE 'p%'";
			$bad = fetchAssociationsKeyedBy($sql, 'userid');
			if($bad) {
				echo "<p>$bizName ($db)<br>";
				foreach($bad as $u=>$r) echo "- ($u) {$r['loginid']} has permissions: {$r['rights']}<br>";
			}
		}
	}
	if(FALSE) { // TOTAL QUEUED EMAILS
		if(!$biz['activebiz']) continue;
		$totalCount += fetchRow0Col0("SELECT count(*) FROM tblqueuedemail");
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $totalCount;
				echo "<hr>Total: $totalCount";
			}
		}
	}
	
	if(FALSE) {
		if(!$biz['activebiz']) continue;
		$firstCalls = fetchAssociationsKeyedBy(
				"SELECT userptr, value, CONCAT_WS(' ', fname, lname) as name
					FROM tbluserpref 
					LEFT JOIN tblprovider ON userid = userptr
					WHERE property = 'firstCallToday'", 'userid');
		if($firstCalls) {
			echo "<p>$bizName ($db)<br>";
			foreach($firstCalls as $k => $v) echo "{$v['name']}: {$v['value']}<br>";
		}
	}
	if(FALSE) {
		//if(!$biz['activebiz']) continue;
		$sql = 
		"SELECT (data_length+index_length)/power(1024,1) tablesize
			FROM information_schema.tables
			WHERE table_schema='$db' and table_name='#TAB#';";
		$msgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessage', $sql));
		$msgcount = fetchRow0Col0("SELECT count(*) FROM tblmessage");
		$errorcount = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
		$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		$newErrorsStart = '2016-01-01 00:00:00';
		$newErrors = fetchRow0Col0("SELECT count(*) FROM tblerrorlog WHERE time < '$newMessagesStart'");
		if(0 && /*$errorsizeKB > 5000*/!$biz['activebiz']) {
			//doQuery("DELETE FROM tblerrorlog WHERE time < '$newMessagesStart'");
			doQuery("DELETE FROM tblerrorlog");			
			$dropped = mysqli_affected_rows();
			$dropped = ",(dropped $dropped of $errorcount rows)";
			doQuery(" OPTIMIZE TABLE `tblerrorlog`");
			$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		}
		else $dropped = '';
		$status = $biz['activebiz'] ? '' : '[inactive] ';
		if(!$started) {echo "bizName,db,msgSizeKB,msgcount,errorsizeKB,errorcount,errrors since $newMessagesStart<br>";$started=1;}
		echo "\"$status$bizName\",$db,$msgsizeKB,$msgcount,$errorsizeKB,$errorcount,$newErrors$dropped<br>";	
	}
	


	if(FALSE) {  // clear out old fromClient photos 
		if(!in_array('tblpet', $tables)) continue;
		if($biz['activebiz']) continue;
		$bizcount += 1;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $totals, $bizcount;
				echo "<hr>Active biz totals ($bizcount bizzes)<br>";
				foreach($totals as $k=>$v) echo "$k: $v ";
			}
			function mbytesIn($dir, $olderThan) {
				$size = 0;
				$files = glob($dir);
				foreach($files as $f) {
					if($olderThan && filemtime($f) >= $olderThan) continue;
					if(!is_dir($f)) $size += filesize($f);
				}
				return (int)($size/1024/1024);
			}
			function deleteOlderThan($dir, $olderThan) {
				$size = 0;
				$files = glob($dir);
				foreach($files as $f) {
					if($olderThan && filemtime($f) >= $olderThan) continue;
					$recoveredBytes += filesize($f);
					$recovered += 1;
					unlink($f);
				}
				if($recovered) {
					echo "$db: deleted $recovered files. recovered ".($recoveredBytes/1024)." K<br>";
					exit;
				}
			}
		}
		$dir =  "bizfiles/biz_$bizptr/";
		$dirs = array(
			'pets'=>"$dir/photos/pets/*",
			'fromClient'=>"$dir/photos/pets/fromClient/*",
			'display'=>"$dir/photos/pets/display/*",
			'fromClientOld'=>"$dir/photos/pets/fromClient/*",
			'fromClient'=>"$dir/photos/pets/fromClient/*",
			'appts'=>"$dir/photos/appts/*");
		foreach($dirs as $nm=>$dir) {
			$olderThan = $nm == 'fromClientOld' ? strtotime("-30 days") : null;
			if($nm == 'fromClientOld') deleteOlderThan($dir, $olderThan);
			$row[$nm] = mbytesIn($dir, $olderThan);
			$totals[$nm] += $row[$nm];
		}
		echo "\"$bizName\" ($db) ";
		foreach($row as $k=>$v) echo "$k: $v ";
		echo "<br>";
	}
	
	if(FALSE) { // find 2016 payments from non-US customers
		
		$country = $biz['country'];
		if(!$country || $country == 'US') continue;
		else {
			reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);
			if(!$ltclientidsByBizId) $ltclientidsByBizId = fetchKeyValuePairs("SELECT garagegatecode,clientid FROM tblclient WHERE garagegatecode IS NOT NULL", 1);
			$active = $biz['activebiz'] ? 'active' : 'inactive';
			if($ltclientidsByBizId[$bizptr])
				$payments = fetchRow0Col0(
					"SELECT SUM(amount) 
					FROM tblcredit 
					WHERE clientptr = {$ltclientidsByBizId[$bizptr]} AND voided IS NULL AND payment = 1 AND issuedate LIKE '2016-%'", 1);
			if($payments) {
				echo "$country,\"$bizName\",$db,@$bizptr,$active,$payments<br>";
				$totals[$country] += $payments;
			}
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $totals;
				echo "<hr>Totals by Country<p>";
				foreach($totals as $country => $total) echo "$country: $total<br>";
			}
		}
	}
	
	
	if(FALSE) { // list inactive business file consumption
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $startTime;
				echo "Finished at: ".date('H:i:s')." ".(time() - $startTime)." seconds.";
			}
		}
		if($biz['activebiz']) continue;
		$sql = 
		"SELECT SUM((data_length+index_length)/power(1024,1)) as dbsize
			FROM information_schema.tables
			WHERE table_schema='$db';";
		$allsizeKB = fetchRow0Col0($sql);
		if(!$started) {echo date('H:i:s')." Inactive business db sizes in KB, MB<br>";$started=1;$startTime=time();}
		$allSizeMB = round($allsizeKB/1024);
		echo "\"$bizName\",$db,".round($allsizeKB).",$allSizeMB<br>";
		usleep(100000); // sleep 100 ms so as not to be a complete hog
	}
	



	if(FALSE) {
		if(!in_array('tblchangelog', $tables)) continue;
		if(dbTEST('dogslife')) continue;
		doQuery("ALTER TABLE `tblchangelog` ADD INDEX `item` ( `itemtable` , `itemptr` ) ");
		echo "\"$bizName\" ($db)  tblchangelog item index added.<br>";
	}
	
	if(FALSE) {
		if(!in_array('tbltimeoffinstance', $tables)) continue;
		doQuery("ALTER TABLE `tbltimeoffinstance` CHANGE `providerptr` `providerptr` INT( 10 ) NOT NULL DEFAULT '0'");
		echo "\"$bizName\" ($db)  tbltimeoffinstanceupdated.<br>";
	}
	
	if(FALSE) {
		if(!in_array('tblappointment', $tables)) continue;
		if(in_array($bizptr, $goldstars)) continue;
		$trial = in_array($bizptr, $trials);
		$deadtrial = in_array($bizptr, $deadtrials);
		$greystar = in_array($bizptr, $greystars);
		$flag = $trial ? 'trial' : ($deadtrial ? 'deadtrial' : ($greystar ? 'greystar' : ''));
		$status = fetchAssociationsKeyedBy("SHOW TABLE STATUS", 'Name', 1);
		$r = array(
			'clients'=> $status['tblclient']['Rows'],
			'visits'=> $status['tblappointment']['Rows']);
		$dataLength = 0;
		foreach($status as $tablestats)
			$dataLength += $tablestats['Data_length'];
		$mbs = number_format($dataLength/1024/1024, 2);
		echo "\"$bizName\",$db,$flag,{$r['clients']},{$r['visits']},$mbs<br>";
	}
	
	if(FALSE) {
		if(!in_array('tblmessagearchive', $tables)) continue;
		doQuery("OPTIMIZE TABLE `tblmessage`");
		echo "OPTIMIZED $db<br>";
	}
	
	if(FALSE) {
		if(!in_array('tblerrorlog', $tables)) continue;
		deleteTable('tblerrorlog', "time < '2017-01-01 00:00:00'", 1);
		doQuery("OPTIMIZE TABLE `tblerrorlog`");
	}
	
	if(FALSE) {
		if(!in_array('tblgeotrack', $tables)) continue;
		if(!$started99) {
			echo "Geotracks Today<p>";
			$midnight = date('Y-m-d 00:00:00');
		}
		$started99 = true;
		$midnight = date('Y-m-d 00:00:00');
		$count = fetchRow0Col0("SELECT count(*) FROM tblgeotrack WHERE date > '$midnight'", 1);
		if($count) echo "$bizName ($db): $count<br>";
	}
		
	if(FALSE) {
		if(!in_array('tblmessage', $tables)) continue;
		if($biz['test'] || !$biz['activebiz'] || !in_array($biz['bizid'], $goldstars)) continue;
		$globalCounts['active paying businesses'] += 1;
		$keys = explode(',', 'autoEmailScheduleChangesProvider,autoEmailApptCancellationsProvider,autoEmailApptReactivationsProvider,autoEmailApptChangesProvider');
		$props = fetchKeyValuePairs($sql = "SELECT property, value FROM tblpreference WHERE property IN ('".join("','", $keys)."')");
		//print_r($sql);exit;
		foreach($props as $k=>$v) $globalCounts[$k] += $props[$k];
		$activeSitterIds = fetchCol0("SELECT providerid FROM tblprovider WHERE active=1");
		$props = fetchKeyValuePairs(
				$sql = "SELECT property, value 
								FROM tblproviderpref 
								WHERE property IN ('".join("','", $keys)."')
									AND providerptr IN (".join(",", $activeSitterIds).")");
		//print_r($sql);exit;
		foreach($props as $k=>$v) $globalCounts["$k (individual)"] += $props[$k];

		
		
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $globalCounts;
				ksort($globalCounts);
				foreach($globalCounts as $k=>$v) echo "$k: $v<br>";
			}
		}
	}
	
	if(FALSE) {
		if(!in_array('tblmessage', $tables)) continue;
		if($biz['test'] || !$biz['activebiz']) continue;
		foreach(fetchAssociations("SELECT UPPER(name) as name, UPPER(type) as type FROM tblpet", 1) as $pet) {
			$allPetNames[trim($pet['name'])][trim($pet['type'])] += 1;
			$total += 1;
			$allPetTypes[trim($pet['type'])] += 1;
			//echo " = {$pet['name']} / {$pet['type']} / <br>";if($total == 100) exit;
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allPetNames, $total, $allPetTypes;
				ksort($allPetNames);
				echo "$total pets<p>";
				echo "Pet Types<br>";
				
				foreach($allPetTypes as $type => $num)
					echo '"'.$type.'",'."$num<br>";
					
				echo "<p>";
				
				
				foreach($allPetNames as $nm => $types) {
					ksort($types);
					foreach($types as $type => $num) {
						echo '"'.$nm.'",'.'"'.$type.'",'."$num<br>";
					}
				}
			}
		}
	}
	if(FALSE) {
		if(!in_array('tblmessage', $tables)) continue;
		$start = '2016-12-01';
		$end = '2016-12-31';
		if(!$started) {echo "<h2>Overdue visit notes notes to managers $start - $end</h2>";$started = 1;}
		/*if(!$started) {echo "<h2>Schedule change memo notes to sitters $start - $end</h2>";$started = 1;}
		$sql = "SELECT count(*) 
						FROM tblmessage 
						WHERE subject LIKE 'Schedule changes:%' 
						AND datetime >= '$start 00:00:00'  AND datetime <= '$end 23:59:59'";*/
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$mgrs = fetchCol0("SELECT CONCAT('(correstable=\"tbluser\" AND correspid=',userid, ')') FROM tbluser WHERE bizptr = $bizptr AND
				(rights LIKE 'o-%' OR rights LIKE 'd-%')", 1);
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		
		$sql = "SELECT subject 
						FROM tblmessage 
						WHERE (".join(' OR ', $mgrs).")
						AND subject LIKE '% overdue visits%'
						AND datetime >= '$start 00:00:00'  AND datetime <= '$end 23:59:59'";
		$visitcount = 0;
		$count = 0;
		foreach(fetchCol0($sql, 1) as $sub) {
			$count += 1;
			$visitcount += (int)substr($sub, 0, strpos($sub, ' '));
		}

//echo "[$count] ".$sql;exit;						
		$n += 1;
		//if($n > 20) exit;
		if($count) {
			echo "$bizName ($db)|$count|visits|$visitcount<br>";
			$bizCount += 1;
			$msgTotal += $count;
		}
		/*if(!function_exists('postProcess')) {
			function postProcess() {
				global $bizCount, $msgTotal;
				echo "<hr><b>$msgTotal</b> schedule change memo notes sent to sitters in $bizCount bizzes during $start - $end";
			}
		}*/
	}
	

	if(FALSE) {
		if(!in_array('tblemailtemplate', $tables)) continue;
		$sql = " ALTER TABLE `tblemailtemplate` CHANGE `body` `body` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ";
		doQuery($sql, 1);
		echo "altered tblemailtemplate in $db.<br>";
	}
	if(FALSE) {
		if(!in_array('tblprovidermemo', $tables)) continue;
		$num = fetchRow0Col0("SELECT COUNT(*) FROM tblprovidermemo", 1);
		if($num) {
			echo "<hr>$db waiting memos: $num<br>";
			foreach(fetchAssociations("SELECT * FROM tblprovidermemo", 1) as $memo)
				echo "{$memo['datetime']} {$memo['note']}<br>";
		}
	}
	
	if(FALSE) {
		if(!in_array('relbillablepayment', $tables)) continue;
		doQuery("ALTER TABLE `relbillablepayment` CHANGE `amount` `amount` FLOAT(6,2) NOT NULL", 1);
		echo "altered relbillablepayment.amount limit in $db.<br>";
	}
	
	if(FALSE) { 
			if(!in_array('tblmessagearchive', $tables)) {
				//echo "<font color=red>$db lacks tblmessagearchive.<br></font>";
				continue;
			}
			$fields = fetchAssociationsKeyedBy("DESCRIBE tblmessagearchive ", 'Field');
			echo "($db) ".print_r($fields['body'], 1)."<p>";
			/*if(!$cols['visitdate']) {
				doQuery("ALTER TABLE `tblusergooglevisit` ADD `visitdate` DATE NULL;",1);
				echo "Added tblusergooglevisit.visitdate field in $db.<br>";
			}
			else echo "<b>tblusergooglevisit already has a visitdate field in $db.</b><br>";*/
	};


	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $occurrences;
				echo "<hr>Total occurrences: $occurrences";
			}
		}
		$negcreds = fetchAssociations("SELECT * FROM tblcredit WHERE amount < 0 ORDER BY clientptr, issuedate"); //  AND issuedate >= '2016-01-01'
		$negrefunds = fetchAssociations("SELECT * FROM tblrefund WHERE amount < 0 ORDER BY clientptr, issuedate");  // AND issuedate >= '2016-01-01'
		$occurrences += count($negcreds) + count($negrefunds);
		if($negcreds || $negrefunds)
			echo "<hr>$bizName ($db) has [".count($negcreds)."] negative credits and  [".count($negrefunds)."] negative refunds.";
			foreach((array)$negcreds as $cred) {
				$client = fetchRow0Col0("SELECT CONCAT(fname, ' ', lname, ' (', clientid, ')') 
																	FROM tblclient WHERE clientid = {$cred['clientptr']}");
				$type = $cred['payment'] ? 'payment' : 'credit';
				$date = substr($cred['issuedate'], 0, 10);
				echo "<br>$date $client [$type] $ {$cred['amount']}";
			}
			foreach((array)$negrefunds as $ref) {
				$client = fetchRow0Col0("SELECT CONCAT(fname, ' ', lname, ' (', clientid, ')') 
																	FROM tblclient WHERE clientid = {$ref['clientptr']}");
				$type = 'refund';
				$date = substr($ref['issuedate'], 0, 10);
				if($ref['paymentptr']) $type .= ' of credit '.$ref['paymentptr'];
				echo "<br>$date $client [$type] $ {$ref['amount']}";
			}
	}
		
		
	if(FALSE) {
		require_once "remote-file-storage-fns.php";
		if(!in_array('tblproviderpref', $tables)) {echo "$bizName ($db) lacks tblproviderpref.<p>";exit;}
		
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $totalBefore, $totalAfter;
				echo "<hr>Total before: ".number_format($totalBefore,1).", after: ".number_format($totalAfter,1)
					.", diff = ".number_format($totalBefore-$totalAfter,1)."<br>";
			}
			function photoFileUse() {
				$photosForAllClients = fetchCol0(
					"SELECT photo, clientid, tblclient.active
						FROM tblpet
						LEFT JOIN tblclient ON clientid = ownerptr
						WHERE photo IS NOT NULL");
				if($photosForActiveClients) {
					$bizTotal = 0;
					foreach($photosForActiveClients as $photo) {
						if(file_exists($photo)) {
							$bizTotal += round(filesize($photo)/1024);
						}
					}
				}
				return $bizTotal;
			}
		}
		$bizTotalBefore = photoFileUse();
		$totalBefore += $bizTotalBefore;
		checkCacheLimits();
		$bizTotalAfter = photoFileUse();
		$totalAfter += $bizTotalAfter;
		echo "$bizName ($db) before: ".number_format($bizTotalBefore,1).", after: ".number_format($bizTotalAfter,1)
					.", diff = ".number_format($bizTotalBefore-$bizTotalAfter,1)."<br>";
		
	}
	if(FALSE) {
		if(!in_array('tblproviderpref', $tables)) {echo "$bizName ($db) lacks tblproviderpref.<p>";exit;}
		$sql = " ALTER TABLE `tblproviderpref` CHANGE `value` `value` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ";
		doQuery($sql, 1);
		echo "$bizName ($db) tblproviderpref value set to text.<p>";		
	}
		
	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		$date = date('Y-m-d', strtotime($_REQUEST['date']));
		$sql = "SELECT COUNT(value)
							FROM tblappointmentprop
							LEFT JOIN tblappointment ON appointmentid = appointmentptr
							WHERE date = '$date' AND property = 'visitphotocacheid'";
		if($n = fetchRow0Col0($sql)) echo "$bizName ($db) stored $n visit photos on $date<br>";
	}
	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		$nDays = 180;
		$start = date('Y-m-d 00:00:00', strtotime("- $nDays days"));
		$rows = fetchAssociations($sql = "SELECT * FROM tblclientrequest WHERE requesttype = 'Spam' AND received >= '$start'");
		//echo $sql;exit;
		if($rows) echo "$bizName ($db) has had ".count($rows)." prospect spams in the last $nDays days.<p>";
	}
		
		
		
	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		$today = date('Y-m-d');
		$end = date('Y-m-d', strtotime("+14 days"));
		$futurevisits =  fetchRow0Col0("SELECT COUNT(*) FROM tblappointment WHERE canceled IS NULL AND date >= '$today' AND date <= '$end'");
		$allfuturevisits += $futurevisits;
		$futureunassignedvisits =  fetchRow0Col0("SELECT COUNT(*) FROM tblappointment WHERE canceled IS NULL AND providerptr = 0 AND date >= '$today' AND date <= '$end'");
		$allfutureunassignedvisits += $futureunassignedvisits;
		if(!$started) {
			echo "<h2>Businesses with visits in next seven days</h2>";
			$started += 1;
		}
		if($futurevisits) {
			echo "$bizName ($db): $futurevisits future visits, $futureunassignedvisits unassigned, ".(round($futureunassignedvisits / $futurevisits * 100))." %<br>";
			$count += 1;
		}
		else  ; //echo "<span style='color:gray'>$bizName ($db): no future visits</span><br>";
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allfuturevisits, $allfutureunassignedvisits, $count;
				echo "<hr>Aggregate of $count businesses: $allfuturevisits future visits, $allfutureunassignedvisits unassigned, "
					.(round($allfutureunassignedvisits / $allfuturevisits * 100))." %<br>";
			}
		}
	}

	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		// count the billing statements sent out
		$count = fetchRow0Col0("SELECT COUNT(*) FROM tblmessage WHERE body LIKE '%Customer Number:%'");
		$betaBilling = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'betaBillingEnabled' LIMIT 1") ? 'yes' : 'no';
		$betaBilling2 = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'betaBilling2Enabled' LIMIT 1") ? 'yes' : 'no';
		if(!$count) {
			echo "$bizName has sent $count statements. Beta Billing: $betaBilling BB2: $betaBilling2<br>";
			$toChange += 1;
			/*insertTable('tblpreference', array('property'=>'betaBillingEnabled', 'value'=>'1'), 1);
			insertTable('tblpreference', array('property'=>'betaBilling2Enabled', 'value'=>'1'), 1);
			*/
		}
		if(!function_exists('postProcess')) {
			function postProcess() {global $toChange, $pattern;echo "<hr>DBs to update: $toChange;";}
		}
	}
	
	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		if(!function_exists('postProcess')) {
			function postProcess() {global $found, $pattern;echo "<hr>DBs with gateway $pattern: $found;";}
		}
		$pattern = "Sage"; // "authorize.net"; //"authorize.net""%Solveras%"; // "%v1%"; // "TransFirstTransactionExpress" // TransFirstV1
		//echo "<p>";
		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		/*if($val = fetchRow0Col0("SELECT value 
											FROM tblpreference 
											WHERE property = 'ccAcceptedList' 
											  AND (value LIKE '%American Express%' OR value LIKE '%Amex%')"))
			echo "$bizName uses AMEX [$val]<br>";*/
		$goahead = false;
		if($g = fetchRow0Col0("SELECT value 
											FROM tblpreference 
											WHERE property = 'ccGateway' 
											  AND (value LIKE '$pattern')"))
			{	
				$echecks = fetchRow0Col0("SELECT count(*) FROM tblecheckacct WHERE active=1");
				$echecks = $echecks ? "$echecks active ach accounts found." : "";
				$found +=1; 
				$bizEmail = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizEmail'");
				echo "$bizName uses $g.  $echecks ($bizEmail)<br>";}			
	}



	if(FALSE) {
		//if(!$biz['activebiz']) continue;
		$sql = 
		"SELECT (data_length+index_length)/power(1024,1) tablesize
			FROM information_schema.tables
			WHERE table_schema='$db' and table_name='#TAB#';";
		$msgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessage', $sql));
		$msgcount = fetchRow0Col0("SELECT count(*) FROM tblmessage");
		$errorcount = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
		$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		$newErrorsStart = '2016-01-01 00:00:00';
		$newErrors = fetchRow0Col0("SELECT count(*) FROM tblerrorlog WHERE time < '$newMessagesStart'");
		if(0 && /*$errorsizeKB > 5000*/!$biz['activebiz']) {
			//doQuery("DELETE FROM tblerrorlog WHERE time < '$newMessagesStart'");
			doQuery("DELETE FROM tblerrorlog");			
			$dropped = mysqli_affected_rows();
			$dropped = ",(dropped $dropped of $errorcount rows)";
			doQuery(" OPTIMIZE TABLE `tblerrorlog`");
			$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		}
		else $dropped = '';
		$status = $biz['activebiz'] ? '' : '[inactive] ';
		if(!$started) {echo "bizName,db,msgSizeKB,msgcount,errorsizeKB,errorcount,errrors since $newMessagesStart<br>";$started=1;}
		echo "\"$status$bizName\",$db,$msgsizeKB,$msgcount,$errorsizeKB,$errorcount,$newErrors$dropped<br>";	
	}
	


if(FALSE) {
	if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
	$apptids = fetchCol0("SELECT DISTINCT appointmentptr FROM tblappointmentprop WHERE property = 'visitphotocacheid' OR property LIKE 'button_%'");
	//$mvapptids = fetchCol0("SELECT DISTINCT appointmentptr FROM tblgeotrack WHERE event = 'mv'");
	//$apptids = array_unique(array_merge($apptids, $mvapptids));
	if(!$apptids) continue;
	$start = '2016-06-01';
	$end = '2016-06-30';
	$numVisits = fetchRow0Col0(
		"SELECT COUNT(*) FROM tblappointment 
		WHERE date >= '$start' AND date <= '$end' 
			AND appointmentid IN (".join(',', $apptids).")");
	if(!$started) echo "Visit reports from $start to $end<p>";
	$started = true;
	if($numVisits) {
		echo "$bizName ($db):  $numVisits<br>";
	}
}

	if(FALSE) {
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
		if(!fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'optionEnabledMailChimpButton'")) {echo ""; continue;}
		if($numRequests) echo "$bizName ($db): has MailChimp enabled<br>";
		
	}
	if(FALSE) {  // Find bizzes with old billing flags
		if(!in_array('tblpreference', $tables)) continue;
		$prefix = 'art/billing-block/billflag-';
		$vs = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE value like '$prefix%png%'");
		$rs = array();
		foreach($vs as $k => $v) {
			$n = substr($v, strlen($prefix), strpos($v, '.')-strlen($prefix));
//echo substr($v, strpos($v, '|'));exit;			
			$rest = strpos($v, '|') ? substr($v, strpos($v, '|')) : '';
//echo $rest;exit;			
			$rs[$k] = 'art/billing-block/number-'.$n.'.svg'.$rest;
//echo $rs[0];exit;			
		}
		if($vs) echo "<hr>$bizname ($db):<br>".join(', ', $vs)."<br>".join(', ', $rs);
		foreach($vs as $k=>$v) {
			$sql = "UPDATE tblpreference SET value = '".mysqli_real_escape_string($rs[$k])."' WHERE property = '$k'";
			echo "<br>".$sql;
			doQuery($sql, 1);
			echo " : ".(mysqli_error() ? mysqli_error() : 'OK');
		}
	}
	
	if(FALSE) {  // Find bizzes with dup recurring visits created by System
		if(!in_array('tblpreference', $tables)) continue;
		$labels = null;
		foreach(fetchCol0("SELECT label FROM tblservicetype") as $label)
			if(strpos($label, "'") !== FALSE) $labels[] = $label;
		if($labels) echo "<p>$bizname ($db): ".join(', ', $labels);
		
	}
	
	if(FALSE) {  // Find bizzes with dup recurring visits created by System
		if(!in_array('tblpreference', $tables)) continue;
		require_once "client-flag-fns.php";
		$bizFlags = getBizFlagList();
		if(!$started) echo "<h2>Biz Flags In Use By Active Businesses</h2><h3>With counts of instances in active sitters</h3>[p] = Office Use Only (private)<p>";
		$started = true;
		if($bizFlags) {
			$activeClientIds = fetchCol0("SELECT clientid FROM tblclient WHERE active = 1");
			$flags = array();
			$instances = 0;
			if(!$activeClientIds) {echo "<p><b>$bizName ($db) NO ACTIVE CLIENTS</b>";continue;}
			foreach($bizFlags as $flag) {
				$label = $flag['officeOnly'] ? '[p]' : '';
				$label .= $flag['title'] ? $flag['title'] : basename($flag['src']);
				//flag_3 	=> 28|
				$count = fetchRow0Col0(
					"SELECT count(*) FROM tblclientpref WHERE clientptr IN (".join(',', $activeClientIds).")"
						." AND property LIKE 'flag_%' AND value LIKE '{$flag['flagid']}|%'");
				$instances += $count;
				$flags[] =  "$label($count)";
			}
			echo "<p><b>$bizName ($db) ".count($bizFlags)." flags, ".number_format($instances)." instances</b><br>";
			echo join(", ", $flags);
			$flagUsers += 1;
		}
		if(!function_exists('postProcess')) {
			function postProcess() {global $flagUsers; echo "<hr>Bizzes using flags: $flagUsers;";}
		}
	}
		



if(FALSE) {
	// count current monthly contracts in active businesses
	if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
	$minContracts = 10;
	$minVisits = 100;
	$contracts = fetchRow0Col0("SELECT count(*) FROM tblrecurringpackage WHERE monthly = 1 AND current = 1");
	if($contracts < $minContracts) continue;
	$monthlies += 1;
	echo "$bizName ($db) $contracts contracts<br>";
	if(!function_exists('postProcess')) {
		function postProcess() {
			global $contracts;
			echo "<hr><b>Total monthly bizzes found:</b> $monthlies";
		}
	}
}
if(FALSE) {
	require_once "preference-fns.php";
	if(fetchPreference('sittersPaidHourly')) {
		echo "$bizName ($db) paid hourly<br>";
	}
}
if(FALSE) { // check for visits that are canceled but still have current billables
	$visits = fetchAssociations(
		"SELECT a.* 
			FROM tblappointment a
			LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
			WHERE canceled IS NOT NULL AND date > '2016-01-01' AND billableid IS NOT NULL");
	if($visits) {
		echo "<p><hr><b>$bizName (DB: $db)</b><p>";
		foreach($visits as $v) {
			$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$v['clientptr']} LIMIT 1");
			echo "{$v['date']} {$v['timeofday']} ({$v['appointmentid']}) $client [last modified {$v['modified']}]<br>";
		}
	}
	$totalVisits += count($visits);
	if(!function_exists('postProcess')) {
		function postProcess() {
			global $totalVisits;
			echo "<hr><b>Total visits found:</b> $totalVisits";
		}
	}
}

if(FALSE) { 
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		if(TRUE || fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableStaleVisitNotifications' LIMIT 1")) {
			echo "$db: ";
			if(in_array('tblstaleappointment', $tables)) echo "already has tblstaleappointment.";
			else {
				echo "MISSING tblstaleappointment.<br>";
				doQuery(
				"CREATE TABLE IF NOT EXISTS `tblstaleappointment` (
				  `appointmentptr` int(11) NOT NULL,
				  `notificationdate` datetime NOT NULL,
				  PRIMARY KEY (`appointmentptr`)
				 ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
				");
				echo "Added tblstaleappointment";
				
			}
			echo "<br>";
		}

}
if(FALSE) { // find users of overdue requests
	if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
	if(!fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableStaleVisitNotifications'")) {echo ""; continue;}
	$numRequests = fetchRow0Col0("SELECT COUNT(*) FROM tblclientrequest WHERE extrafields LIKE '%Overdue Visits%'");
	if($numRequests) echo "$bizName ($db): $numRequests Overdue visit notifications<br>";
}

if(FALSE) { // find active pet counts for active clients
	if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
	if(!in_array('tblpet', $tables)) {echo ""; continue;}
	$dbcounts = array();
	$dbpets = 0;
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE active=1");
	$allclients += count($clientids);
	foreach($clientids as $clientid) {
		$numpets = fetchRow0Col0("SELECT COUNT(*) FROM tblpet WHERE ownerptr = $clientid AND active=1");
		$dbpets += $numpets;
		$allpets += $numpets;
		$dbcounts[$numpets] += 1;
		$allcounts[$numpets] += 1;
	}
	echo "<p><b>$bizName ($db)</b> pets: $dbpets clients: ".count($clientids).":<br>";
	asort($dbcounts);
	$dbcounts = array_reverse($dbcounts, 1);
	foreach($dbcounts as $numPets => $numClients)
		echo "Clients with $numPets: $numClients<br>";
		
	if(!function_exists('postProcess')) {
		function postProcess() {
			global $allclients, $allpets, $allcounts;
			echo "<p><b>TOTALS</b> pets: $allpets clients: $allclients:<br>";
			asort($allcounts);
			$allcounts = array_reverse($allcounts, 1);
			foreach($allcounts as $numPets => $numClients)
				echo "Clients with $numPets: $numClients<br>";
		}
	}
}

if(FALSE) {
	if(!in_array('tblinvoice', $tables)) {echo ""; continue;}
	require_once "remote-file-storage-fns.php";
	
	//$stats = getFileCacheStats();
	$nativeApp = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableNativeSitterAppAccess' LIMIT 1");
	/*if($stats['error'] && $nativeApp) {
		echo "$db has Native App without file cache<br>";
		echo "<pre>";
		//echo "<h2>Cache</h2>";
		//print_r(getFileCacheStats());
		echo "<h3>Actual</h3>";
		print_r(getActualStorageStats($bizptr));
		echo "</pre><p>";
	}
	else if($nativeApp) {
		echo "$db file cache stats:<br><pre>";
		print_r(getFileCacheStats());
		echo "<h3>Actual</h3>";
		print_r(getActualStorageStats($bizptr));
		echo "</pre><p>";
	}*/
	$stats = getActualStorageStats($bizptr);
	$stats['nativeapp'] = $nativeApp ? '[NATIVE]' : '';
	$stats['outboarded'] = remoteCacheAvailable() ? '[OUTBOARDED]' : '';
	$stats['bizname'] = "$bizName ($db)";
	$stats['totalsize'] = $stats['localvisitsstorage'] + $stats['localpetsstorage'];
	$stats['totalcount'] = $stats['visitphotostotal'] + $stats['petphotostotal'];
	if(in_array($bizptr, $formerclients)) $stats['former'] = "<b>FORMER</b>";
	$allStats[] = $stats;
	if(!function_exists('postProcess')) {
		function sortBySize($a, $b) {return $a['totalsize'] < $b['totalsize'];}
		function postProcess() {
			global $allStats;
			usort($allStats, 'sortBySize');
			foreach($allStats as $stats) {
				$sizeInKB = number_format(round($stats['totalsize']/1024)).' K';
				/*if($stats['former']) {
					$cumulative += round($stats['totalsize']/1024);
					$cumulativeInKB = number_format($cumulative).' K';
					echo "{$stats['bizname']} {$stats['nativeapp']} {$stats['outboarded']} {$stats['former']} Storage: $sizeInKB in {$stats['totalcount']} files (cumulative: $cumulativeInKB)<br>";
				}
				else continue; */
				$cumulative += round($stats['totalsize']/1024);
				$cumulativeInKB = number_format($cumulative).' K';
				echo "{$stats['bizname']} {$stats['nativeapp']} {$stats['outboarded']} {$stats['former']} Storage: $sizeInKB in {$stats['totalcount']} files (cumulative: $cumulativeInKB)<br>";
			}
		}
	}
}

if(FALSE) { // show message counts for today
	if(in_array('tblclientrequestprop', $tables)) {echo "$db already has tblclientrequestprop<br>"; continue;}
	doQuery(
"CREATE TABLE IF NOT EXISTS `tblclientrequestprop` (
  `requestptr` int(11) NOT NULL,
  `property` varchar(20) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`requestptr`,`property`),
  KEY `request` (`requestptr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
	echo "$db: ADDED tblclientrequestprop<br>";
}

if(FALSE) { // show message counts for today
	if(!in_array('tblcreditcarderror', $tables)) {echo ""; continue;}
	$pattern = "ResponseCode=12&"; //ResponseCode=12& // ErrorCode=50012
	if($found = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime LIKE '2015-12-31%'")) // time > '2015-10-01 00:00:00' AND 
		echo "<br>[{$biz['bizid']}] $db messages: $found";
}

if(FALSE) { // find ACH errors for today
	if(!in_array('tblcreditcarderror', $tables)) {echo ""; continue;}
	$pattern = "ResponseCode=12&"; //ResponseCode=12& // ErrorCode=50012
	if($found = fetchRow0Col0("SELECT count(*) FROM tblcreditcarderror WHERE response LIKE '$pattern%'")) // time > '2015-10-01 00:00:00' AND 
		echo "<br>[{$biz['bizid']}] $db has $found error \"$pattern\" in total";
}

if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {global $smallText;echo "DBs with small queued message body: $smallText;";}
		}
	
		$desc = fetchAssociations("DESC tblqueuedemail");
		if($desc[3]['Type']=='text') {
			if($_REQUEST['update']) {
				echo "$db tblqueuedemail changing body...";
				doQuery("ALTER TABLE `tblqueuedemail` CHANGE `body` `body` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL",1);
				echo "<br>";
			}
			$smallText += 1;
		}
		//else echo "$db tblqueuedemail body type: {$desc[3]['Type']}<br>";
}

if(FALSE) {  // Find bizzes in New York State
	require_once "preference-fns.php";
	if(!in_array('tblpreference', $tables)) continue;
	$bizState = strtoupper($biz['state']);
	if($bizState && $bizState != 'NY' && $bizState != 'NEW YORK') continue;
	$addr = fetchPreference('bizAddress');
	$addr = explode(' | ', $addr);
	$addState = trim(strtoupper($addr[3]));
	if($bizState != 'NY' && $addState && $addState != 'NY' && $addState != 'NEW YORK') continue;
	echo "[$bizState] $bizName ($db) [{$addr[2]}], [$addr[3]]<br>";
}

if(FALSE) {  // Find bizzes with dup recurring visits created by System
	if(!in_array('tblpreference', $tables)) continue;
	require_once "client-flag-fns.php";
	updateBillingFlags();
}

if(FALSE) {  // Find bizzes with dup recurring visits created by System
	if(!function_exists('showDupCount')) {
		function showDupCount($start, $end=null) {
			$clients = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE active = 1 ORDER BY lname, fname", 'clientid');;
			foreach($clients as $clientid => $client) {
				//global $createdBy, $totalDoomed, $minDate, $maxDate;
				$dups = array();
				$dupIds = array();
				$dateRange = array();
				$start = '2015-12-02';
				if($start) $dateRange[] = "AND date >= '$start'";
				if($end) $dateRange[] = "AND date >= '$end'";
				$dateRange = join(' ', $dateRange);
				$appts = fetchAssociations($sql = "SELECT date, appointmentid, pets, canceled, servicecode, starttime, modified FROM tblappointment 
						WHERE clientptr = $clientid AND recurringpackage = 1 $dateRange AND createdby = 0", 1); //  AND modified IS NULL
			//echo "$clientid: $sql ".count($appts)."<br>";
				foreach($appts as $appt) {
					//$appt['date'] = date('m/d/Y', strtotime($appt['date']));
					$appt['date'] = date('m/d/Y', strtotime($appt['date']));

					$modified = $appt['modified'] ? 1 : 0;
					unset($appt['modified']);
					$id = $appt['appointmentid'];
					unset($appt['appointmentid']);

					$mods = ($canceled = $appt['canceled'])  ? '*' : '';
					unset($appt['canceled']);
					if($modified) $mods .= "#";
					$mods = $mods ? "<font color=red>$mods</font>" : '';
					$str = "{$appt['date']}|".print_r($appt,1);
			//echo "$str<br>";		
					$dups[$str][] = "$id$mods";
					if($canceled) $canned[$str] += 1;
				}
				foreach($dups as $str => $ids) if(count($ids) > 1) {
					$dupsGroups += 1;
					$dupsDates[substr($str, 0, strpos($str, '|'))] = 0;
				}
			}
			global $db, $bizName, $totalBizzes;
			if($dupsGroups) {
				echo "$bizName ($db) as $dupsGroups sets of duplicates; ".join(', ', array_keys($dupsDates))."<br>";
				$totalBizzes += 1;
			}
		}
		
		function postProcess() {
			global $totalBizzes;
			echo "<hr>$totalBizzes have duplicate visits.";
		}
	}
	if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) continue;
	showDupCount($start, $end=null);

}
if(FALSE) {  // Report Today's Rollover numbers
		if(!$biz['activebiz'] || $biz['test'] || !in_array('tblgratuity', $tables)) continue;
		$yesterday = date('Y-m-d', strtotime("-1 day"));
		$x = fetchRow0Col0(
			"SELECT note FROM tblchangelog 
				WHERE itemtable = 'tblrecurringpackage' AND note LIKE 'ROLLOVER%' AND time > '$yesterday 13:00:00' LIMIT 1");
		if(!$x) echo "$db: NO ROLLOVER<br>";
		else {
			$num = trim(substr($x, strlen('ROLLOVER FINISHED:')));
			$num = explode(' ', $num);
			$total += $num[0];
			if($num[0]) {
				echo "$db: {$num[0]}<br>";
				$rolloverBizzes += 1;
			}
		}
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $total, $rolloverBizzes;
				echo "$total visits were created for $rolloverBizzes businesses.";
			}
		}
}


if(FALSE) {  // Aggregated gratuities by month
		if($biz['test'] || !in_array('tblgratuity', $tables)) continue;
		$result = doQuery("SELECT issuedate, amount FROM tblgratuity ORDER BY issuedate");
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$monthYear = date('Y-m-01', strtotime($row['issuedate']));
			//$dbtotals[$monthYear][$row['requesttype']] += 1;
			//if(!$completeFound && strpos($row['requesttype'], 'omplete')) {$completeFound = 1; echo "$db has ({$row['requesttype']}) requests.<br>";}
			$allTotals[$monthYear] += $row['amount'];
		}
		//echo "$db: ".print_r($dbtotals, 1)."<br>";
		//exit;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allTotals;
				ksort($allTotals);
				echo "Month,Amount<br>";
				foreach($allTotals as $month => $total) {
					echo "$month,$total<br>";
				}
			}
		}
}

if(FALSE) { // find derelict e-payments
	set_time_limit(30 * 60);
	if(!in_array('tblappointment', $tables)) {echo ""; continue;}
	$missing = array();
	$allTransactions = fetchAssociations(
		"SELECT * FROM tblchangelog WHERE note LIKE 'Approved%' AND time > '2015-11-05' AND itemtable NOT LIKE '%refund'");

	foreach($allTransactions as $i => $trans) {
		$parts = explode('|', $trans['note']);
		$allTransactions[$i]['transid'] = $parts[1];
		$parts = explode('-', $parts[0]);
		$allTransactions[$i]['amount'] = $parts[1];
	}

	foreach($allTransactions as $i => $trans) {
		$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE issuedate >= '2015-11-05 00:00:00' AND externalreference LIKE '%{$trans['transid']}' LIMIT 1"); 
		if(!$credit) {
			$tbl = strpos($trans['itemtable'], 'cc') !== FALSE ? 'tblcreditcard' : 'tblecheckacct';
			$idfield = $tbl == 'tblcreditcard' ? 'ccid' : 'acctid';
			$trans['client'] = fetchRow0Col0($sql =
				"SELECT CONCAT_WS(', ', lname, fname) 
					FROM $tbl
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE $idfield = {$trans['itemptr']}
					LIMIT 1");
			$missing[] = $trans;
		}
	}
	if(!$missing) continue;
	echo "<h2>E-payments not registered in Credits for $db</h2>";
	foreach($missing as $trans) echo "{$trans['time']} {$trans['client']} {$trans['amount']}<br>";
}


if(FALSE) {  // find epayments with gratuities  11/10/2015 - 11/16/2015
	$csv = $_GET['csv'];
	if(!in_array('tblappointment', $tables)) {echo ""; continue;}
	$sql = "SELECT p.issuedate, p.amount, CONCAT_WS(' ', fname, lname) as name, creditid, externalreference
					FROM tblgratuity
					LEFT JOIN tblcredit p ON creditid = paymentptr
					LEFT JOIN tblclient ON p.clientptr = clientid
					WHERE (p.sourcereference LIKE 'CC:%' OR p.sourcereference LIKE 'ACH:%') AND
						p.issuedate > '2015-11-10 00:00:00' 
					GROUP BY creditid
					ORDER BY name"; //AND p.issuedate <  '2015-11-17 00:00:00'
	$credits = fetchAssociations($sql);
	foreach($credits as $i => $credit) {
		$transactionId = substr($credit['externalreference'], strpos($credit['externalreference'], ': ')+2);
		$note = fetchRow0Col0("SELECT note FROM tblchangelog WHERE note LIKE '%|{$transactionId}|%' LIMIT 1");
		$credits[$i]['externalreference'] = $transactionId;
		if($note) {
			$note = explode('|', $note);
			$note = $note[0] ? substr($note[0], strpos($note[0], '-')+1) : '';
			$credits[$i]['echarge'] = $note;
		}
		$tips = fetchRow0Col0("SELECT sum(amount) FROM tblgratuity WHERE paymentptr = {$credit['creditid']} LIMIT 1");
		$credits[$i]['total tips'] = $tips;
	}
	$CSVBIZ = $csv ? 'Business,' : '';
	$cols = explode(',', "{$CSVBIZ}date,credit amt,client,credit ID,transactionid,echarge,total tips");
	if(!$alreadyStarted) { 
		if($csv) echo join(',', $cols);
		else echo "<table border=1 bordercolor=black><tr><td>".join('<td>', $cols); 
		$alreadyStarted=1;
	}
	if($credits) {
		$found +=  count($credits);
		echo $csv ? '' : "\n<tr><td colspan=4><b>{$biz['bizname']} $db</b>"; // "\n<br>{$biz['bizname']} $db"
		foreach($credits as $credit) {
			if($csv) echo "\n<br>{$biz['bizname']},".join(',', $credit);
			else {
				if($credit['amount']+$credit['total tips'] != $credit['echarge']) $credit['amount'] = "<font color=red>{$credit['amount']}</font>";
				echo "\n<tr><td>".join('<td>', $credit);
			}
		}
	}
	if(!function_exists('postProcess')) {
		//echo "BizFiles Usage|Photos|pets|fromClient|display|appts";
		function postProcess() {
			global $csv, $found;
			if(!$csv) echo "\n</table>\nFound $found credits with gratuities";
			else echo "\n<p>Found $found credits with gratuities";
		}
	}
}

if(FALSE) { // find pigs
	if(!in_array('tblpreference', $tables)) {echo ""; continue;}
	if($found = fetchRow0Col0("SELECT value FROM tblpreference WHERE property ='recurringScheduleWindow' and value > 90")) // time > '2015-10-01 00:00:00' AND 
		echo "<br>{$biz['bizid']} $db has a $found day lookahead";
}
if(FALSE) { // find ACH errors for today
	if(!in_array('tblcreditcarderror', $tables)) {echo ""; continue;}
	if($found = fetchRow0Col0("SELECT count(*) FROM tblcreditcarderror WHERE response LIKE 'ErrorCode=50029%'")) // time > '2015-10-01 00:00:00' AND 
		echo "<br>{$biz['bizid']} $db has $found ACH errors today";
}
if(FALSE) { // group businesses by gateway
	if(!in_array('tblappointment', $tables)) {echo ""; continue;}
	if(file_exists("bizfiles/biz_{$biz['bizid']}/photos/appts"))
		echo "<br>{$biz['bizid']} $db has appts folder";
}
if(FALSE) { // group businesses by gateway
// https://leashtime.com/cc-transaction-history-multi.php?bydate=1
	if(!function_exists('postProcess')) {
		//echo "BizFiles Usage|Photos|pets|fromClient|display|appts";
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		function postProcess() {
			global $gateways;
			foreach($gateways as $gateway => $bizzes) {
				sort($bizzes);
				echo "<p>$gateway: (".count($bizzes).") ".join(', ', $bizzes);
			}
		}
	}
	if($g = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway' LIMIT 1")) {
		if(fetchFirstAssoc("SELECT errid FROM tblcreditcarderror WHERE `time` LIKE '2015-10-15%' LIMIT 1"))
			$gateways[$g][] = $bizName ? $bizName : "($db)";
	}
}



if(FALSE) { 
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		if(TRUE) {
			echo "$db: ";
			if(in_array('reldedicatedpayment', $tables)) echo "already has reldedicatedpayment.";
			else {
				echo "MISSING reldedicatedpayment.<br>";
				doQuery(
				"CREATE TABLE IF NOT EXISTS `reldedicatedpayment` (
  `dedicatedpaymentid` int(11) NOT NULL AUTO_INCREMENT,
  `clientptr` int(11) NOT NULL,
  `expensetable` varchar(20) NOT NULL,
  `expenseptr` int(11) NOT NULL,
  `paymentptr` int(11) NOT NULL,
  PRIMARY KEY (`dedicatedpaymentid`),
  UNIQUE KEY `uniqeindex` (`expensetable`,`expenseptr`,`paymentptr`),
  KEY `expenseindex` (`expensetable`,`expenseptr`),
  KEY `paymentindex` (`paymentptr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");
				echo "Added reldedicatedpayment";
				
			}
			echo "<br>";
		}

}



if(FALSE && $_GET['reduce']) { // OVERWRITE photos OF PETS OWNED BY INACTIVE CLIENTS WITH THEIR DISPLAYSIZE COUNTERPARTS
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		if($db != $_GET['reduce']) {
			continue;
		}
		$photosForInactiveClients = fetchAssociations(
			"SELECT photo, clientid, tblclient.active, CONCAT_WS(' ', fname, lname) as owner, tblpet.name as petname
				FROM tblpet
				LEFT JOIN tblclient ON clientid = ownerptr
				WHERE photo IS NOT NULL AND tblclient.active != 1");
			$f = "bizfiles/biz_$bizptr/photos/pets";
		if($photosForInactiveClients) {
			$savings = 0;
			foreach($photosForInactiveClients as $client) {
				$photo = $client['photo'];
				if(file_exists($photo)) {
					$fullFileSize = round(filesize($photo)/1024);
					$displayName = "$f/display/".basename($photo);
					if(file_exists($displayName)) {
						$displaySize = round(filesize($displayName)/1024);
						$success = copy($displayName, $photo);
						if($success) {
							echo "<br>Copied ".basename($photo)." display (".number_format($displaySize)."KB) over full size (".number_format($fullFileSize)."KB) "
									."yielding ".number_format($fullFileSize-$displaySize)."KB";
							echo " -- {$client['owner']}/{$client['petname']}";
							$savings += $fullFileSize-$displaySize;
						}
						else echo "<br>Failed copying $displayName over $photo";
					}
					else echo "<br>display size photo $displayName not found.";
				}
				else echo "<br>full size photo $photo not found.";
			}
			echo "<p>$db savings: ".number_format($savings)."KB<p>";

		}
		else echo "Nothing to reduce.";
}

if(FALSE && $_GET['check']) { // DETERMINE DISK USAGE FOR PHOTOS OF PETS OWNED BY INACTIVE CLIENTS
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		if(!$boink) {
			echo "Total filesize for photos of pets with inactive owners (in KB)";
			echo "<br><a href='#sorted'>Jump to Sorted table</a>";
			echo "<br>Business|full size|display size";
			$boink=1;
		}
		$photosForInactiveClients = fetchCol0(
			"SELECT photo, clientid, tblclient.active
				FROM tblpet
				LEFT JOIN tblclient ON clientid = ownerptr
				WHERE photo IS NOT NULL AND tblclient.active != 1");
			$f = "bizfiles/biz_$bizptr/photos/pets";
		if($photosForInactiveClients) {
			$totalFileSize = 0;
			$totalDisplaySize = 0;
			foreach($photosForInactiveClients as $photo) {
				if(file_exists($photo)) $totalFileSize += round(filesize($photo)/1024);
				$displayName = "$f/display/".basename($photo);
				if(file_exists($displayName)) $totalDisplaySize += round(filesize($displayName)/1024);
			}
			$photosForActiveClients = fetchCol0(
				"SELECT photo, clientid, tblclient.active
					FROM tblpet
					LEFT JOIN tblclient ON clientid = ownerptr
					WHERE photo IS NOT NULL AND tblclient.active = 1");
			if($photosForActiveClients) {
				$totalActiveFileSize = 0;
				foreach($photosForActiveClients as $photo) {
					if(file_exists($photo)) $totalActiveFileSize += round(filesize($photo)/1024);
				}
			}
			$totals[] = array('name'=>"$bizName [$db] ($f)", 'inactivetotal'=>$totalFileSize, 'inactivedisplay'=>$totalDisplaySize, 'activetotal'=>$totalActiveFileSize);
			echo "<br>$bizName [$db] ($f)|".number_format($totalFileSize)."|".number_format($totalDisplaySize);
		}
		if(!function_exists('postProcess')) {
			function byInactiveTotal($a, $b) {return $a['inactivetotal'] <= $b['inactivetotal'];}
			function postProcess() {
				global $totals;
				usort($totals, 'byInactiveTotal');
				echo "<a name='sorted'></a><table><tr><th>Name<th>Inactive Client Pets<th>Inactive Display<th>Active Total</tr>";
				foreach($totals as $t) echo "<tr><td>{$t['name']}<td>{$t['inactivetotal']}<td>{$t['inactivedisplay']}<td>{$t['activetotal']}";
				echo "</table>";
			}
		}
}

if(FALSE) { // DETERMINE ACTIVE CLIENT AND PET TOTALS
	if(!$gabbagabba) {echo "BIZ|ACTIVE CLIENTS|ACTIVE PETS OF ACTIVE CLIENTS|STATUS";$gabbagabba=1;}
	if(!function_exists('postProcess')) {
		function postProcess() {global $clientsALL,$petsALL;echo "<p>Totals:|$clientsALL|$petsALL";}
	}
	if(!in_array('tblappointment', $tables)) {echo ""; continue;}
	if($biz['test'] || !$biz["activebiz"]) continue;
	$clientids = fetchCol0("SELECT clientid FROM tblclient WHERE active=1");
	$clients = count($clientids);
	$pets = $clients ? fetchRow0Col0("SELECT count(*) FROM tblpet WHERE active=1 AND ownerptr IN (".join(',', $clientids).")")
					: 0;
	$clientsALL += $clients;
	$petsALL += $pets;
	$status = in_array($biz['bizid'], $formerclients) ? 'FORMER' : '';
	if(!$status && in_array($biz['bizid'], $goldstars)) $status = 'PAYING';
	echo "<br>$inactive$bizName ($db)|$clients|$pets|$status";
}


if(FALSE) { // DETERMINE AGGREGATE PET PHOTO SIZE FOR FULL SIZE PHOTOS
	if(!in_array('tblappointment', $tables)) {echo ""; continue;}
	if(!$gabbagabba) {echo "BIZ|FILESIZE (K)|FILES|REMOTE ENABLED|BIZ STATUS";$gabbagabba=1;}
	$totalBytes = $n = 0;
	foreach(glob("/var/www/prod/bizfiles/biz_{$biz['bizid']}/photos/pets/*") as $f) {
		if(!is_dir($f)) {
			//if(!in_array(strtoupper(substr($f, strrpos($f, '.'))), array('.JPG', '.JPEG', '.PNG')))
			//	echo "<br>MISC: ".$f;
			$n++;
			$totalBytes += filesize($f)/1024;
		}
	}
	$remoteEnabled = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableNativeSitterAppAccess' LIMIT 1");
	$inactive = $biz['activebiz'] ? '' : '<FONT color=red>INACTIVE</font> ';
	$inactive = $inactive ? $inactive : 
		($former = in_array($biz['bizid'], $formerclients) ? '<FONT color=red>FORMER</font> ' : '');
	echo "<br>$inactive$bizName ($db)|".number_format($totalBytes)."|$n|".($remoteEnabled ? 'yes' : 'no')
				."|".($inactive ? $inactive : 'ACTIVE');
}

if(FALSE) { // DETERMINE DISK USAGE FOR PET PHOTOS AND MESSAGE FOR EACH BUSINESS
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		// determine bizfiles usage
		if(!function_exists('dirSize')) {
			//echo "BizFiles Usage|Photos|pets|fromClient|display|appts";
			echo "BizFiles|Total|all pet photos|display size photos|pet photos from client|messages older than 730 days|message body total";
			function dirSize($f) {
				$io = popen ( '/usr/bin/du -sk ' . $f, 'r' );
				$size = fgets ( $io, 4096);
				$size = substr ( $size, 0, strpos ( $size, "\t" ) );
				pclose ( $io );	
				return $size;
			}
		}
		$f = "bizfiles/biz_$bizptr";
		echo "<br>$bizName ($f)|".dirSize($f);
		if(file_exists("$f/photos/pets")) echo "|".dirSize("$f/photos/pets");
		else echo "|";
		if(file_exists("$f/photos/pets/display")) echo "|".dirSize("$f/photos/pets/display");
		else echo "|";
		if(file_exists("$f/photos/pets/fromClient")) echo "|".dirSize("$f/photos/pets/fromClient");
		else echo "|";
		
		
		$days = "-730 days";
		$date = date('Y-m-d', strtotime($days)).' 00:00:00';
		$oldMessages = fetchFirstAssoc(
			"SELECT count(*) as n, round(sum(length(body))/1024) as len 
				FROM tblmessage 
				WHERE datetime < '$date'");
		if($oldMessages) echo '|'.$oldMessages['n'].'|'.$oldMessages['len'];
		
		continue;
		//if($bizptr != 99) continue;
		$f = "bizfiles/biz_$bizptr";
		echo "<br>$bizName ($f)|".dirSize($f);
		//if(!file_exists("$f/photos")) continue;
		//echo "|".dirSize("$f/photos");
		//if(!file_exists("$f/photos/pets")) continue;
		//echo "|".dirSize("$f/photos/pets");
		if(!file_exists("$f/photos/pets/fromClient")) continue;
		echo "|".dirSize("$f/photos/pets/fromClient");
		if(!file_exists("$f/photos/pets/display")) continue;
		echo "|".dirSize("$f/photos/pets/display");
		//if(!file_exists("$f/photos/appts")) continue;
		//echo "<br>$bizName|".dirSize("$f/photos/appts");
}



if(FALSE) {
		if(!in_array('tblappointment', $tables)) {echo ""; continue;}
		if($g = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway' LIMIT 1"))
			if(strpos($g, 'rans')) echo "$db: $g<br>"; // if(strpos($g, 'rans')) 
}
if(FALSE) {
		if(!function_exists('postProcess')) {
			function postProcess() {global $smallText;echo "DBs with small message body: $smallText;";}
		}
	
		$desc = fetchAssociations("DESC tblmessage");
		if($desc[6]['Type']=='text') {
			if($_REQUEST['update']) {
				echo "$db tblmessage changing body...";
				doQuery("ALTER TABLE `tblmessage` CHANGE `body` `body` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ",1);
				echo "<br>";
			}
			else $smallText += 1;
		}
}
if(FALSE) { 
		 		 //  ALTER TABLE `tblcredit` ADD INDEX `clientptrindex` ( `clientptr` )
		 		 // ALTER TABLE `relinvoicecredit` ADD INDEX `creditptrindex` ( `creditptr` ) 
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		$desc = fetchAssociations("DESC tblcredit");
		if($desc[1]['Key']/*clientptr has key*/) continue;
		//$ntblcredit = fetchRow0Col0("SELECT COUNT(*) FROM tblcredit");
		//$nrelinvoicecredit = fetchRow0Col0("SELECT COUNT(*) FROM relinvoicecredit");
		//echo "$db tblcredit rows: $ntblcredit - relinvoicecredit rows: $nrelinvoicecredit<br>";
		doQuery("ALTER TABLE `tblcredit` ADD INDEX `clientptrindex` ( `clientptr` )");
		doQuery("ALTER TABLE `relinvoicecredit` ADD INDEX `creditptrindex` ( `creditptr` )");
		echo "updated $db<br>";
}

if(FALSE) { // determine size in bytes of all message bodies older than 2 years
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		$n = fetchRow0Col0("SELECT SUM(LENGTH(body)) FROM tblmessage WHERE datetime < ADDTIME(NOW(), '-730 0:0:0')");
		echo "$n $db<br>";
}

if(FALSE) { 
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		// global CC payments
		require_once "preference-fns.php";
		$gateway = fetchPreference('ccGateway');
		if(!$gateway) continue;
				
		$start = 		'2015-04-15';
		$end = 		'2015-05-15';
				
		$ccpayments = fetchRow0Col0(
			"SELECT sum(amount) FROM tblcredit 
				WHERE issuedate >= '$start 00:00:00' AND issuedate <= '$end 23:59:59' 
				AND externalreference LIKE 'CC:%' AND sourcereference LIKE 'CC:%'");
				
		$achpayments = fetchRow0Col0(
			"SELECT sum(amount) FROM tblcredit 
				WHERE issuedate >= '$start 00:00:00' AND issuedate <= '$end 23:59:59' 
				AND externalreference LIKE 'ACH:%' AND sourcereference LIKE 'ACH:%'");
				
		$notgold = !in_array($biz['bizid'], $goldstars) ? '###' : '';
		//echo "[$gateway] $bizName ($db) $payments<br>";
		if(!$started) echo "Gateway|Business|Credit Card|ACH<br>";
		$started = 1;
		
		echo "$gateway|$notgold$bizName ($db)|$ccpayments|$achpayments<br>";
				
}
		
if(FALSE) { 
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		if(!$dispatchersWithGI) {
			require "common/init_db_common.php";
			$dispatchersWithGI = 
				fetchAssociationsIntoHierarchy("SELECT bizptr, userid  FROM `tbluser` WHERE active = 1 AND `rights` LIKE 'd%#gi%' AND `rights` LIKE '%#ac%'", array('bizptr', 'userid'));
			$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
			if ($lnk < 1) {
				echo "Not able to connect: invalid database username and/or password.\n";
			}
			$lnk1 = mysqli_select_db($db);
			if(mysqli_error()) echo mysqli_error();
			//print_r($dispatchersWithGI); exit;
			foreach($dispatchersWithGI as $bbizptr => $users) {
				$dispatchersWithGI[$bbizptr] = array_keys($users);;
			}
		}
		$suppressedDispatchersWithGI = 
			!$dispatchersWithGI[$bizptr] ? 0 
			: fetchRow0Col0("SELECT COUNT(*) FROM tbluserpref WHERE property = 'suppressRevenueDisplay' AND userptr IN (".join(',', $dispatchersWithGI[$bizptr]).")");
		if($suppressedDispatchersWithGI) echo "<br>$bizName ($db) has $suppressedDispatchersWithGI with #gi who cannot see revenue.";
}

if(FALSE) { 
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $numEchecks;
				asort($numEchecks, 1);
				$numEchecks = array_reverse($numEchecks);
				foreach($numEchecks as $key => $count) {
					echo "$key: $count<br>";
				}
			}
		}
		if(!in_array('tblecheckacct', $tables)) {echo ""; continue;}
		//doQuery("ALTER TABLE `tblecheckacct` CHANGE `acctnum` `acctnum` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
		$num =  fetchRow0Col0("SELECT count(*) FROM tblecheckacct WHERE active = 1");
		$active = !$biz['test'] && $biz['activebiz'];// && in_array($biz['bizid'], $clients);
		if($num && $active) $numEchecks[$db] = $num;
		//if($numEchecks) echo "$db: $numEchecks<br>";
}


if(FALSE) { // sorted biz list
		if(!function_exists('postProcess')) {
			$starttime = time();
			function postProcess() {
				global $bizClients;
				asort($bizClients);
				echo count($bizClients)."<p>";
				$bizClients = array_reverse($bizClients);
				foreach($bizClients as $url => $num) {
					if(strpos($url, 'htt') !== 0) $url = "<font color=red>$url</font>";
					echo "$url<br>";
				}
			}
		}
	
		if($biz['test'] || !in_array('tblmessage', $tables)) {echo ""; continue;}
		$bizHomePage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
		if($bizHomePage) $bizClients[$bizHomePage] = fetchRow0Col0("SELECT count(*) FROM tblclient WHERE active=1");
		if($bizClients[$bizHomePage] < 50) unset($bizClients[$bizHomePage]);
}

if(FALSE) { 
		if(!function_exists('postProcess')) {
			$starttime = time();
			function postProcess() {
				global $starttime;
				echo "Done in ".(time() - $starttime)." seconds.";
			}
		}
	
		if(!in_array('tblmessage', $tables)) {echo ""; continue;}
		$num =  fetchRow0Col0("SELECT count(*) FROM tblmessage");
		$indexes = fetchAssociationsKeyedBy("SHOW INDEX FROM tblmessage", 'Key_name');
		if($changed == 10) continue;
		if($indexes['originatorindex']) { if($changed) echo "$db: OK<br>";}
		else {
			$t0 = microtime(1);
			doQuery("ALTER TABLE `tblmessage` ADD INDEX `originatorindex` (`originatorid`,`originatortable`)", 1);
			echo "$db ALTERED in ".(microtime(1) - $t0)." seconds<br>";
			$changed += 1;
		}
}

if(FALSE) { 
	// SELECT * FROM tblmessage WHERE ((correspid = 1434 AND correstable = 'tblclient') OR (originatorid = 1434 AND originatortable = 'tblclient'))AND datetime >=  '2015-04-13'
		if(!function_exists('postProcess')) {
					
			function postProcess() {
				$str = fopen("/var/data/slow-log", "r");
				while($line = fgets($str))
					if(strpos($line, "use ") === 0)
						$errorcounts[trim(substr($line, 4, strpos($line, ';')-4))] += 1;
				print_r($errorcounts);echo "<hr>";
				global $numMessages, $markers;
				asort($numMessages, 1);
				$numMessages = array_reverse($numMessages);
				foreach($numMessages as $key => $count) {
					$color = $errorcounts[$key] ? 'red' : 'black';
					$errors = $errorcounts[$key] ? $errorcounts[$key] : '0';
					echo "<font color=$color>{$markers[$key]} $key: $count messages, $errors slow queries</font><br>";
				}
			}
			

		}
		if(!in_array('tblmessage', $tables)) {echo ""; continue;}
		$num =  fetchRow0Col0("SELECT count(*) FROM tblmessage");
		$indexes = fetchAssociationsKeyedBy("SHOW INDEX FROM tblmessage", 'Key_name');
		$markers[$db] = $indexes['originatorindex'] ? '&#x2713;' : '';
		$active = !$biz['test'] && $biz['activebiz'] && in_array($biz['bizid'], $clients);
		if($num && $active) $numMessages[$db] = $num;
		//if($numEchecks) echo "$db: $numEchecks<br>";
}

if(FALSE) { 
		if(!in_array('tblclientrequest', $tables)) continue;
		require_once "preference-fns.php";
		$color = !$biz['timeZone'] ? 'red' : ($biz['timeZone'] != 'America/New_York' ? 'blue' : 'black');
		$prefZone = fetchPreference('timeZone');
		$bizAddress = fetchPreference('bizAddress');
		if(!$prefZone) $color = 'brown';
		$active = $biz['test'] ? '[TEST]' : ($biz['activebiz'] ? '' : '[INACTIVE]');
		if($prefZone != $biz['timeZone']) {
			$action = '';
			if($prefZone && $biz['timeZone'] == 'America/New_York') {
				require "common/init_db_common.php";
				updateTable('tblpetbiz', array('timeZone'=>$prefZone), "bizid = $bizptr", 1);
				$action = "<b> CHANGED TO $prefZone</b>";
			}
			else if(!$prefZone && $biz['timeZone'] == 'America/New_York') {
				//require "common/init_db_common.php";
				setPreference('timeZone',$biz['timeZone']);
				$action = "<b> CHANGED pref TO {$biz['timeZone']}</b>";
			}
			$prefZoneOrState = $prefZone ? $prefZone : "<span style='text-decoration:underline' title='$bizAddress'>{$biz['state']}</span>";
			echo "$active<font color=$color>TimeZone mismatch DB: ($db) ($bizptr) pref: $prefZoneOrState biz: {$biz['timeZone']}.$action</font><br>";
		}
}

if(FALSE) { 
		if(!in_array('tblclientrequest', $tables)) continue;
		deleteTable('tblstaleappointment', "1=1");
		if(mysqli_affected_rows()) echo "DB: $db -- ".mysqli_affected_rows()." rows deleted.<br>";
}

if(FALSE) { 
		if(!in_array('tblclientrequest', $tables)) continue;
		$numTracks = fetchRow0Col0("SELECT count(*) FROM tblgeotrack");
		if($numTracks > 50000) {/*echo "<br>$db tracks: $numTracks";*/continue;}
		if(fetchAssociations("SHOW KEYS FROM `tblgeotrack`")) 
			{echo "... $db.tblgeotrack ($numTracks) has an index.";continue;}
		else {
			$sql = "ALTER TABLE `tblgeotrack` ADD INDEX `appointmentindex` ( `appointmentptr` );";
			doQuery($sql);
			$sql = "ALTER TABLE `tblgeotrack` ADD INDEX `userindex` (`userptr`);";
			doQuery($sql);
			echo "<br>Updated $db";
		}
/*
ALTER TABLE `tblgeotrack` ADD INDEX `appointmentindex` ( `appointmentptr` );
ALTER TABLE `tblgeotrack` ADD INDEX `userindex` (`userptr`);
*/
}

if(FALSE) { 
		if(!in_array('tblclientrequest', $tables) || $db == 'carolinapetcare') continue;
		echo "Updating $db: ";
		doQuery("CREATE TABLE IF NOT EXISTS `tblstaleappointment` (
  `appointmentptr` int(11) NOT NULL,
  `notificationdate` datetime NOT NULL,
  PRIMARY KEY (`appointmentptr`)
 ) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
		echo " done.<br>";
}
if(FALSE) { 
		if(!in_array('tblclientrequest', $tables) || $db == 'carolinapetcare') continue;
		echo "Updating $db: ";
		doQuery("ALTER TABLE `tblpayable` ADD INDEX `itemindex` (`itemptr`,`itemtable`)");
		echo " done.<br>";
}
if(FALSE) { // duplicate cleanup completed 4/17/2015
	if(!$startedTHISJOBBER) {
		echo "<h2>Visits created at about 2am March 10 that look like duplicates</h2>";
		$startedTHISJOBBER = 1;
	}
		
	$dups = 0;
	$out = '';
	$tbd = array();
	$dubious = 0;
	if($biz['test'] || !in_array('tblclientrequest', $tables)) continue;
	
//if($db == 'walkingthedogsfairfax') continue; // REMOVE THIS	
	
	if(!$biz['activebiz']) continue;
	
	$appts = fetchAssociations(
		"SELECT a.*, CONCAT(c.lname, ', ', c.fname) as sortclient , CONCAT(c.fname, ' ', c.lname) as client, IFNULL(nickname, CONCAT(p.fname, ' ', p.lname)) as provider 
			FROM tblappointment a
			LEFT JOIN tblclient c ON clientid = clientptr
			LEFT JOIN tblprovider p ON providerid = providerptr
			WHERE `created` LIKE '2015-03-10 02:%' OR `created` LIKE '2015-03-10 03:%'
			ORDER BY date, timeofday, sortclient", 1);
	if(!$appts) continue;
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	foreach($appts as $i => $appt) {
		$sitter = $appt['provider'] ? $appt['provider'] : 'UNASSIGNED';
		$curr = "{$appt['date']} {$appt['timeofday']} {$appt['client']}/{$appt['pets']} ({$services[$appt['servicecode']]}) -- {$sitter} #MOD#<br>";//{$sitter}
		if($last == $curr) {
			$dups += 1;
			if($appts[$i-1]['provider'] != $appt['provider']) {
				$add = "<font color=blue>$curr</font>";
				$dubious += 1;
				$alldubious += 1;
			}
			else $add = "<font color=red>$curr</font>";
			if($db == 'scalawags') $tbd[] = $appt['appointmentid']; // bellyrubsleesburg
		}
		else $add = $curr;
		$add = str_replace('#MOD#', ($appt['modified'] ? "[{$appt['modified']}]" : ''), $add);
		$last = $curr;
		$out .= $add;
	}
	if(!$dups) continue;
	$alldups += $dups;
	$affectedBizzes += 1;
	if($db == 'tlcpetsittingllc') $out = "tlcpetsittingllc has bad visit data due to x-apple-data-detectors<br>";
	if($dubious) $dubious = "DUBIOUS";
	echo "<p>$db: ($bizName) -- $dups duplicates out of ".count($appts)." visits created. $dubious<br>$out";
	if($tbd) echo "<hr>TO BE DELETED: ".join(',', $tbd)."<hr>";
	if(!function_exists('postProcess')) {
		function postProcess() {
			global $alldups, $affectedBizzes, $alldubious;
			echo "<p>$affectedBizzes businesses affected. $alldups duplicates. $alldubious dubious.";
		}
	}
}
if(FALSE) { 
		if($biz['test'] || !in_array('tblclientrequest', $tables) || !in_array($biz['bizid'], $goldstars)) continue;
		$gateways[fetchRow0Col0("SELECT value  FROM tblpreference WHERE property = 'ccGateway' LIMIT 1")] += 1;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $gateways;
				ksort($gateways);
				foreach($gateways as $key => $total) {
					echo ($key ? $key : 'None').": $total<br>";
				}
			}
		}
		
}
if(FALSE) { // Aggregated Request Counts
		if($biz['test'] || !in_array('tblclientrequest', $tables)) continue;
		$result = doQuery("SELECT received, requesttype FROM tblclientrequest ORDER BY received");
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$monthYear = date('Y-m-1', strtotime($row['received']));
			//$dbtotals[$monthYear][$row['requesttype']] += 1;
			if(!$completeFound && strpos($row['requesttype'], 'omplete')) {$completeFound = 1; echo "$db has ({$row['requesttype']}) requests.<br>";}
			$allTotals[$monthYear][$row['requesttype']] += 1;
			$allTypes[$row['requesttype']] = 1;
		}
		$completeFound = false;
		//echo "$db: ".print_r($dbtotals, 1)."<br>";
		//exit;
		if(!function_exists('postProcess')) {
			function postProcess() {
				global $allTypes, $allTotals;
				ksort($allTotals);
				ksort($allTypes);
				echo "Month,".join(',', array_keys($allTypes))."<br>";
				foreach($allTotals as $month => $totals) {
					$row = array($month);
					foreach($allTypes as $type => $unused) $row[] = $totals[$type];
					echo join(',', $row)."<br>";
				}
			}
		}
		
}
if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		$date = "2015-01-12";
		$count = fetchRow0Col0(
			"SELECT count(*) FROM tblmessage 
				WHERE inbound=0 AND transcribed IS NULL 
				AND datetime >= '$date 00:00:00' AND datetime <= '$date 23:59:59'");
		$totalCount += $count;
		if(!$started) echo "Emails sent on $date:<p>"; 
		$started = true;
		echo "$db: $count<br>";
		if(!function_exists('postProcess')) {function postProcess() {global $totalCount; echo "<hr>Total: $totalCount"; }}
		
}
if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		doQuery("CREATE TABLE IF NOT EXISTS `tbltextbag` (
		`textbagid` int(11) NOT NULL AUTO_INCREMENT,
		`referringtable` varchar(40) DEFAULT NULL,
		`body` mediumtext NOT NULL,
		PRIMARY KEY (`textbagid`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='To hold texts associated with error log, etc' AUTO_INCREMENT=1 ;
	",1);
	echo "Added tbltextbag to : $db<br>";
}

if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		if(!in_array('tblappointmentprop', $tables)) 	echo "missing in: $db<br>";
		else  echo "tblappointmentprop FOUND in: $db<br>";
}



if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		//doQuery("DROP TABLE IF EXISTS `tblappointmentprop`;");
		doQuery("CREATE TABLE IF NOT EXISTS `tblappointmentprop` (
  `appointmentptr` int(11) NOT NULL,
  `property` varchar(20) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`appointmentptr`,`property`),
  KEY `appointment` (`appointmentptr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;",1);
	echo "Added tblappointmentprop to : $db<br>";
}

if(FALSE) {
	if(!$started) echo "Clients with logins<p>business,<b>active clients</b>,<i>inactive clients</i><br>";
	$started = 1;
	if(!in_array('tblpayable', $tables)) continue;
	if($biz['test'] || !$biz['activebiz']) continue;
	if($clients = fetchCol0("SELECT active FROM tblclient WHERE userid IS NOT NULL")) {
		$active = 0; $inactive = 0;
		foreach($clients as $client) 
			if($client['active']) $active += 1; else $inactive += 1;
				
		echo "<u>$bizName($db)</u>,<b>$active</b>,<i>$inactive</i><br>";
	}
}

if(FALSE) { 
	if($labels = fetchCol0("SELECT label FROM tblservicetype WHERE label LIKE '%training%'")) {
		$XX += 1;
		echo "<hr><u>$bizName ($db) training:</u><br>".join(', ', $labels)."<p>";
	}
}

if(FALSE) { 
	if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'suppressTimeFrameDisplayInCLientUI'")) {
		$XX += 1;
		echo "<hr>$bizName ($db) suppressTimeFrameDisplayInCLientUI = TRUE ($XX)<p>";
	}
}

if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		//<img src="https://leashtime.com/art/paypal_paynow.gif"  width="100" height="34" border="0">
		if($a = fetchFirstAssoc("SELECT * FROM tblpreference WHERE value LIKE '%paypal_paynow.gif%'")) {
			$a['value'] = str_replace('width="180" height="64"', 'width="100" height="34"', $a['value']);
			updateTable('tblpreference', $a, "property='{$a['property']}'");
			echo "<hr>$db: ".htmlentities($a['value'])."<p>".$a['value'];
		}
}
if(FALSE) { 
		if(!in_array('tblusergooglevisit', $tables)) {
			echo "<font color=red>$db lacks tblusergooglevisit.<br></font>";
			continue;
		}
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblusergooglevisit ", 'Field');
		if(!$cols['visitdate']) {
			doQuery("ALTER TABLE `tblusergooglevisit` ADD `visitdate` DATE NULL;",1);
			echo "Added tblusergooglevisit.visitdate field in $db.<br>";
		}
		else echo "<b>tblusergooglevisit already has a visitdate field in $db.</b><br>";
};


if(FALSE) { 
	$latestError = fetchFirstAssoc("SELECT * FROM tblerrorlog WHERE message LIKE '%|tbag:%' ORDER BY `time` DESC LIMIT 1");
	if($latestError)
		echo "<hr>$db<br>{$latestError['time']} <b>{$latestError['message']}</b>";
}

if(FALSE) { 
		if(!in_array('tblpayable', $tables)) continue;
		doQuery("CREATE TABLE IF NOT EXISTS `tbltextbag` (
		`textbagid` int(11) NOT NULL AUTO_INCREMENT,
		`referringtable` varchar(40) DEFAULT NULL,
		`body` mediumtext NOT NULL,
		PRIMARY KEY (`textbagid`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='To hold texts associated with error log, etc' AUTO_INCREMENT=1 ;
	",1);
	echo "Added tbltextbag to : $db<br>";
}

	if(FALSE) { // ROLLOVER CHECK
		if(!in_array('tblpayable', $tables)) continue;
		$actflag = !$biz['active'] ? ' (inactive)' : '';
		
		$rows = fetchAssociations("SELECT time, note FROM tblchangelog WHERE itemtable = 'tblrecurringpackage' AND 
			`time` > '2014-10-27 00:00:00' AND note like 'ROLLO%' ORDER BY time DESC", 1);
		//if(!$rows) echo "$db,";
		echo "";
		if($rows) ;//foreach($rows as $row) echo "<p><b>$bizName ($db)</b><br>{$row['time']} - {$row['note']}<br>";
		else echo "<p><b>$bizName ($db)$actflag</b><br><font color=red>No rollover.</font><br>";
	}
	
	if(FALSE) { 
	if($v = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'smtpSecureConnection' and value LIKE 'ssl%'")) 
		echo "$db smtpSecureConnection: $v<br>";
}

if(FALSE) { 
	doQuery("ALTER TABLE `tblserviceagreement` CHANGE `terms` `terms` MEDIUMTEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL ",1);
	echo "Lengthened terms in tblserviceagreement to MEDIUMTEXT: $db<br>";
}

if(FALSE) { 
	if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableProviderTeamSchedule'")) {
		$mobileSitterNOTAppEnabled ++;
		echo "<hr>$bizName ($db) is enabled for Provider Team Schedules<p>";
		if($lastBiz) echo "$mobileSitterNOTAppEnabled bizzes have not enabled Mobile Sitter App.";
	}
}

if(FALSE) {
	$rows = fetchAssociations("SELECT subject FROM tblreminder WHERE subject LIKE '%TOADY%'");
	if(!$rows) continue;
	else {
		echo "<p><b>$bizName ($db):</b> ".count($rows)." occurrences:<br>";
		foreach($rows as $row) echo "{$row['subject']}<br>";
	}	
}

if(FALSE) {
	$rows = fetchAssociations("SELECT label FROM tblservicetype WHERE label LIKE '%Pampered Pet Package (2 Cats)%'");
	if(!$rows) continue;
	else {
		echo "<p><b>$bizName ($db):</b> ".count($rows)." occurrences:<br>";
		foreach($rows as $row) echo "{$row['client']}<br>";
	}	
}

if(FALSE) {
	$rows = fetchAssociations("SELECT tblpet.*, CONCAT_WS(' ', fname, lname) as client FROM tblpet LEFT JOIN tblclient ON clientid = ownerptr WHERE name LIKE '%husky%'");
	if(!$rows) continue;
	else {
		echo "<p><b>$bizName ($db):</b> ".count($rows)." occurrences:<br>";
		foreach($rows as $row) echo "{$row['client']}<br>";
	}	
}

if(FALSE) {
	$rows = fetchAssociations("SELECT * FROM tblchangelog WHERE (itemtable = 'achpayment' OR itemtable = 'ccpayment') AND note LIKE '%No response from Solveras%'");
	if(!$rows) continue;
	else {
		echo "<p><b>$db:</b> ".count($rows)." occurrences:<br>";
		foreach($rows as $row) echo "{$row['time']} {$row['note']}<br>";
	}	
}

if(FALSE) {
	if(!in_array('tblusergooglevisit', $tables)) continue;
	$cols = fetchAssociationsKeyedBy("DESCRIBE tblusergooglevisit ", 'Field');
	if($cols['visitdate']) continue;
	else {
		doQuery("ALTER TABLE `tblusergooglevisit` ADD `visitdate` DATE NULL COMMENT 'added 7/19/2014';",1);
		echo "$db: visitdate added to tblusergooglevisit<br>";
	}	
}

if(FALSE) {
	if(!in_array('tblecheckacct', $tables)) continue;
	$achClients = fetchCol0("SELECT DISTINCT clientptr FROM tblecheckacct ach LEFT JOIN tblclient c ON clientid=clientptr WHERE ach.active=1 AND c.active=1");
	if($achClients) {
		$inactive = $biz['activebiz'] ? '' : " <font color=red>(inactive)</font>";
		echo "<hr><b>$bizName$inactive</b>: ".count($achClients)." ACH clients<br>";
	}
}

if(FALSE) {
	$groupservicetypes = fetchCol0("SELECT label FROM tblservicetype WHERE label LIKE '%group%'");
	if($groupservicetypes)
		echo "<hr><b>$bizName</b><br>";
		echo join(", ", $groupservicetypes);		
}

if(FALSE) {
	$staff = fetchAssociations("SELECT * FROM relstaffnotification");
	if($staff) {
		echo "<hr><b>$bizName</b><br>";
		foreach($staff as $m) {
			$style = strpos($m['eventtypes'], 'r') !== FALSE ? (
				strpos($m['eventtypes'], 'i') === FALSE ? 'style="font-weight:bold;color:red;"' : 'style="font-weight:bold;color:blue;"')
				: '';
			echo "<br>[{$m['userptr']}] {$m['email']} (<span $style>{$m['eventtypes']}</span>)";
			if(strpos($m['eventtypes'], 'r') !== FALSE && strpos($m['eventtypes'], 'i') === FALSE) {
				$eventtypes  = $m['eventtypes'].",i";
				updateTable('relstaffnotification', array('eventtypes'=>$eventtypes), 
						"userptr={$m['userptr']} AND email = '{$m['email']}' AND daysofweek = '{$m['daysofweek']}'"
						." AND timeofday = '{$m['timeofday']}' AND eventtypes = '{$m['eventtypes']}'"
				);
				echo " UPDATED to [$eventtypes]";
			}
		}
	}
	//else echo "<br><span style='color:lightgrey'>$bizName has no staff notifications</span><br>";
}
if(FALSE) {
	require_once "preference-fns.php";
	$numInvoices = fetchRow0Col0("SELECT count(*) FROM tblinvoice WHERE date > '2014-04-01'");
	setPreference('invoicingEnabled', ($numInvoices > 5));
	setPreference('enableInvoicing', null);
	$color = getPreference('invoicingEnabled') ? 'black' : 'gray';
	echo "<br><span style='color:$color;}'>$bizName invoicingEnabled ($numInvoices): ".getPreference('invoicingEnabled')."</span>";
}
if(FALSE) {
	if($biz['test'] || !$biz['activebiz']) continue;
	if(!function_exists('cmpcounts')) {function cmpcounts($x, $y) { return $x['num'] < $y['num'] ? 1 : ($x['num'] > $y['num'] ? -1 : 0); }}
	$nm = mysqli_real_escape_string($bizName);
	$confcounts[] = fetchFirstAssoc("SELECT '$nm' as nm, count(*) as num FROM tblconfirmation");
	if($lastBiz) {
		usort($confcounts, 'cmpcounts');
		foreach($confcounts as $cc) {
			if($cc['num']) {
				$color = $cc['num'] > 10000 ? 'red' : ($cc['num'] > 1000 ? 'blue' : ($cc['num'] > 100 ? 'black' : 'gray'));
				echo "{$cc['nm']} #confirmations: <font color=$color>".number_format($cc['num'])."</font><br>";
			}
		}
	}
}
if(FALSE) {
		require_once "preference-fns.php";
		$enforceProspectSpamDetection = fetchPreference('enforceProspectSpamDetection');
		$useBetaVersionProspectForm = fetchPreference('useBetaVersionProspectForm');
		if($enforceProspectSpamDetection || $useBetaVersionProspectForm) {
			$enforceProspectSpamDetection = $enforceProspectSpamDetection ? 'yes' : 'no';
			$useBetaVersionProspectForm = $useBetaVersionProspectForm ? 'yes' : 'no';
			echo "$bizName enforceProspectSpamDetection: $enforceProspectSpamDetection - useBetaVersionProspectForm: $useBetaVersionProspectForm<br>";
		}
}
if(FALSE) {// surcharge collision policy
		require_once "preference-fns.php";
		if(!fetchPreference('surchargeCollisionPolicy')) {
			setPreference('surchargeCollisionPolicy', 'Apply the smallest charge');
			echo "$bizName surchargeCollisionPolicy set to: [".fetchPreference('surchargeCollisionPolicy')."]<br>";
		}
}
if(FALSE) { 
	if(!fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mobileSitterAppEnabled'")) {
		$mobileSitterNOTAppEnabled ++;
		echo "<hr>$bizName ($db) not enabled for Mobile Sitter App<p>";
		if($lastBiz) echo "$mobileSitterNOTAppEnabled bizzes have not enabled Mobile Sitter App.";
		//$users = fetchCol0("SELECT userptr FROM tbluserpref WHERE property = 'mobileVersionPreferred' AND value = 1");
		//if(!$users) continue;
		//$emails = fetchCol0(
		//	"SELECT email FROM tblprovider WHERE userid IN (".join(',', $users).")");
		//echo "<hr>$bizName ($db)<p>";
		//foreach($emails as $email) echo "$email<br>";
	}
}
if(FALSE) {// email usage
		require_once "preference-fns.php";
		if(fetchPreference('mobileEmailsToClientsReplyToBusinessEmail')) 
			echo "$bizName mobileEmailsToClientsReplyToBusinessEmail<br>";;
}
if(FALSE) {// email usage
		$sql = "SELECT * FROM relstaffnotification WHERE eventtypes LIKE '%r%'" ;
		foreach(fetchAssociations($sql) as $row) echo "{$row['email']} - {$row['eventtypes']}<br>";
		/*$sql = "SELECT * FROM relstaffnotification WHERE eventtypes LIKE '%r%' AND eventtypes NOT LIKE '%t%'" ;
		foreach(fetchAssociations($sql) as $row) echo "{$row['email']} - {$row['eventtypes']}<br>";
		$sql = "UPDATE relstaffnotification SET eventtypes=CONCAT(eventtypes, ',t') WHERE eventtypes LIKE '%r%' AND eventtypes NOT LIKE '%t%'" ;
		doQuery($sql);
		echo mysqli_affected_rows()." rows updated.<hr>";*/
}
if(FALSE) {// email usage
		require_once "preference-fns.php";
		if(strpos(fetchPreference('bizAddress'), 'Reston')) echo "$bizName, ".fetchPreference('bizAddress')."<br>";;
}
if(FALSE) {// email usage
		$date = '2014-03-07'; $prettydate = date('l Y-m-d', strtotime($date));
		if($biz['test'] || $db == 'leashtimecustomers') continue;
		require_once "preference-fns.php";
		if(fetchPreference('emailHost')) continue;
		$emailCount[$db] = fetchRow0Col0("SELECT COUNT(*) FROM tblmessage WHERE inbound=0 AND datetime LIKE '%$date%'");
		echo "$bizName: {$emailCount[$db]}<br>";
		if($lastBiz) echo "TOTAL messages sent on $prettydate: ".array_sum($emailCount);
}
if(FALSE) {//SAGE users
		//if($biz['test']) continue;
		require_once "preference-fns.php";
		$v = fetchPreference('ccGateway');
		if(strtoupper($v) == 'SAGE') echo "$bizName<br>";
}
if(FALSE) {//sittersPaidHourly
		//if($biz['test']) continue;
		require_once "preference-fns.php";
		$sittersPaidHourly = fetchPreference('sittersPaidHourly');
		if($sittersPaidHourly) echo "$bizName<br>";
}
if(FALSE) {//useBetaVersionProspectForm
		//if($biz['test']) continue;
		require_once "preference-fns.php";
		$useBetaVersionProspectForm = fetchPreference('useBetaVersionProspectForm');
		if($useBetaVersionProspectForm) echo "$bizName<br>";
}
if(FALSE) {
		if($db == 'leashtimecustomers') {
			$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
			$liveBizIds = fetchCol0("SELECT garagegatecode 
																	FROM tblclient 
																	WHERE clientid IN (SELECT clientptr 
																											FROM tblclientpref 
																											WHERE property LIKE 'flag_%' AND value like '2|%')");
			echo count($liveBizIds)." live businesses.<p>";
			//foreach(fetchAssociations("SELECT garagegatecode, fname FROM tblclient WHERE garagegatecode IN (".join(',', $liveBizIds).")") as $liv)
			//	echo join(': ', $liv)."<br>";
			sort($liveBizIds);
			echo join(', ', $liveBizIds)."<br>";
			continue;
		}
	
		if($biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $liveBizIds)) ;
		else {
			$stypes = fetchAssociations(
				"SELECT * 
					FROM tblservicetype	
					WHERE active = 1 AND defaultrate > 0 AND defaultcharge > 0");
			foreach($stypes as $type) {
				$numTypes += 1;
				if($type['ispercentage']) $rate = (float)$type['defaultrate'];
				else $rate = ((float)$type['defaultrate'] / (float)$type['defaultcharge']) * 100;
				if($rate < 15) echo "<font color=red>$bizName: $rate {$type['label']}</font><br>";
				else if($rate >= 100) ; //ignore
				else {
					$total += $rate;
					$style = $rate >= 60 ? "font-weight:bold;color:blue;" : ($rate >= 50 ? "color:blue;" : '');
					echo "<span style='$style'><u>$rate</u> {$type['label']}</span><br>";
				}
			}
		}
		if($lastBiz) echo "<p>Average: ".($total/$numTypes);
		//else echo "$bizName: ".($total/$numTypes).'<br>';
}

if(FALSE) {  // find discounted recurring clients
		if($biz['test']) continue;
		require_once "preference-fns.php";
		$bizAddress = fetchPreference('bizAddress');
		$bizAddress = $bizAddress ? array_map('trim', explode('|', $bizAddress)) : '';
		if(TRUE || $bizAddress[4]) {
			$activeClients = fetchRow0Col0("SELECT count(*) FROM tblclient WHERE active = 1");
			$activeSitters = fetchRow0Col0("SELECT count(*) FROM tblprovider WHERE active = 1");
			$sitterStyle = $activeSitters > 20 ? 'font: bold;color:red' : ( 
										 $activeSitters > 10 ? 'font: bold;color:orange' : (
										 $activeSitters > 5 ? 'font: bold;color:blue' : ''));
			$date = '2014-01-23';
			$visitsToday = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE canceled IS NULL AND date = '$date'");
			$threshold = 6;
			$upperLimit = 9;
			$visitThreshold = 5;
			//if($activeSitters >= $threshold && $activeSitters <= $upperLimit && $visitsToday >= $visitThreshold)
				echo "$bizName ($db): {$bizAddress[2]}, {$bizAddress[3]} <b>({$bizAddress[4]})</b> - [$activeClients] clients, <span style='$sitterStyle'>[$activeSitters] sitters</span>, [$visitsToday] visits on $date<br>";
		}
}

if(FALSE) {  // updating database table keys
		//if($db != 'dogslife')continue;
		if($stopNow == 20) continue; 
		$modsMade = 0;
		$allindexes = array(
			'tblappointment'=>array('clientindex'=>'`clientptr`', 'packageindex'=>'`packageptr`'),
			'tblsurcharge'=>array('surchargeindex'=>'`clientptr`'),
			'tblmessage'=>array('correspindex'=>'`correspid`'),
			'tblbillable'=>array('clientindex'=>'`clientptr`', 'itemindex'=>'`itemtable`,`itemptr`')
		);
		
		$notes = array();
		foreach($allindexes as $table => $indexes) {
			$rows = fetchAssociationsKeyedBy("SHOW INDEX FROM $table", 'Key_name');
			if(!in_array($table, $tables)) {
				$notes[] = "<font color='palegreen'>NO $table</font>";
			}
			else {
				foreach($indexes as $indName => $cols) {
					if(!$rows[$indName]) {
						$sql = "ALTER TABLE `$table` ADD INDEX `$indName` ($cols)";
						$notes[] = "$sql";
						if(!doQuery($sql)) $notes[count($notes)-1] .= ' <font color=red>'.mysqli_error().'</font>';
						$modsMade = 1;
					}
					else $notes[] =  "<font color='green'>has $table.$indName</font>";
				}
			}
		}
		if($modsMade) $stopNow++;
		echo "<p><b>$bizName ($db):</b><br>".join('<br>', $notes);
		if($lastBiz || $stopNow == 20) echo "<p><b>Run time: ".(microtime(1) - $scriptStart).' seconds.';
	}
	
	if(FALSE) {  // find discounted recurring clients
		$found = fetchFirstAssoc("SELECT * FROM tblcontact WHERE note LIKE 'alternate cat-sitter for the kitties'");
		if($found) {
			echo "<br>$bizName<br>".print_r($found,1);
		}
		else echo "<br><font color='palegreen'>$bizName</font><br>".print_r($found,1);
	}
	if(FALSE) {  // find discounted recurring clients
		require_once "preference-fns.php";
		$petTypes = fetchPreference('petTypes');
		if($petTypes) foreach(explode('|', $petTypes) as $type)
			$allTypes[] = strtolower($type);
		$allTypes = array_unique((array)$allTypes);
		if($lastBiz) {
			sort($allTypes);
			echo join('<br>', $allTypes);
		}
	}
	if(FALSE) {  // find discounted recurring clients
		require_once "preference-fns.php";
		$rows = fetchCol0("SELECT CONCAT_WS(' ', fname, lname), lname, fname
														FROM tblrecurringpackage p
														LEFT JOIN relclientdiscount d ON p.clientptr = d.clientptr
														LEFT JOIN tblclient c ON c.clientid = p.clientptr
														WHERE p.current = 1 AND d.clientptr IS NOT NULL 
															AND (cancellationdate IS NULL OR cancellationdate > '2013-11-08')
														ORDER BY lname, fname");
		if($rows) {
			echo "<hr><b>$bizName</b><br>";
			foreach($rows as $name) echo "$name<br>";
		}
	}


	if(FALSE && !$biz['test'] /*$db == 'dogsgonewalking'*/) {  // clear email queues
		$testDate = $_GET['date'] ? $_GET['date'] : '2013-07-29';
		if(!function_exists('visitsEqual')) {
			require_once "appointment-fns.php";
			function visitsEqual($a, $b) {
				unset($a['appointmentid']);
				//unset($b['appointmentid']);
				unset($a['completed']);
				unset($b['completed']);
				unset($a['providerptr']);
				unset($b['providerptr']);
				unset($a['canceled']);
				unset($b['canceled']);
				unset($a['custom']);
				unset($b['custom']);
				unset($a['created']);
				unset($b['created']);
				//return $a == $b;
				foreach($a as $k => $v) if($b[$k] != $v) return false;
				return true;
			}
			echo "<h2>Duplicate rollover visits created on $testDate</h2>";
		}
		$lines = array();
		$visits = fetchAssociations( // date = '$testDate' 
			"SELECT * FROM tblappointment 
				WHERE created >= '$testDate 00:00:00' AND created <= '$testDate 11:59:59'
					AND  recurringpackage=1 AND createdby=0");
		$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
		if($visits) $lines[] = "$db: ".count($visits)." visits.<br>";
		$dups = array();
		while($visits) {
			$visit = array_pop($visits);
			foreach($visits as $othr) {
				if(visitsEqual($visit, $othr)) {
					$lines[] = "<font color=red>{$visit['date']} [{$visit['appointmentid']}] {$clients[$visit['clientptr']]} {$visit['timeofday']}</font><br>";
					$dups[] = $visit['appointmentid'];
				}
			}
		}
		if(count($lines) > 1) {
			foreach($lines as $line) echo $line;
			$sql = "DELETE FROM `tblappointment` WHERE appointmentid IN(".join(',', $dups).")";
			doQuery($sql, 1);
			echo "Deleted ".mysqli_affected_rows()." visits.<br>";
		}
	}
	if(FALSE) {  // clear email queues
		require_once "preference-fns.php";
		if($x = fetchPreference("emailBCC"))
			echo "$db: BCC: $x.<br>";
	}
	if(FALSE) {  // clear email queues
		deleteTable("tblqueuedemail", "1=1", 1);
		echo "$db: deleted ".mysqli_affected_rows()." messages.<br>";
	}
	if(FALSE) {
		$errorCount[$db] = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
		$style[$db] = !$biz['activebiz'] ? 'style="background:pink"' : 'style=""';
		if($lastBiz) {
			asort($errorCount);
			$errorCount = array_reverse($errorCount, 'preservekeys');
			echo "<table>";
			foreach($errorCount as $d => $c) echo "<tr {$style[$d]}><td>$d<td>".number_format($c);
			echo "</table>";
		}
	}
	if(FALSE) {
		if($biz['test'] || !$biz['activebiz']) echo "<font color=gray>$db</font><br>";
		else {
			$count = fetchRow0Col0("SELECT count(*)  FROM `tblappointment` WHERE date='2014-03-12' AND canceled IS NULL");
			global $total;
			$total += $count;
			echo "$db: $count <b>$total</b>.<br>";
		}
	}
	if(FALSE) {
		$yes = fetchRow0Col0("SELECT count(*)  FROM `tblclientpref`   WHERE property='autoEmailClientSchedule'");
		if($yes) {
			echo "$db: $yes active clients have autoEmailClientSchedule=1.<br>";
			//deleteTable('tblclientpref', "property='autoEmailClientSchedule'", 1);
		}
	}
	if(FALSE) {
		$yes = fetchRow0Col0("SELECT count(*)  FROM `tblclient`   WHERE active=1 and setupdate IS NOT NULL");
		$no = fetchRow0Col0("SELECT count(*)  FROM `tblclient`   WHERE active=1 and setupdate IS NULL");
		$color = $no ? 'red' : 'blue';
		echo "$db: $yes active clients have setup dates. <span style='color:$color'>$no</span> active clients do not.<br>";
		//echo "<p><b>$bizName ($db)</b><br>";
		//if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
	}
	
	if(FALSE) {
		$found = fetchRow0Col0("SELECT count(*)  FROM `tblgratuity` g  WHERE g.`issuedate` LIKE '1969%'");
		if($found)  echo "$db: $found dateless gratuities found.<br>";
		//echo "<p><b>$bizName ($db)</b><br>";
		//if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
	}
	
	if(FALSE) {
		if(in_array('relproviderzip', $tables))  echo "$db: provider territories enabled.<br>";
		//echo "<p><b>$bizName ($db)</b><br>";
		//if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
	}
	
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$sql = " SELECT *
							FROM (
							SELECT count( * ) AS x, fname, lname, CONCAT('@', clientptr)
							FROM `tblservicepackage`
							LEFT JOIN tblclient ON clientid = clientptr
							WHERE current =1
							GROUP BY clientptr
							) z
							WHERE x >45
							ORDER BY x DESC
							LIMIT 10 ";
		if($rows = fetchAssociations($sql)) {
			echo "<b>$bizName ($db) ".($biz['test'] ? '[TEST]' : '')."</b><br><table>";
			foreach($rows as $row) echo "<tr><td>".join('<td>', $row);
			echo "</table>";
		}
	}

	if(FALSE) {
		require_once "email-template-fns.php";
		$templates = fetchCol0("SELECT label FROM tblemailtemplate WHERE label LIKE '%redential%'");
		if($templates) echo "DB: $db: has email templates".join(', ', $templates).".<p>";
		/*else {
			ensureStandardTemplates('client');
			ensureStandardTemplates('provider');
			echo "DB: $db: ADDED templates.<p>";
		}*/
		//doQuery("DELETE FROM tblemailtemplate WHERE label LIKE '#STANDARD - Invoice Email'");
		//ensureStandardTemplates($type=null);
		//$t = fetchFirstAssoc("SELECT subject, body FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email' AND targettype = 'client'");
		//echo "DB: $db: [{$t['subject']}] CLIENT ".htmlentities($t['body'])."<p>";
		//echo "DB: $db: created new email templates.<p>";
	}





	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		require_once "preference-fns.php";
		$uses = fetchPreference('sittersPaidHourly') ? 'uses' : 'does NOT use';
		$color = fetchPreference('sittersPaidHourly') ? '' : 'lightgrey';
		if(fetchPreference('sittersPaidHourly')) $users++;
		else $losers++;
		echo "<font color='$color'>$bizName was $uses the hourly payroll option.</font><br>";
		if($lastBiz) echo "Users: $users losers: $losers";
		// clientScheduleMakerDays = "6"

	}
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		require_once "preference-fns.php";
		$uses = fetchPreference('showCalendarPageInBanner') ? 'uses' : 'does NOT use';
		$color = fetchPreference('showCalendarPageInBanner') ? '' : 'lightgrey';
		if(fetchPreference('showCalendarPageInBanner')) $users++;
		else $losers++;
		echo "<font color='$color'>$bizName was $uses the calendar option.</font><br>";
		if($lastBiz) echo "Users: $users losers: $losers";
		// clientScheduleMakerDays = "6"

	}
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$found = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientScheduleMakerDays'");
		if($bizptr > 367) {
			require_once "preference-fns.php";
			$not = fetchPreference('showCalendarPageInBanner') ? 'NOT' : '';
			$color = fetchPreference('showCalendarPageInBanner') ? 'lightgrey' : '';
			setPreference('showCalendarPageInBanner', 1);
			echo "<font color='$color'>$bizName was $not updated.</font><br>";
		}
		
		// clientScheduleMakerDays = "6"

	}
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$found = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientScheduleMakerDays'");
		if($found == 1) {
			//updateTable('tblpreference', array('value'=>6), "property = 'clientScheduleMakerDays'", 1);
			echo "$bizName has clientScheduleMakerDays set to '1' but was updated.<br>";
		}
		
		// clientScheduleMakerDays = "6"

	}
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$found = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'sittersCanSendICInvoices'");
		if($found) echo "$bizName has sittersCanSendICInvoices enabled<br>";
	}
	if(FALSE) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblcredit ", 'Field');
		if($cols['created']) continue;
		echo "created column missing in [tblcredit] $db<br>";
	}
	if(FALSE && !$biz['test']) { // turn off OneDay for bizzes that have not used it yet
		if(!in_array('tblcreditcard', $tables)) continue;
		$yes =fetchRow0Col0("SELECT * FROM tblservicepackage WHERE enddate IS NULL");
		if(!$yes) {
			echo "<p><b>$bizName</b> uses OneDay: ".($yes ? '<font color=green>yes</font>' : '<font color=red>no</font>')."<br>";
			require_once "preference-fns.php";
			setPreference('hideOneDayScheduleAtTop', 1);
			setPreference('hideOneDayScheduleAtBottom', 1);
			$offed++;
		}
		if($lastBiz) echo "Turned off OneDay in $offed bizzes.";
	}
	if(FALSE && !$biz['test']) { // identify email templates with relative URLs
		if(!in_array('tblcreditcard', $tables)) continue;
		foreach(fetchAssociations("SELECT * FROM tblemailtemplate") as $t)
			if($start = strpos($t['body'], 'href="'))
				if(substr($t['body'], $start+6, strlen('http')) != 'http') {
					if($lastdb != $db) echo "<p><b>$bizName</b><br>";
					$lastdb = $db;
					echo "{$t['label']}<br>";
				}
	}
	if(FALSE && !$biz['test']) {
		if(!in_array('tblcreditcard', $tables)) continue;
		require_once "appointment-fns.php";
		$changedVisits = fetchCol0("SELECT itemptr
		FROM `tblchangelog`
		WHERE `note` LIKE'%[EZ Schedule service change]%'");
		$changedVisits = array_unique($changedVisits);
		//echo "<table><tr><td>ID<td>Date<td>Time<td>Client<td>Sitter<td>Service<td>Rate<td>Complete";
		$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
		$n = 0;
		foreach($changedVisits as $id) {
			$appt = getAppointment($id, 1);
			if(!$appt) continue;
			if($appt['rate'] > 0) continue;
			if($appt['canceled']) continue;
			$n++;
			//$completed = $appt['completed'] ? '<font color=green>COMPLETED</font>' : ($appt['canceled'] ? '<font color=red>CANCELED</font>' : 'INCOMPLETE');
			$service = $serviceTypes[$appt['servicecode']];
			//echo "<tr><td>[$id]<td>{$appt['date']}<td>{$appt['timeofday']}<td>{$appt['client']}<td>{$appt['provider']}<td>$service<td>{$appt['rate']}<td>$completed";
			//echo print_r($appt,1)."<hr>";
		}
		//echo "</table>";
		if($n) echo "$bizName: $n visits<br>";
	}
		
		
	if(FALSE && !$biz['test']) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$start = date('Y-m-d H:i:s', strtotime("- 6 months"));
		$changeNotes = fetchAssociations(
			"SELECT * 
				FROM tblclientrequest 
				WHERE note IS NOT NULL AND requesttype IN ('change','cancel', 'uncancel') AND received > '$start'");
		if($changeNotes) {
			$bco += 1;
			echo "<hr><b>$bizName (".count($changeNotes)."):</b><br>";
			$colors = explodePairsLine('change|black||cancel|red||uncancel|green');
			foreach($changeNotes as $note) {
				$rtype = "<span style='font-weight:bold;color:{$colors[$note['requesttype']]}'>{$note['requesttype']}</span>";
				echo "$rtype: {$note['note']}<p>";
			}
		}
		if($lastBiz) echo "Found $bco businesses with request notes.";
	}

	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'suppresscontactinfo' LIMIT 1"))
			echo "$bizName: yes<br>";
	}

	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$arr = array("New Year's Day"=>"New Years Day","Martin Luther King Day"=>"MLK Day","New Year's Eve"=>"New Years Eve");
		foreach($arr as $old => $new) {
			$old = mysqli_real_escape_string($old);
			if($key = fetchRow0Col0("SELECT label FROM tblsurchargetype WHERE label LIKE '$old' LIMIT 1")) {
				echo "<p><b>$bizName ($db)</b> has $key".'<br>';
				updateTable('tblsurchargetype', array('label'=>$new), "label = '$old'", 1);
			}
		}
		$country = $biz['country'];
		if($country != 'US' && fetchRow0Col0("SELECT label FROM tblsurchargetype WHERE label LIKE 'MLK Day' LIMIT 1")) {
			echo "<p><b>$bizName ($db)</b> has MLK Day [$country]".'<br>';
			deleteTable('tblsurchargetype', "label = 'MLK Day'", 1);
			
			//doQuery("INSERT INTO `tblsurchargetype` (`label`, `descr`, `date`, `automatic`, `recurring`, `pervisit`, `filterspec`, `defaultrate`, `defaultcharge`, `active`, `permanent`, `menuorder`) VALUES
			//	('MLK Day', NULL, '2012-01-16', 0, 0, 1, NULL, 5.00, 5.00, '0', 1, 4);", 1);
		}
	}
	
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$badVersionFrag = mysqli_real_escape_string("%<table bgcolor=lightblue cellspacing=10 align=center>\n<tr><td>Username:</td><td bgcolor=white%");
		$badVersion = mysqli_real_escape_string("#LOGO#\n\nHi #RECIPIENT#,\n\nHere are your username and password for logging in to view your account with #BIZNAME#.  The password is temporary; the very next time you try to login (whether using this password or not), this password will be erased.  If you login with this password, you will be asked to supply a new permanent password.\n<table bgcolor=lightblue cellspacing=10 align=center>\n<tr><td>Username:</td><td bgcolor=white><b>#LOGINID#</b></td</tr>\n<tr><td>Temp Password:</td><td bgcolor=white><b>#TEMPPASSWORD#</b></td</tr>\n</table>\nIf your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: <a href='https://leashtime.com/login-page.php?bizid=#BIZID#'>https://leashtime.com/login-page.php?bizid=#BIZID#</a> using the forgotten password link.\n\nTo obtain a new password, you will need to supply your username (#LOGINID#) and this email address (#EMAIL#).  Once you do, a new temporary password will be emailed immediately to that email address.\n\nPlease contact us at #BIZEMAIL# or #BIZPHONE# if you have any questions.\n\nThank you,\n\n#MANAGER#");
		$correction = mysqli_real_escape_string("#LOGO#\n\nHi #RECIPIENT#,\n\nHere are your username and password for logging in to view your account with #BIZNAME#.  The password is temporary; the very next time you try to login (whether using this password or not), this password will be erased.  If you login with this password, you will be asked to supply a new permanent password.\n<table bgcolor=lightblue cellspacing=10 align=center>\n<tr><td>Username:</td><td bgcolor=white><b>#LOGINID#</b></td></tr>\n<tr><td>Temp Password:</td><td bgcolor=white><b>#TEMPPASSWORD#</b></td></tr>\n</table>\nIf your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: <a href='https://leashtime.com/login-page.php?bizid=#BIZID#'>https://leashtime.com/login-page.php?bizid=#BIZID#</a> using the forgotten password link.\n\nTo obtain a new password, you will need to supply your username (#LOGINID#) and this email address (#EMAIL#).  Once you do, a new temporary password will be emailed immediately to that email address.\n\nPlease contact us at #BIZEMAIL# or #BIZPHONE# if you have any questions.\n\nThank you,\n\n#MANAGER#");
		$key = fetchRow0Col0("SELECT label FROM tblemailtemplate WHERE body LIKE '$badVersionFrag' LIMIT 1");
		if($key) {
			echo "<p><b>$bizName ($db)</b> has it".'<br>';
		}
	}
	
	if(FALSE) {  // FIND visits where provider has time off -- NONE FOUND
		if(!in_array('tblcreditcard', $tables)) continue;
//if(!dbTEST('houseboundhounds')) continue;
		require_once "provider-fns.php";
		$lines = array();
		$provs = fetchKeyValuePairs("SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as name FROM tblprovider WHERE active = 1");
		foreach($provs as $providerid => $provname) {
			
			if(($timesoff = getProviderTimeOff($providerid, $showpasttimeoff=false))) {
//echo "<hr>{$provs[$providerid]}: ".print_r($timesoff,1);
				foreach($timesoff as $timeoff) {
					$visits = fetchAssociations($sql = 
						"SELECT a.*, c.lname FROM tblappointment a LEFT JOIN tblclient c ON clientptr = clientid 
						WHERE providerptr = $providerid AND date = '{$timeoff['date']}'");
//echo "<br>".print_r($visits, 1);
					foreach($visits as $appt) {
//echo "<br>".print_r("providerIsOff($providerid, {$appt['date']}, {$appt['timeofday']}, $timesoff)", 1);//, $timesoff
						if(providerIsOff($providerid, $appt['date'], $appt['timeofday']))//, $timesoff
							$lines[] = "<tr><td>$provname<td>".shortDate(strtotime($appt['date']))."<td>{$appt['timeofday']}<td>{$appt['lname']}";
					}
				}
				}
		}
		if($lines) {
			echo "<p><b>$bizName ($db)</b>: ".count($lines).'<br>';
			echo "<table><tr><th>Sitter<th>Date<th>Time<td>Service".join('<br>', $lines)."</table>";
		}
	}

	if(FALSE) {
		$threshold = 50;
		if(!in_array('tblcreditcard', $tables)) continue;
		$day1 = date('Y-m-d 00:00:00');
		$dayN = date('Y-m-d 23:59:59');
		
		//$day1 = date('2012-11-20 00:00:00');
		//$dayN = date('2012-11-20 23:59:59');
		
		$emailGroups = fetchKeyValuePairs(
			"SELECT subject, count(*), datetime as x FROM `tblmessage` 
			WHERE inbound = 0 AND transcribed IS NULL AND 
			datetime >= '$day1' AND	datetime <= '$dayN' group by subject", 1);
		$total = 0;
		//echo "<hr>".print_r($emailGroups, 1);
		foreach($emailGroups as $sub => $count) {
			if($count < $threshold) unset($emailGroups[$sub]);
			$total += $count;
		}
		if($emailGroups) {
			echo "<br><b>$bizName ($db)</b>: $total outbound messages today";
			foreach($emailGroups as $sub => $count) echo "<br>[$count] $sub";
		}
	}
	
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$started = false;
		foreach(fetchAssociations("SELECT * FROM tblclient") as $person)
			if(setUserActive($person['userid'], $person['active'])) {
				if(!$started) echo "<br><b>$bizName ($db)</b>:";
				$started = true;
				echo "<br>Client {$person['fname']} {$person['lname']} set ".($person['active'] ? 'active' : 'inactive');
			}
		foreach(fetchAssociations("SELECT * FROM tblprovider") as $person)
			if(setUserActive($person['userid'], $person['active'])) {
				if(!$started) echo "<br><b>$bizName ($db)</b>:";
				$started = true;
				echo "<br>Provider {$person['fname']} {$person['lname']} set ".($person['active'] ? 'active' : 'inactive');
			}
	}
	
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if('strict' != ($x = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeframeOverlapPolicy'"))) {
			echo "<br><b>$bizName ($db)</b>: $x";
		}
	}
	
	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if(!fetchRow0Col0("SELECT property FROM tblpreference WHERE property = 'timeframeOverlapPolicy'")) {
			insertTable('tblpreference', array('property'=>'timeframeOverlapPolicy','value'=>'strict'), 1);
			echo "<br><b>$bizName ($db)</b>: strict";
		}
	}
	
	
	
	
	if(FALSE && in_array($db, array('dogsgonewalking','pawspetcare','goldcoastpetsau'))) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if(in_array('tblunassignedboard', $tables)) echo "$bizName already has table tblunassignedboard<br>";
		else {
			doQuery("CREATE TABLE IF NOT EXISTS `tblunassignedboard` (
  `uvbid` int(11) NOT NULL auto_increment,
  `appointmentptr` int(11) default NULL,
  `packageptr` int(4) default NULL,
  `clientptr` int(4) default NULL,
  `uvbdate` date NOT NULL,
  `uvbtod` varchar(40) default NULL,
  `uvbnote` text,
  `created` datetime NOT NULL,
  `modified` datetime default NULL,
  `createdby` int(11) NOT NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`uvbid`),
  UNIQUE KEY `appointmentid` (`appointmentptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
", 1);
			echo "$bizName ADDED table tblunassignedboard<br>";
		}
		
	}

	if(FALSE) {
		if(!$crap) echo "Exclusive services:<p>";$crap=1;
		if(!in_array('tblcreditcard', $tables)) continue;
		$servs = fetchCol0(			"SELECT label FROM tblservicetype WHERE hoursexclusive = 1");
		if($servs) echo "<br><b>$bizName ($db)</b>: ".join(', ', $servs);
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$pat = '%q%';
		if(count(fetchCol0(
			"SELECT value FROM tblpreference
				WHERE property IN ('defaultReplyTo', 'emailFromAddress') AND value")) < 2)
			echo "<p><b>$bizName ($db)</b>: ".join('<br>', $srvs);
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$pat = '%q%';
		if($srvs = fetchCol0(
			"SELECT CONCAT_WS(' | ', received, fname, lname) FROM tblclientrequest
				WHERE requesttype = 'Prospect' AND fname LIKE '$pat' OR lname LIKE '$pat'"))
			echo "<p><b>$bizName ($db)</b>: ".join('<br>', $srvs);
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if($srvs = fetchCol0("SELECT CONCAT(time, ' ', message) FROM tblerrorlog WHERE message LIKE 'CURL%'"))
			echo "<p><b>$bizName ($db)</b>: ".join('<br>', $srvs);
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if($srvs = fetchCol0("SELECT CONCAT(label, ' [', servicetypeid, ']') FROM tblservicetype WHERE label LIKE 'Group %'"))
			echo "<p><b>$bizName ($db)</b>: ".join(', ', $srvs);
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables) || !$biz['activebiz'] || $biz['test']) continue;
		if($gw = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway'"))
			$gateways[strtoupper($gw)][] = $bizName;
		if(!function_exists('postProcess')) {function postProcess() {global $gateways; foreach($gateways as $gw => $list) echo "<p>$gw: (".count($list).") ".join(', ', $list);}}
	}



	if(FALSE) { // ROLLOVER CHECK
		if(!in_array('tblpayable', $tables)) continue;
		
		$rows = fetchAssociations("SELECT time, note FROM tblchangelog WHERE itemtable = 'tblrecurringpackage' AND `time` > '2012-09-11 00:00:00' AND note like 'ROLLO%' ORDER BY time DESC", 1);
		//if(!$rows) echo "$db,";
		echo "<p><b>$bizName ($db)</b><br>";
		if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
		else echo "<font color=red>No rollover.</font><br>";
	}
	
	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		if(!($smtp = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailHost'"))) continue;
		if($smtp != 'smtp.gmail.com') continue;
		$port = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'smtpPort'");
		$security = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'smtpSecureConnection'");
		$user = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailUser'");
		
		echo "<br>$bizName ($db): SMTP Host: $smtp Port: $port Security: $security User: $user</br>";
		}
	
	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblgeotrack ", 'Field');
		if($cols['error']) continue;
		else {
			echo "<br><font color=gray>$bizName ($db): tblegeotracklacks error column</font>";
			doQuery("ALTER TABLE tblgeotrack ADD `error` varchar(10) default NULL", 1);
			echo "<br>$db: changed table tblgeotrack (added error column).";
		}
	}
	
	if(FALSE) { 
		
		$countExtraPet = fetchRow0Col0("SELECT count(extrapetrate) FROM tblservicetype WHERE extrapetrate > 0");
		
		if($countExtraPet) echo "$bizName ($db) uses ($countExtraPet) extra pet rates.<br>";
	}


	if(FALSE) {
		if(!$biz['test'] && !in_array('tblpreference', $tables)) continue;
		$count = fetchRow0Col0("SELECT count(*) FROM tblclient WHERE active AND prospect");
		if($count) echo "<b>$bizName ($db) prospects:</b> $count<br>";
		//else echo "<font color=gray>$bizName ($db): --<br></font>";
	}
	
	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		$look = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'holidayVisitLookaheadPeriod' LIMIT 1");
		//if($look == 15) echo "$db - lookahead: $look.<br>";
		$cats[$look][] = $db;
		if(!function_exists('postProcess')) {
			function postProcess() {
				echo "<hr><b>Lookaheads:</b><p>";
				global $cats;
				if($cats) {
					$cats[30] = $cats[null];
					unset($cats[null]);
					ksort($cats);
					foreach($cats as $cat => $dbs) {
						$date = date('F j', strtotime("- $cat days", strtotime("7/4/2012")));
						echo "<b>$cat days ($date):</b> ".join(', ', $dbs)."<br>";
					}
				}
			}
		}
	}
	
	if(FALSE) {
		if(!in_array('tblpayable', $tables)) continue;
		
		$rows = fetchAssociations("SELECT time, note FROM tblchangelog WHERE itemtable = 'providerschedules' AND `time` like '2012-06-21%'", 1);
		//if(!$rows) echo "$db,";
		echo "<p><b>$bizName ($db)</b><br>";
		if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
	}
	
	if(FALSE) {
		if(!in_array('tbltimeoffinstance', $tables))  echo "$db,";
		//echo "<p><b>$bizName ($db)</b><br>";
		//if($rows) foreach($rows as $row) echo "{$row['time']} - {$row['note']}<br>";
	}
	
	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		require_once "preference-fns.php";
		foreach(explodePairsLine(
					'visit|confirmApptModificationsProvider||'
					.'reassignvisit|confirmApptModificationsProvider||reassignvisits|confirmApptModificationsProvider||'
					.'deletevisit|confirmApptCancellationsProvider||'
					.'cancelvisit|confirmApptCancellationsProvider||cancelvisits|confirmApptCancellationsProvider||'
					.'uncancelvisit|confirmApptReactivationsProvider||uncancelvisits|confirmApptReactivationsProvider||'
					.'schedule|confirmSchedulesProvider') as $x=>$prop) $props[] = $prop;
		foreach(explodePairsLine(
					'visit|autoEmailApptChangesProvider||'
					.'reassignvisit|autoEmailApptChangesProvider||reassignvisits|autoEmailApptChangesProvider||'
					.'deletevisit|autoEmailApptCancellationsProvider||'
					.'cancelvisit|autoEmailApptCancellationsProvider||cancelvisits|autoEmailApptCancellationsProvider||'
					.'uncancelvisit|autoEmailApptReactivationsProvider||uncancelvisits|autoEmailApptReactivationsProvider||'
					
					.'schedule|autoEmailScheduleChangesProvider') as $x=>$prop) $props[] = $prop;
		echo "<hr><p><b>$bizName:</b><br>";
		foreach(array_unique($props) as $prop) 
			if(fetchRow0Col0("SELECT count(*) FROM tblpreference WHERE property = '$prop'") == 0) {
				echo "$prop will be set to yes<br>";
				setPreference($prop, 1);
			}
	}

	if(FALSE) {
		if(!in_array('tblusergooglevisit', $tables)) echo "$bizName is missing tblusergooglevisit.<br>";
	}
		

	if(FALSE) {
		$bizAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress'");
		$bizAddress = explode(' | ', ''.$bizAddress);
		if($bizAddress) {
			$zip = array_pop($bizAddress);
			$state = array_pop($bizAddress);
		}
		if($zip) echo "$bizName $state, <a href='zipcityedit.php?forzip=$zip&allcapsonly=1' target='zipeditor'>$zip</a><br>";
	}
		

	if(FALSE) {
		if(!in_array('tblpayable', $tables)) continue;
		$newccs = fetchAssociations("SELECT received, tblclient.fname, tblclient.lname, clientid, company, last4 , x_exp_date
										FROM tblclientrequest r
										LEFT JOIN tblclient ON clientid = r.clientptr
										LEFT JOIN tblcreditcard ON tblcreditcard.active = 1 AND tblcreditcard.clientptr = r.clientptr
										WHERE requesttype =	'CCSupplied'
										AND received > '2012-04-29'");
		if($newccs) {
			echo "$bizName ".($newccs ? $newccs : '0')." new credit cards:<br>";
			quickTable($newccs, $extra='border=1', $style=null, $repeatHeaders=0);
		}
	}
	if(FALSE) {
		if(!in_array('tblpayable', $tables)) continue;
		if(in_array('tblcreditcardadhoc', $tables)) {
			echo "$bizName: already as table tblcreditcardadhoc<br>";
			continue;
		}
		$mod = "CREATE TABLE IF NOT EXISTS `tblcreditcardadhoc` (
  `ccid` int(11) NOT NULL auto_increment,
  `last4` varchar(4) NOT NULL,
  `x_exp_date` date NOT NULL,
  `company` varchar(20) default NULL,
  `clientptr` int(11) NOT NULL,
  `created` date NOT NULL,
  `modified` date default NULL,
  `createdby` int(11) NOT NULL,
  `modifiedby` int(11) NOT NULL,
  `gateway` varchar(40) default NULL,
  PRIMARY KEY  (`ccid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		doQuery($mod, 1);
		echo "$bizName ADDED table tblcreditcardadhoc<br>";
		
	}
		
	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		$matches = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE value like '%paypal%'");
		if($matches) {
			echo "<hr>$db<br>";
			foreach($matches as $p => $v) echo "$p:<br>$v<p>";
		}
	}
		
	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz'] ) continue;
		$keyLabelSize = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'keyLabelSize' LIMIT 1");
		if($keyLabelSize) echo "$bizName: <b>$keyLabelSize</b><br>";
		if($keyLabelSize == '1.66 in X 0.9 in') {
			updateTable('tblpreference', array('value'=>'1.66 in X 0.9 in - Clik-It size (9 X 4 labels/sheet)'), "property='keyLabelSize'", 1);
		}
		if($keyLabelSize == '1.4375 in X 0.75 in') {
			updateTable('tblpreference', array('value'=>'1.4375 in X 0.75 in  (9 X 4 labels/sheet)'), "property='keyLabelSize'", 1);
		}
	}

	if(FALSE) {  // ACTIVE CITIES
		if($db == 'leashtimecustomers') {
			$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
			$liveBizIds = fetchCol0("SELECT garagegatecode 
																	FROM tblclient 
																	WHERE clientid IN (SELECT clientptr 
																											FROM tblclientpref 
																											WHERE property LIKE 'flag_%' AND value like '2|%')");
			echo count($liveBizIds)." live businesses.<p>";
			//foreach(fetchAssociations("SELECT garagegatecode, fname FROM tblclient WHERE garagegatecode IN (".join(',', $liveBizIds).")") as $liv)
			//	echo join(': ', $liv)."<br>";
			sort($liveBizIds);
			echo join(', ', $liveBizIds)."<br>";
			continue;
		}

		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, (array)$liveBizIds)) continue;
		// !in_array($bizptr, $liveBizIds))
		$fullAdd = $bizAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress'");
		$url = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage'");
		$bizAddress = explode(' | ', ''.$bizAddress);
		if($bizAddress) {
			$state = strtoupper(trim(array_pop($bizAddress)));
			if(is_numeric($state) && $bizAddress) $state = strtoupper(trim(array_pop($bizAddress)));
			if(!trim($city = trim(array_pop($bizAddress)))) echo "<font color=red>Unknown: [{$biz['bizname']}] ".print_r($fullAdd, 1)." [$url] </font><br>";
			else if($state && $city) {
				$locs[] = "$state,$city";
			}
			else if(trim($state)) echo "<font color=red>Bad: $state [{$biz['bizname']}] [$url] </font><br>";
		}
		//if($state == 'CA') echo "$state-{$biz['bizname']}".(in_array($bizptr, (array)$liveBizIds) ? '*' : '')."<br>";
		//if($state) echo "$state-{$biz['bizname']}<br>";
		if(!function_exists('postProcess')) {
			function postProcess() {
				echo "<hr>";
				global $locs;
				if($locs) {
					sort($locs);
					foreach($locs as $loc) 
						echo "$loc<br>";
				}
				echo "Total: ".count($locs)." locations.";
			}
		}
//if(strpos($state, 'GA')!==FALSE) echo "[[[{$state}]]] [$bizptr]<br>";
	}

	if(FALSE) {  // SHOW STATES
		if($db == 'leashtimecustomers') {
			$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
			$liveBizIds = fetchCol0("SELECT garagegatecode 
																	FROM tblclient 
																	WHERE clientid IN (SELECT clientptr 
																											FROM tblclientpref 
																											WHERE property LIKE 'flag_%' AND value like '2|%')");
			echo count($liveBizIds)." live businesses.<p>";
			//foreach(fetchAssociations("SELECT garagegatecode, fname FROM tblclient WHERE garagegatecode IN (".join(',', $liveBizIds).")") as $liv)
			//	echo join(': ', $liv)."<br>";
			sort($liveBizIds);
			echo join(', ', $liveBizIds)."<br>";
			continue;
		}

		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		// !in_array($bizptr, $liveBizIds))
		if($state = strtoupper($biz['state'])) {
			$states[strtoupper($biz['state'])] += 1;
			if(in_array($bizptr, $liveBizIds)) $livestates[strtoupper((string)$biz['state'])] += 1;
			//echo "$state [$bizptr]<br>";
		}
		else {
			$fullAdd = $bizAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress'");
			$url = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage'");
			$bizAddress = explode(' | ', ''.$bizAddress);
			if($bizAddress) {
				$state = array_pop($bizAddress);
				if(is_numeric($state) && $bizAddress) $state = array_pop($bizAddress);
				if(!trim($state)) echo "<font color=red>Unknown: [{$biz['bizname']}] ".print_r($fullAdd, 1)." [$url] </font><br>";
				else if(strlen(trim($state)) <= 3) {
					//echo "$state [$bizptr]<br>";

					$states[strtoupper($state)] += 1;
					if(!$livestates[strtoupper($state)])  $livestates[strtoupper($state)] = 0;
					if(in_array($bizptr, (array)$liveBizIds)) $livestates[strtoupper($state)] += 1;
				}
				else if(trim($state)) echo "<font color=red>Bad: $state [{$biz['bizname']}] [$url] </font><br>";
			}
		}
		//if($state == 'CA') echo "$state-{$biz['bizname']}".(in_array($bizptr, (array)$liveBizIds) ? '*' : '')."<br>";
		//if($state) echo "$state-{$biz['bizname']}<br>";
		if(!function_exists('postProcess')) {
			function postProcess() {
				echo "<hr>";
				global $states, $livestates;
				if($states) {
					ksort($states);
					foreach($states as $state => $count) 
						echo "$state,"
									.($livestates[$state] ? $livestates[$state] : '0')
									.",".($count-($livestates[$state] ? $livestates[$state] : '0')).","
									.$count,"<br>";
				}
				echo "Total: ".array_sum($states)." businesses in ".count($states)." states.";
			}
		}
//if(strpos($state, 'GA')!==FALSE) echo "[[[{$state}]]] [$bizptr]<br>";
	}

	if(FALSE) {
		if($biz['test'] || !in_array('tblclientrequest', $tables)) continue;
		$rcounts = fetchKeyValuePairs("SELECT requesttype, count(*) FROM tblclientrequest
																	WHERE received > '2012-02-11'
																	GROUP BY requesttype");
		foreach(explode(',', 'Schedule,cancel,uncancel,change,Profile,CCSupplied,ACHSupplied') as $type)
			if($rcounts[$type]) 
				echo "$bizName ($db);$type;{$rcounts[$type]}<br>";
	}

	if(FALSE) {
		if(!in_array('tblmessage', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblmessage", 'Field');
		if($desc['hidefromcorresp']) {
			echo "<br>$db: changed table tblmessage (added hidefromcorresp).";
		}
		else {
			doQuery("ALTER TABLE tblmessage ADD `hidefromcorresp` tinyint(1) NOT NULL default '0'", 1);
			echo "<br>$db: changed table tblmessage (added hidefromcorresp).";
		}
	}

	if(FALSE) {
		if(!in_array('tblappointment', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblappointment", 'Field');
		if($desc['pets']['Type'] == 'varchar(255)') continue;
		doQuery("ALTER TABLE `tblappointment` CHANGE `pets` `pets` VARCHAR( 255 ) 
							CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL ");
		echo "tblappointment changed in $db<br>";
	}

	if(FALSE) {
		require_once "provider-fns.php";

		if(!in_array('tbltimeoff', $tables)) continue;
		echo "<br>$db: ";
		if(in_array('tbltimeoffinstance', $tables)) {
			echo "<font color=gray>new timeoff tables created already added to $db</font>";
			continue;
		}
		$mods = array('relwipedappointment'=>"CREATE TABLE IF NOT EXISTS `relwipedappointment` (
			`providerptr` int(11) NOT NULL,
			`appointmentptr` int(11) NOT NULL,
			`time` datetime NOT NULL,
			PRIMARY KEY  (`appointmentptr`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;",
		'tbltimeoffinstance'=>"CREATE TABLE IF NOT EXISTS `tbltimeoffinstance` (
			`patternptr` int(10) unsigned NOT NULL default '0',
			`date` date NOT NULL default '0000-00-00',
			`timeofday` varchar(45) default NULL,
			`providerptr` int(10) unsigned NOT NULL default '0',
			`timeoffid` int(10) unsigned NOT NULL auto_increment,
			`note` text,
			`created` datetime default NULL,
			`createdby` int(11) default NULL,
			`modified` datetime default NULL,
			`modifiedby` int(11) default NULL,
			UNIQUE KEY `Index_2` (`timeoffid`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;",
		'tbltimeoffpattern'=>"CREATE TABLE IF NOT EXISTS `tbltimeoffpattern` (
  `patternid` int(10) unsigned NOT NULL auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `until` date NOT NULL default '0000-00-00',
  `timeofday` varchar(45) default NULL,
  `providerptr` int(10) unsigned NOT NULL default '0',
  `note` text,
  `pattern` varchar(20) default NULL,
  `created` datetime default NULL,
  `createdby` int(11) default NULL,
  `modified` datetime default NULL,
  `modifiedby` int(11) default NULL,
  UNIQUE KEY `Index_2` (`patternid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");		
		foreach($mods as $tbl=>$mod) {
			doQuery($mod, 1);
			echo "<br>...$tbl added";
		}
		convertAllOldTimesOffToNew();
	}
		
		
	if(FALSE) {
		if(!in_array('tblcredit', $tables)) continue;
		echo "$db: ";
		$desc = fetchAssociationsKeyedBy("DESC tblcredit", 'Field');
		if(!$desc['created']) {
			doQuery("ALTER TABLE `tblcredit` ADD `created` DATETIME NULL AFTER `bookkeeping` ,
								ADD `createdby` INT NULL AFTER `created` ;");
			echo "tblcredit changed in $db<br>";
		}
		else echo "<font color=gray>tblcredit created already added to $db</font><br>";
	}

	if(FALSE) {
		if(!in_array('tblpayable', $tables)) continue;
		if(!in_array('tblusergooglevisit', $tables)) {
			doQuery("CREATE TABLE IF NOT EXISTS `tblusergooglevisit` (
  `userptr` int(11) NOT NULL,
  `visitptr` int(11) NOT NULL,
  `role` varchar(10) NOT NULL,
  `googleurl` varchar(255) NOT NULL,
  PRIMARY KEY  (`userptr`,`visitptr`),
  UNIQUE KEY `googleurl` (`googleurl`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
			echo "tblusergooglevisit added to $db.<br>";
		}
		else echo "<font color=gray>tblusergooglevisit already added to $db</font><br>";
	}

	if(FALSE) {
		if(!in_array('tblgeotrack', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblgeotrack", 'Field');
		if(!$desc['accuracy']) doQuery("ALTER TABLE `tblgeotrack` ADD `accuracy` DOUBLE NOT NULL DEFAULT '999999' AFTER `heading` ;");
		if(!$desc['event']) doQuery("ALTER TABLE `tblgeotrack` ADD `event` VARCHAR(10) NULL;");
		echo "tblgeotrack changed in $db: ".mysqli_affected_rows()."<br>";
	}

	if(FALSE) {
		if(!in_array('tblgeotrack', $tables)) continue;
		doQuery("UPDATE `tblgeotrack` SET event = 'completed' WHERE event IS NULL;");
		echo "tblgeotrack changed in $db: ".mysqli_affected_rows()."<br>";
	}

	if(FALSE) {
		if(!in_array('tblgeotrack', $tables)) continue;
		echo "$db: ";
		if(!$desc['error']) {
			doQuery("ALTER TABLE `tblgeotrack` ADD `error` VARCHAR(10) NULL;");
			echo "tblgeotrack changed in $db<br>";
		}
		else echo "<font color=gray>tblgeotrack event already added to $db</font><br>";
	}

	if(FALSE) {
		if(!in_array('tblgeotrack', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblgeotrack", 'Field');
		echo "$db: ";
		if(!$desc['event']) {
			doQuery("ALTER TABLE `tblgeotrack` ADD `event` VARCHAR(10) NULL;");
			echo "tblgeotrack changed in $db<br>";
		}
		else echo "<font color=gray>tblgeotrack event already added to $db</font><br>";
	}

	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test'] || !$biz['activebiz']) continue;
		$keys = fetchCol0("SELECT property FROM tblpreference WHERE property LIKE 'client_serv%'");
		$nums = array();
		foreach($keys as $k) $nums[] = (int)substr($k, strrpos($k, '_')+1);
		sort($nums);
		echo "<br>$bizName ($db): ".join(', ', $nums);
	}

	if(FALSE) {
		if(!in_array('tblpayable', $tables)) continue;
		replaceTable('tblpreference', array('value'=>1, 'property'=>'sittersCanRequestClientProfileChanges'), 1);
		echo "$bizName done.<br>";
	}

	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test']) continue;
		$start = '2011-10-30';
		$end = '2011-11-03';
		$q = "SELECT correspid, CONCAT_WS(fname, lname) as client, datetime, subject, correspaddr, lname, fname
					FROM tblmessage
					LEFT JOIN tblclient ON clientid = correspid
					WHERE inbound = 0 AND datetime >= '$start' AND datetime < '$end' 
						AND correstable = 'tblclient'
						AND body LIKE '%Current Charges%'
						AND body NOT LIKE '%Invoice Number%'
					ORDER BY datetime, lname, fname";
		echo "<hr><b>$bizName ($db)</b><br><table border=1>";
		$list = fetchAssociations($q);
		if($list) {
			echo "<tr><th>date (last payment)<th>time<th>client<th>subject</tr>\n";
			foreach($list as $row) {
				// if payment after nov 2 then dont't include
				$lastPaymentDate = fetchRow0Col0("SELECT issuedate FROM tblcredit 
													WHERE payment = 1 AND clientptr = {$row['correspid']} 
													ORDER BY issuedate DESC LIMIT 1");
				if(strcmp($end, $lastPaymentDate) < 0) continue;
				echo "<tr><td>".substr($row['datetime'], 0, 10)." ($lastPaymentDate)</td>";
				echo "<td>".substr($row['datetime'], 12, 4)."</td>";
				echo "<td>{$row['client']} ({$row['correspid']})</td><td>{$row['subject']}</td>";
				echo "</tr>\n";
				$tally++;
			}
		}
		else echo "<tr><th>None found.";
		echo "</table> Tally: $tally\n";
	}
	
	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test']) continue;
		//if(!$zz) {echo "<table><tr><td>Payables due prior to 10/01/2011";$zz=1;}
		$q = "SELECT XXXid, street1, street2, city, state, zip
					FROM tblXXX
					WHERE LENGTH( CONCAT_WS( '', street1, street2, city, state, zip ) ) >0 AND (city OR zip)";
		echo "<hr><b>$bizName ($db)</b><br><table border=1>";
		$list = fetchAssociations(str_replace('XXX', 'vet', $q));
		if($list) echo "<tr><th>vetid<th>street1<th>street2<th>city<th>state<th>zip</tr>\n";
		foreach($list as $row)
			echo "<tr><td>".join('</td><td>', $row)."</td></tr>\n";
		$list = fetchAssociations(str_replace('XXX', 'clinic', $q));
		if($list) echo "<tr><th>clinicid<th>street1<th>street2<th>city<th>state<th>zip</tr>\n";
		foreach($list as $row)
			echo "<tr><td>".join('</td><td>', $row)."</td></tr>\n";
		echo "<table>\n";
	}

	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test']) continue;
		//if(!$zz) {echo "<table><tr><td>Payables due prior to 10/01/2011";$zz=1;}
		$amt = fetchRow0Col0("SELECT sum(amount - paid) FROM tblpayable WHERE date >= '2011-01-01' AND date < '2011-10-01'");
		$cc = $cc == 'yellow' ? 'white' : 'yellow';
		//echo "<tr style='background:$cc'><td>$bizName ($db):<td align=right>".dollarAmount($amt);
		echo "$bizName ($db);".$amt.'<br>';
	}

	if(FALSE) {
		if($biz['test'] || !in_array('tblclientrequest', $tables)) continue;
		$cc_count = fetchFirstAssoc("SELECT count(*) as n, sum(amount) as sum FROM tblcredit WHERE sourcereference LIKE 'CC:%'");
		if($cc_count['n']) echo "$bizName ($db);CC Payment;{$cc_count['n']};{$cc_count['sum']}<br>";
		$ach_count = fetchFirstAssoc("SELECT count(*) as n, sum(amount) as sum FROM tblcredit WHERE sourcereference LIKE 'ACH:%'");
		if($ach_count['n']) echo "$bizName ($db);ACH Payment;{$ach_count['n']};{$ach_count['sum']}<br>";

		$cc_count = fetchFirstAssoc("SELECT count(*) as n, sum(amount) as sum FROM tblrefund WHERE sourcereference LIKE 'CC:%'");
		if($cc_count['n']) echo "$bizName ($db);CC REFUND;{$cc_count['n']};-{$cc_count['sum']}<br>";
		$ach_count = fetchFirstAssoc("SELECT count(*) as n, sum(amount) as sum FROM tblrefund WHERE sourcereference LIKE 'ACH:%'");
		if($ach_count['n']) echo "$bizName ($db);ACH REFUND;{$ach_count['n']};-{$ach_count['sum']}<br>";
	}
	
	if(FALSE) {
		if($biz['test'] || !in_array('tblclientrequest', $tables)) continue;
		foreach(explode(',', 'tblecheckacct,tblcreditcard') as $table) {
			$count = fetchRow0Col0("SELECT count(*) FROM $table");
			$activecount = fetchRow0Col0("SELECT count(*) FROM $table WHERE active");
			if($count) 
				echo "$bizName ($db);$table;$count;$activecount<br>";
		}
	}
	
	if(FALSE) {
		if(!in_array('tblpayable', $tables) || $biz['test']) continue;
		//if(!$zz) {echo "<table><tr><td>Payables due prior to 10/01/2011";$zz=1;}
		$amt = fetchRow0Col0("SELECT sum(amount - paid) FROM tblpayable WHERE date < '2011-10-01'");
		$cc = $cc == 'yellow' ? 'white' : 'yellow';
		//echo "<tr style='background:$cc'><td>$bizName ($db):<td align=right>".dollarAmount($amt);
		echo "$bizName ($db),".$amt.'<br>';
	}

	if(FALSE) {
		if(!in_array('tblhistoricaldata', $tables)) continue;
			echo "<hr>$bizName ($pdb): has historical data. ";
	}


	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		if($x = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone'")) {
			$pdb = $db;
			require "common/init_db_common.php";
			updateTable('tblpetbiz', array('timeZone'=>$x), "bizid = $bizptr");
			echo "<hr>$bizName ($pdb): TimeZone set to[$x] ";
		};
	}

	if(FALSE) {
			echo "<hr>$db: [$x] email: ".fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizemail' LIMIT 1");
			//print_r(fetchFirstAssoc("SELECT received, note FROM tblclientrequest WHERE clientptr IS NULL AND note LIKE '%holid%'ORDER BY received DESC LIMIT 1"));
		};
		
	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		$date = '2011-07-01';
		if($x = fetchRow0Col0("SELECT count(*) FROM tblinvoice WHERE date >= '$date'")) {
			echo "<hr>$db: [$x] invoices since $date";
			//print_r(fetchFirstAssoc("SELECT received, note FROM tblclientrequest WHERE clientptr IS NULL AND note LIKE '%holid%'ORDER BY received DESC LIMIT 1"));
		};
	}


	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		if($x = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone'")) {
			echo "<hr>$db: [$x] ";
			//print_r(fetchFirstAssoc("SELECT received, note FROM tblclientrequest WHERE clientptr IS NULL AND note LIKE '%holid%'ORDER BY received DESC LIMIT 1"));
		};
		//echo "<p>";
		$a = array('Eastern'=>'America/New_York', 'Central'=>'America/Chicago', 'Pacific'=>'America/Los_Angeles', 'Mountain'=>'America/Boise');
		if($a[$x]) {
			updateTable('tblpreference', array('value'=>$a[$x]), "property = 'timeZone'");
		}
	}


	if(FALSE) {
		if(!in_array('tblsurchargetype', $tables)) continue;
		if($x = fetchFirstAssoc("SELECT * FROM tblsurchargetype WHERE label = 'Labor Day' AND date NOT LIKE '%6'")) {
			echo "<hr>$db: [{$x['date']}] ";
			print_r(fetchFirstAssoc("SELECT received, note FROM tblclientrequest WHERE clientptr IS NULL AND note LIKE '%holid%'ORDER BY received DESC LIMIT 1"));
		};
		echo "<p>";
	}

	if(FALSE) {
		if(!in_array('tblpreference', $tables)) continue;
		replaceTable('tblpreference', array('value'=>1, 'property'=>'markStartFinish'));
		echo "markStartFinish set in $db<br>";
	}

	if(FALSE) {
		if(!in_array('tblservicepackage', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblservicepackage", 'Field');
		echo "$db: ";
		if(!$desc['billingreminders']) {
			doQuery("ALTER TABLE `tblservicepackage` ADD `billingreminders` TINYINT NULL DEFAULT '0' AFTER `prepaid` ;");
			echo "tblmessage changed in $db<br>";
		}
		else echo "<font color=gray>billingreminders already added to $db</font><br>";

	}

	if(FALSE) {
		if(!in_array('tblmessage', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblmessage", 'Field');
		echo "$db: ";
		if(!$desc['tags']) {
			doQuery("ALTER TABLE `tblmessage` ADD `tags` VARCHAR(100) NULL AFTER `transcribed`;");
			echo "tblmessage changed in $db<br>";
		}
		else echo "<font color=gray>Already done in $db</font><br>";

	}


	if(FALSE) {
		if(!in_array('tbldiscount', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tbldiscount", 'Field');
		echo "$db: ";
		if(!$desc['unlimiteddollar']) {
			doQuery("ALTER TABLE `tbldiscount` ADD `unlimiteddollar` TINYINT NULL AFTER `ispercentage`;");
			echo "tbldiscount changed in $db<br>";
		}
	}



	if(FALSE) { 
		
		$keyDescriptions = fetchFirstAssoc("SELECT COUNT(*) as descs, avg(LENGTH(description)) as len FROM tblkey WHERE description IS NOT NULL");
		$keys = fetchRow0Col0("SELECT count(*) FROM tblkey");
		$style = $keyDescriptions['len'] == 0 || !$keyDescriptions['descs'] ? "style='color:gray'" : '';
		
		echo "<span $style>$bizName ($db) keys: $keys descriptions: {$keyDescriptions['descs']} average length: ".round($keyDescriptions['len']).".</span><br>";
	}


	if(FALSE) { 
		doQuery(
		"CREATE TABLE IF NOT EXISTS `tblecheckacct` (
		  `acctid` int(11) NOT NULL auto_increment,
		  `active` tinyint(4) NOT NULL,
		  `abacode` varchar(9) NOT NULL,
		  `bank` varchar(50) default NULL,
		  `acctnum` varchar(20) NOT NULL,
		  `last4` varchar(4) NOT NULL,
		  `acctname` varchar(50) NOT NULL,
		  `accttype` varchar(20) default NULL,
		  `acctentitytype` varchar(20) default NULL,
		  `autopay` tinyint(1) NOT NULL,
		  `clientptr` int(11) NOT NULL,
		  `created` date NOT NULL,
		  `modified` date default NULL,
		  `createdby` int(11) NOT NULL,
		  `modifiedby` int(11) NOT NULL,
		  `gateway` varchar(40) default NULL,
		  `vaultid` varchar(100) default NULL,
		  `encrypted` tinyint(4) NOT NULL COMMENT 'if 0, sensitive values are already masked',
		  `primarypaysource` tinyint(4) NOT NULL,
		  PRIMARY KEY  (`acctid`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");
		doQuery(
		"CREATE TABLE IF NOT EXISTS `tblecheckacctinfo` (
			`acctptr` int(11) NOT NULL,
			`x_company` varchar(50) default NULL,
			`x_address` varchar(60) NOT NULL,
			`x_city` varchar(40) NOT NULL,
			`x_state` varchar(40) NOT NULL,
			`x_zip` varchar(20) NOT NULL,
			`x_country` varchar(60) NOT NULL,
			`x_phone` varchar(25) NOT NULL
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
		echo "<p>Added tables to <b>$bizName</b>.";

	}
	if(FALSE) { /** BAD EMAILS **/
		// find bad emails
		$started = false;
		$emailpat = "/^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/";
		foreach(fetchAssociations("SELECT CONCAT_WS(' ', fname, lname) as name, email, email2, clientid as id FROM tblclient") as $client) {
			$msg1 = !$client['email'] || preg_match($emailpat, $client['email']) ? '' : "Bad email: [{$client['email']}].  ";
			$msg2 = !$client['email2'] || preg_match($emailpat, $client['email2']) ? '' : "Bad email2: [{$client['email2']}]";
			if($msg1 || $msg2) {
				if(!$started) echo "<p>$bizName ($db):";
				$started = true;
				echo "<br>--- Client <a href=client-edit.php?id={$client['id']}>[{$client['name']}]</a>: $msg1$msg2";
			}
		}
		foreach(fetchAssociations("SELECT CONCAT_WS(' ', fname, lname) as name, email, providerid as id FROM tblprovider") as $prov) {
			$msg1 = !$prov['email'] || preg_match($emailpat, $prov['email']) ? '' : "Bad email: [{$prov['email']}].  ";
			if($msg1) {
				if(!$started) echo "<p>$bizName ($db):";
				$started = true;
				echo "<br>--- Sitter <a href=provider-edit.php?id={$provider['id']}>[{$prov['name']}]</a>: $msg1";
			}
		}
	}


	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway'") != 'Solveras') {
			$sql = "
					SELECT tblcreditcard.*, CONCAT_WS(' ', fname, lname) as client
					FROM `tblcreditcard`
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE tblcreditcard.active = 1 and primarypaysource = 0"; //  and primarypaysource = 0
			echo "<p>$db:<table border=1>";
			foreach(fetchAssociations($sql) as $row)
				echo "<tr><td>{$row['client']} ({$row['clientptr']})<td>{$row['ccid']}<td>#{$row['x_card_num']}";
			echo "</table>";
		}
		else echo "<p>No gateway for $db.";
	}

	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'ccGateway'")) {
			$sql = "
					SELECT *, CONCAT_WS(' ', fname, lname) as client
					FROM `tblgratuity`
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE paymentptr
					AND paymentptr NOT
					IN (SELECT creditid FROM tblcredit WHERE payment)";
			echo "<p>$db:<table border=1>";
			foreach(fetchAssociations($sql) as $row)
				echo "<tr><td>{$row['client']} ({$row['clientptr']})<td>{$row['issuedate']}<td>#{{$row['gratuityid']}}";
			echo "</table>";
		}
		else echo "<p>No gateway for $db.";
	}

	if(FALSE) { // BIG MISTAKE!  DON'T DO THIS AGAIN
		if(!in_array('tblcreditcard', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblcreditcard", 'Field');
		echo "$db: ";
		if($desc['primarypaysource']) {
			doQuery("UPDATE `tblcreditcard` SET `primarypaysource` = 1 WHERE active=1 ;");
			echo "<p>Turned primarypaysource on in <b>$bizName</b>: ".mysqli_affected_rows().".";
		}
	}

	if(FALSE) {
		if(!in_array('tblcreditcarderror', $tables)) continue;
		echo "$db: ";
			doQuery("ALTER TABLE `tblcreditcarderror` ADD `sourcetable` varchar(30) NOT NULL COMMENT 'tblcreditcard or tblecheckacct';");
			doQuery("UPDATE `tblcreditcarderror` SET `sourcetable` = 'tblcreditcard';");
			echo "<p>Added sourcetable to tblcreditcarderror in <b>$bizName</b>.";
	}

	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblcreditcard", 'Field');
		echo "$db: ";
		if(!$desc['primarypaysource']) {
			doQuery("ALTER TABLE `tblcreditcard` ADD `primarypaysource` TINYINT NOT NULL ;");
			doQuery("UPDATE `tblcreditcard` SET `primarypaysource` = 1 WHERE active ;");
			echo "<p>Added primarypaysource to <b>$bizName</b>.";
		}
		else echo "tblcreditcard already has field primarypaysource in $db.<br>";
	}

	if(FALSE) {
		if(!in_array('tblcreditcard', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tblcreditcard", 'Field');
		//echo "$db: ".print_r($desc, 1).'<br>';
		if(!$desc['vaultid']) {
			doQuery("ALTER TABLE `tblcreditcard` 
								ADD `gateway` varchar(40) default NULL,
								ADD `vaultid` varchar(100) default NULL");
			echo "<p>Added gateway and vaultid to <b>$bizName</b>.";
		}
		else echo "tblcreditcard already has field vaultid in $db.<br>";
	}

	if(FALSE) {
		$set = fetchRow0Col0("SELECT property, value FROM tblpreference WHERE property IN ('emailHost', 'emailFromAddress', 'defaultReplyTo')");
		if(!$set['replyTo'] && !$set['emailFromAddress'] && !$set['emailHost']) 
			echo "<p><b>$bizName</b> ($db) has no replyTo set.";
	}
	
	if(FALSE) {
		echo "<p><b>$db</b><br>";
		foreach(fetchAssociations("SELECT * FROM tblemailtemplate WHERE label LIKE '#S%' AND subject LIKE '#S%'" ) as $t) {
			$new = substr($t['subject'], 12);
			updateTable('tblemailtemplate', array('subject'=>$new), "templateid = {$t['templateid']}", 1);
			echo "[{$t['label']}] [{$t['subject']}] => [$new]<br>";
		}
	}

	if(FALSE) {
		if(!in_array('tbltimeoff', $tables)) continue;
		$desc = fetchAssociationsKeyedBy("DESC tbltimeoff", 'Key');
		//echo "$db: ".print_r($desc, 1).'<br>';
		if($desc['PRI']['Field'] != 'timeoffid') {
			doQuery("ALTER TABLE `tbltimeoff` DROP PRIMARY KEY");
			echo "tbltimeoff changed in $db<br>";
		}
		else echo "tbltimeoff already lacks primary key in $db<br>";
	}

	if(FALSE) {
		if(!in_array('tblkey', $tables)) continue;
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblkey ", 'Field');
		if($cols['possessor6']) continue;
		
		doQuery("ALTER TABLE `tblkey` ADD `possessor6` VARCHAR( 45 ) NULL AFTER `possessor5` ,
		ADD `possessor7` VARCHAR( 45 ) NULL AFTER `possessor6` ,
		ADD `possessor8` VARCHAR( 45 ) NULL AFTER `possessor7` ,
		ADD `possessor9` VARCHAR( 45 ) NULL AFTER `possessor8` ,
		ADD `possessor10` VARCHAR( 45 ) NULL AFTER `possessor9` ;");
		echo "tblkey changed in $db<br>";
	}
		
		
	if(FALSE) { 
		$south = fetchAssociations("SELECT * FROM `geocodes` WHERE `lat` LIKE '-%'");
		if($south) {
			echo "<hr>Southern hemisphere addresses in $bizName ($db)<p>";
			foreach($south as $add) {print_r($add);echo "<br>";}
		}
	}
	if(FALSE) { 
		$dtf = fetchAssociations("SELECT correspaddr, correspid, datetime, mgrname, CONCAT(fname, ' ', lname) as client
			FROM `tblmessage`
			LEFT JOIN tblclient on clientid = correspid
			WHERE body LIKE '%Current Charges</label></td><td id=\'\' class=\'right\'>$ 0%'
			ORDER BY datetime desc"); //datetime LIKE '2011-05-04%' AND 
		if($dtf) {
			echo "<hr>All Zero-sum invoices in <b>$bizName ($db)</b> Most recent first: <br><u>[Sent to],[client name & id],[date],[Manager]</u><br>";
			foreach($dtf as $row) echo "{$row['correspaddr']},({$row['client']} - {$row['correspid']}),{$row['datetime']},{$row['mgrname']}<br>";
		}
	}
	if(FALSE) { 
		$dtf = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailhost' AND value <> '' AND value IS NOT NULL LIMIT 1");
		echo "emailhost in $db: $dtf<br>";
	}
	if(FALSE) { 
		$label = 'Easter';
		$date = fetchRow0Col0("SELECT date FROM tblsurchargetype WHERE label = '$label'"); //  AND date like '%31'
		if($date) echo "$label in $db: $date<br>";
	}
	
	if(FALSE) { 
		if(!in_array('relpetcustomfield', $tables)) continue;
		doQuery("ALTER TABLE `relpetcustomfield` CHANGE `fieldname` `fieldname` VARCHAR( 15 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT 'petcustomN'");
		echo "relpetcustomfield changed in $db<br>";
	}
	
	if(FALSE) { 
		doQuery("CREATE TABLE IF NOT EXISTS `tblgeotrack` (
  `userptr` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `lat` double NOT NULL,
  `lon` double NOT NULL,
  `speed` double NOT NULL,
  `heading` double NOT NULL,
  `appointmentptr` int(11) default NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
		echo "tblgeotrack exists in $db<br>";
	}
	if(FALSE) { 
		$dtf = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'defaultTimeFrame' LIMIT 1");
		echo "Default Time Frame in $db: $dtf<br>";
	}
	if(FALSE) { // ;
		if(in_array('tblremindertype', $tables)) continue;
		doQuery("
CREATE TABLE IF NOT EXISTS `tblremindertype` (
  `remindertypeid` int(11) NOT NULL auto_increment,
  `label` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sendon` varchar(16) NOT NULL,
  `userid` int(11) NOT NULL COMMENT 'null if for all managers',
  `restriction` varchar(10) default NULL COMMENT 'client, sitter, or null',
  `standard` int(11) NOT NULL,
  PRIMARY KEY  (`remindertypeid`),
  UNIQUE KEY `label` (`label`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
");
	doQuery("
CREATE TABLE IF NOT EXISTS `tblreminder` (
  `reminderid` int(11) NOT NULL auto_increment,
  `userid` int(1) NOT NULL COMMENT 'null if for all managers',
  `remindercode` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sendon` varchar(16) NOT NULL COMMENT 'int,dow,date,datetime',
  `clientptr` int(11) NOT NULL,
  `providerptr` int(11) NOT NULL,
  `edited` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `lastsent` datetime default NULL,
  PRIMARY KEY  (`reminderid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
");
		echo "Added reminders to $db<br>";
	}
	
	if(FALSE) { // ;
		//$avg = fetchRow0Col0("SELECT avg(length(CONCAT_WS(' ', fname, lname))) FROM tblclient");
		//$count = fetchRow0Col0("SELECT count(*) FROM tblclient");
		$avg = fetchRow0Col0("SELECT avg(length(CONCAT_WS(' ', fname, lname))) FROM tblpet");
		$totCount += $count;
		$totSum += ($count * $avg);
		echo "Average for $db: $avg all: ".($totSum / $totCount)."<br>";
	}

	if(FALSE) { // ;
		$cols = fetchAssociationsKeyedBy("DESCRIBE relpetcustomfield ", 'Field');
		if(!$cols['fieldname']) continue;
		doQuery("ALTER TABLE `relpetcustomfield` CHANGE `fieldname` `fieldname` VARCHAR( 15 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT 'petcustomN' ");
		echo "Corrected relpetcustomfield fieldname length in $db<br>";
	}
	
	if(FALSE) { // ;
		$cols = fetchAssociationsKeyedBy("DESCRIBE relitemnote ", 'Field');
		if($cols['subject']) continue;
		doQuery("ALTER TABLE `relitemnote` ADD `subject` VARCHAR( 80 ) NULL ;");
		echo "Added subject column to relitemnote in $db<br>";
	}
	
	if(FALSE) { // ;
		$pairs = fetchKeyValuePairs("SELECT clientptr, count(*) FROM tblclientpref WHERE property LIKE 'flag_%' GROUP BY clientptr");
		if($pairs) echo "$db has flagged [".count($pairs)."] clients with [".array_sum($pairs)."] flags.<br>";
	}
	
	if(FALSE) { // ;
		$cols = fetchAssociationsKeyedBy("DESCRIBE tbltimeoff ", 'Field');
		if($cols['timeofday']) continue;
		doQuery("ALTER TABLE `tbltimeoff` ADD `timeofday` VARCHAR( 45 ) NULL AFTER `lastdayoff`;");
		echo "Added timeofday column to tblcredit in $db<br>";
	}
	
	if(FALSE) {
		$v = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientagreementrequired' LIMIT 1");
		if(!$v) echo "DB: $db ({$biz['bizid']}): clientagreementrequired = [$v].<p>";
	}

	if(FALSE && !in_array('relpetcustomfield', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `relpetcustomfield` (
  `petptr` int(11) NOT NULL,
  `fieldname` varchar(15) NOT NULL COMMENT 'petcustomN',
  `value` text,
  PRIMARY KEY  (`petptr`,`fieldname`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
		echo "DB: $db: updated.<p>";
	}


	if(FALSE && in_array('tblreminder', $tables)) {
		echo "$db has reminders<br>";
	}



	if(FALSE) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblcredit ", 'Field');
		if($cols['hide']) continue;
		doQuery("ALTER TABLE `tblcredit` ADD `hide` TINYINT NOT NULL DEFAULT 0;");
		echo "Added hide column to tblcredit in $db<br>";
	}

	if(FALSE) {
		doQuery("ALTER TABLE `tblappointment` CHANGE `birthmark` `birthmark` VARCHAR( 30 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL COMMENT 'timeofday_servicecode' ");
		echo "altered tblappointment.birthmark in $db<br>";
	}



	if(FALSE) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblcredit ", 'Field');
		if($cols['voidedamount']) continue;
		doQuery("ALTER TABLE `tblcredit` ADD `voidedamount` FLOAT(6,2) NULL;");
		echo "Added columns to tblcredit in $db<br>";
	}

	if(FALSE) {
		require_once "email-template-fns.php";
		//doQuery("DELETE FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
		ensureStandardTemplates($type=null);
		//$t = fetchFirstAssoc("SELECT subject, body FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email' AND targettype = 'client'");
		//echo "DB: $db: [{$t['subject']}] CLIENT ".htmlentities($t['body'])."<p>";
		echo "DB: $db: created new email templates.<p>";
	}


	if(FALSE) {
		$notes = fetchCol0("SELECT CONCAT_WS(' ', time, note) FROM tblchangelog WHERE itemtable = 'providerschedules'");
		foreach($notes as $note) echo "DB: $db: $note<br>
	";
	}

	if(FALSE && !in_array('relinvoicerefund', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `relinvoicerefund` (
  `invoiceptr` int(11) NOT NULL,
  `refundptr` int(11) NOT NULL,
  PRIMARY KEY  (`invoiceptr`,`refundptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
	}

	if(FALSE) {
		$scheduleDay = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'scheduleDay'");
		if($scheduleDay != 'Thursday') continue;
		echo "DB: $db: ";
		$start = '2011-01-18 04:00:00';
		$end = '2011-01-18 04:30:00';
		$count = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime >= '$start' AND datetime <= '$end'");
		if(!$count) $count = "<font color=red>NO</font>";
		echo "$count messages sent between ".date('h:i a', strtotime($start))." and ".date('h:i a', strtotime($end))." [DAY: $scheduleDay]<br>";
	}

	if(FALSE) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblcredit ", 'Field');
		if($cols['modified']) continue;
		doQuery("ALTER TABLE `tblcredit` ADD `modified` DATETIME NULL ,
ADD `modifiedby` INT NULL ,
ADD `voided` DATETIME NULL ;");
		echo "Added columns to tblcredit in $db<br>";
	}

	if(FALSE && !in_array('relinvoicerefund', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `relinvoicerefund` (
  `invoiceptr` int(11) NOT NULL,
  `refundptr` int(11) NOT NULL,
  PRIMARY KEY  (`invoiceptr`,`refundptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
	}

	if(FALSE) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblclientrequest ", 'Field');
		if($cols['extrafields']) continue;
		doQuery("ALTER TABLE `tblclientrequest` ADD `extrafields` text");
		echo "Added column to tblclientrequest in $db<br>";
	}


	if(FALSE && in_array('tblclient', $tables)) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblemailtemplate", 'Field');
		if($cols['extratokens']) continue;
		doQuery("ALTER TABLE `tblemailtemplate` ADD `extratokens` varchar(255) NOT NULL DEFAULT ''");
		echo "Added column to tblemailtemplate in $db<br>";
	}


	if(FALSE && in_array('tblservicepackage', $tables) && !fetchRow0Col0("SELECT templateid FROM tblemailtemplate WHERE label ='#STANDARD - Invoice Email'")) {
		echo "$db:<br>";
		doQuery("INSERT INTO `tblemailtemplate` (
`label` ,
`subject` ,
`body` ,
`targettype` ,
`personalize` ,
`salutation` ,
`farewell` ,
`active`
)
VALUES (
'#STANDARD - Invoice Email', 'Your Invoice', 'Hi #RECIPIENT#,<p>Here is your latest invoice.<p>Sincerely,<p>#BIZNAME#', 'client', '1', NULL , '', '1'
) 
");
	}


	if(FALSE && in_array('tblservicepackage', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `tblemailedinvoicepreview` (
  `previewid` int(11) NOT NULL auto_increment,
  `clientptr` int(11) NOT NULL,
  `attempted` datetime default NULL,
  `failed` tinyint(4) NOT NULL,
  `email` varchar(100) default NULL,
  PRIMARY KEY  (`previewid`),
  UNIQUE KEY `clientptr` (`clientptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;");
	}

	if(false) {
		echo "<hr><u>$db</u><br>";
		foreach(fetchAssociations("SELECT datetime,subject FROM tblmessage WHERE subject like '%$25%'") as $email)
			echo "{$email['datetime']} {$email['subject']}<br>";
	}

	if(false) {
		$tz = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizAddress' LIMIT 1");
		echo "address($db): $tz<br>";
	}

	if(false) {
		$tz = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone' LIMIT 1");
		echo "TIMEZONE: $tz<br>";
	}

	if(FALSE && $db != 'dogslife2') {
		doQuery("ALTER TABLE `tblservicetype` ADD `hours` VARCHAR( 5 ) NULL COMMENT 'HH:MM' AFTER `active` ,
ADD `hoursexclusive` TINYINT NOT NULL COMMENT 'if(1) hours are exclusive' AFTER `hours` ", 1);
		echo "ALTERED `tblservicetype in $db<br>";
	}

	if(FALSE && in_array('tblservicepackage', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `tblemailedinvoicepreview` (
  `previewid` int(11) NOT NULL auto_increment,
  `clientptr` int(11) NOT NULL,
  `attempted` datetime default NULL,
  `failed` tinyint(4) NOT NULL,
  `email` varchar(100) default NULL,
  PRIMARY KEY  (`previewid`),
  UNIQUE KEY `clientptr` (`clientptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
", 1);
		echo "ADDED `tblemailedinvoicepreview in $db<br>";
	}
	
	
	
	if(FALSE && in_array('tblservicepackage', $tables)) {
		doQuery("DROP TABLE IF EXISTS `tblscheduleplan`", 1);
		doQuery("CREATE TABLE IF NOT EXISTS `tblscheduleplan` (
  `planid` int(10) unsigned NOT NULL default '0',
  `clientptr` varchar(45) NULL default '',
  `name` varchar(100) NOT NULL default '',
  `notes` text,
  `previousversionptr` int(10) unsigned default NULL,
  `preemptrecurringappts` tinyint(1) NOT NULL default '1',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`planid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;", 1);
		echo "ADDED `tblscheduleplan in $db<br>";
		
		doQuery("CREATE TABLE IF NOT EXISTS `tblscheduleplanservice` (
  `serviceid` int(10) unsigned NOT NULL auto_increment,
  `daysofweek` varchar(45) default NULL,
  `providerptr` int(10) unsigned NOT NULL default '0',
  `timeofday` varchar(45) NOT NULL default '0',
  `servicecode` int(10) unsigned NOT NULL default '0' COMMENT 'Business-specific',
  `pets` varchar(45) NOT NULL default '',
  `planptr` int(10) unsigned NOT NULL default '0',
  `clientptr` int(10) unsigned NOT NULL default '0',
  `current` tinyint(1) NOT NULL default '1',
  `firstLastOrBetween` varchar(8) default NULL COMMENT 'For non-recurring packages',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`serviceid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;", 1);
		echo "ADDED `tblscheduleplanservice in $db<br>";
	}
	
	
	/*if($tems = fetchCol0("SELECT label FROM tblemailtemplate WHERE salutation is not null and salutation != ''")) {
		echo "<p>$db: (".count($tems).")<br>";
		echo join('<br>', $tems); //foreach($tems as $t) echo "{$t['label']}<br";
	}*/
	
	if(FALSE) {
		require_once "invoice-fns.php";
		$invoiceids = fetchCol0("SELECT invoiceid FROM tblinvoice");
		$started = false;
		foreach($invoiceids as $invoiceid) 
			if($mismatch = invoiceMismatch($invoiceid)) {
				if(abs($mismatch[0]-$mismatch[1]) < .02) continue;
				if(!$started) 	echo "<hr>$bizName ($db)<p>";
				$started = true;
				echo "LT".sprintf("%04d",$invoiceid).": <font color=gray>{$mismatch[0]} <> {$mismatch[1]}</font><br>";
			}
	}
	if(FALSE && in_array('relinvoiceitem', $tables)) {
		doQuery(" ALTER TABLE `relinvoiceitem` CHANGE `description` `description` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL  ");
		echo "ALTERed relinvoiceitem in $db<br>";
	}
	
	if(FALSE && in_array('relinvoiceitem', $tables)) {
		doQuery("CREATE TABLE IF NOT EXISTS `relinvoicerefund` (
  `invoiceptr` int(11) NOT NULL,
  `refundptr` int(11) NOT NULL,
  PRIMARY KEY  (`invoiceptr`,`refundptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
		echo "CREATEd relinvoicerefund in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("ALTER TABLE `tblemailtemplate` MODIFY label varchar(255) Not NULL ");
		echo "ALTERed `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('relproviderpayablepayment', $tables)) {
		doQuery("ALTER TABLE `relproviderpayablepayment` MODIFY negative tinyint(1) NULL ");
		echo "ALTERed `relproviderpayablepayment in $db<br>";
	}
	
	if(FALSE && in_array('tblclient', $tables) && !tableHasColumn('tblprovider', 'training')) {
		doQuery("ALTER TABLE `tblprovider` ADD `training` tinyint(4) Not NULL");
		echo "ALTERed `tblprovider` in $db<br>";
	}
	
	if(FALSE && in_array('tblpreference', $tables)) {
		doQuery("ALTER TABLE `tblpreference` MODIFY `value` text Not NULL");
		echo "ALTERed `tblpreference in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("ALTER TABLE `tblemailtemplate` MODIFY salutation varchar(100) NULL");
		echo "ALTERed `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblcredit', $tables) && !tableHasColumn('tblcredit', 'bookkeeping')) {
		doQuery("ALTER TABLE `tblcredit` ADD bookkeeping tinyint(4) Not NULL");
		echo "ALTERed `tblcredit` in $db<br>";
	}
	
	if(FALSE && in_array('tblclient', $tables) && !tableHasColumn('tblclient', 'training')) {
		doQuery("ALTER TABLE `tblclient` ADD `training` tinyint(4) Not NULL");
		echo "ALTERed `tblclient` in $db<br>";
	}
	
	if(FALSE && in_array('relclientcharge', $tables)) {
		doQuery("ALTER TABLE `relclientcharge` MODIFY `taxrate` float(5,4) NOT NULL default '-1.00'");
		echo "ALTERed `relclientcharge in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("ALTER TABLE `tblemailtemplate` ADD `extratokens` VARCHAR( 255 ) NOT NULL");
		echo "ALTERed `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblcredit', $tables)) {
		doQuery(" ALTER TABLE `tblpreference` CHANGE `value` `value` TEXT NOT NULL  ");
		echo "ALTERed `tblpreference in $db<br>";
	}
	
	if(FALSE && in_array('tblcredit', $tables)) {
		doQuery("UPDATE `tblcredit` set bookkeeping= 1 WHERE reason like '%New billable created. (v:%'");
		echo "UPDATEd `tblcredit in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("ALTER TABLE `tblemailtemplate` CHANGE `salutation` `salutation` VARCHAR( 100 ) NULL");
		echo "ALTERed `emailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblcredit', $tables)) {
		doQuery("ALTER TABLE `tblcredit` ADD `bookkeeping` TINYINT NOT NULL DEFAULT '0'");
		echo "ALTERed `tblcredit in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("UPDATE `tblemailtemplate` SET `body` = '#LOGO#\nDear #RECIPIENT#,\n\nHere is your upcoming schedule.\n\nSincerely,\n#BIZNAME#\n\n#SCHEDULE#' WHERE `label` LIKE '#STANDARD - Client''s Schedule' LIMIT 1 ;");
		echo "UPDATEd `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		doQuery("UPDATE `tblemailtemplate` SET `extratokens` = '#SCHEDULE#' WHERE `label` LIKE '#STANDARD%' ;");
		echo "UPDATEd `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblemailtemplate', $tables)) {
		$old = "#LOGO#\nDear #RECIPIENT#,\n\nHere is your upcoming schedule.\n\nSincerely,\n#BIZNAME#\n\n#SCHEDULE#";
		$new = "#LOGO#\n\nDear #RECIPIENT#,\n\nHere is your upcoming schedule.\n\nSincerely,\n#BIZNAME#\n\n#SCHEDULE#";
		doQuery("UPDATE `tblemailtemplate` SET `body` = '$new' WHERE `body` = '$old' LIMIT 1 ;");
		echo "UPDATEd `tblemailtemplate in $db<br>";
	}
	
	if(FALSE && in_array('tblclient', $tables)) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblclient", 'Field');
		if($cols['training']) continue;
		doQuery("ALTER TABLE `tblclient` ADD `training` TINYINT NOT NULL DEFAULT '0'");
		echo "Added column to tblclient in $db<br>";
	}
	
	if(FALSE && in_array('tblprovider', $tables)) {
		$cols = fetchAssociationsKeyedBy("DESCRIBE tblprovider", 'Field');
		if($cols['training']) continue;
		doQuery("ALTER TABLE `tblprovider` ADD `training` TINYINT NOT NULL DEFAULT '0'");
		echo "Added column to tblprovider in $db<br>";
	}
}
if(function_exists('postProcess')) { postProcess(); }
