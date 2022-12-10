<?// visit-sheet-fns.php


function dumpFields($fields, $oneCol=0, $specificData=null) {
	global $data;
	if(!$specificData) $specificData = $data;
	$primaryPhoneField = primaryPhoneField($specificData);
	
//if(mattOnlyTEST() && $specificData['clientid']) echo "<tr><td colspan=2>[".print_r($fields,1)."]NOTES: ".print_r($specificData['notes'],1);	
	foreach((array)$fields as $field => $label) 
		if(isset($specificData[$field])) {
			$val = $specificData[$field];
			$raw = in_array($field, array('homeaddress', 'mailaddress', 'pets', 'emergency','neighbor', 'keydata', 'notes', 'packagenotes', 'clinic', 'vet'));
			//$raw = in_array($field, array('homeaddress', 'mailaddress', 'pets', 'emergency','neighbor'));
			if(strpos($field, 'phone')) {
				if($oneCol && ($tel = usablePhoneNumber($val))) {
					$raw = true;
					$sms = textMessageEnabled($val);
					$name = safeValue("{$specificData['fname']} {$specificData['lname']}");
					$class = $field == $primaryPhoneField ?  'redfauxlink' : null;
					$icon = $sms ?  "<img src='art/SMS-yes.gif' style='height:20px;width:20px;vertical-align:bottom;'> " : null;
					$val = fauxLink(strippedPhoneNumber($val), "openCallBox(\"$name\", \"$tel\", \"$sms\")", 1, null, null, $class);
					if($icon) $label = "$icon$label";
				}
				else $val = strippedPhoneNumber($val);
			}
			if($field == $primaryPhoneField) {
				$raw = true;
				$val = "<b><font color=red>$val</font></b>";
			}
			else if(in_array($field, array('email', 'email2'))) {
				$raw = true;
				$val = clientEmailLink($specificData['clientid'], $val, $val, 99);				
			}
			else if(in_array($field, array('key'))) {
				$raw = true;
			}
			if(!$oneCol) labelRow($label.':', '', $val, 'labelcell','dataCell','','',$raw);
			else oneByTwoLabelRows($label.':', '', $val, 'labelcell','dataCell','','',$raw);
			//labelRow($label.':', '', $data[$field], 'labelcell','dataCell','','',$raw);
		}
}

function withHTMLBreaks($val) {
	//if($_SERVER['REMOTE_ADDR'] != '68.225.89.173') return $val;
	return str_replace("\n\n", "<p>", str_replace("\n", "<br>", cleanseString(trim($val))));
}

function clientEmailLink($clientid, $email, $label=null, $length=null, $nullCase=null) {
	if(userRole() == 'o' || $_SESSION['preferences']['trackSitterToClientEmail']) {
		if(!$email) return $nullCase;
		if($length) $label = truncatedLabel($label, $length);
		if(strpos($_SERVER["HTTP_USER_AGENT"], 'Windows Phone')) 
			return fauxLink($label, 
								"$.fn.colorbox({	href: \"comm-composer-mobile.php?client=$clientid\",	width:screen.availWidth, height:\"450\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});",
								1);
		else return fauxLink($label, "openConsoleWindow(\"emailcomposer\", \"comm-composer-mobile.php?client=$clientid\",300,500);", 1);
 	}
	else return makeEmailLink($email, $email, '', 24);
}


function addressFields($arr, $prefix='') {
	foreach(array('street1','street2','city','state','zip') as $f)
	  $add[$f] = $arr["$prefix$f"];
	
	return htmlFormattedAddress($add);
}

function getAddressList(&$appointments, &$clientDetails) {
  foreach($appointments as $appt)
		$addresses[] = $clientDetails[$appt['clientptr']]['googleaddress'];
	$addresses2 = array();
  foreach($addresses as $i => $address)
    if($i && ($address != $addresses[$i-1]))
      $addresses2[] = $address;
	return $addresses2;
}

function providerAppointmentsTable($appointments, &$clientDetails, $noKeyIDsAtAll=false) {
	$secureMode = $_SESSION['preferences']['secureClientInfo'];
	
	$providerNames = getProviderNames();
	$columns = explodePairsLine('client|Client||keylabel|Key||service|Service||pets|Pets');
	if($noKeyIDsAtAll) unset($columns['keylabel']);
  $timeofday = null;
	$services = array();
  foreach($appointments as $appt) {
		$services[] = $appt['serviceptr'];
		if($appt['timeofday'] != $timeofday) {
			$timeofday = $appt['timeofday'];
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow' colspan=".(count($columns)).">$timeofday</td></tr>");
		}
		$row = array();
		$row['service'] = $_SESSION['servicenames'][$appt['servicecode']];
		$row['pets'] = 	$appt['pets'];
		if($row['pets'] == 'All Pets') {
			if(!isset($allPets[$appt['clientptr']])) {
				require_once "pet-fns.php";
				$allPets[$appt['clientptr']] = getClientPetNames($appt['clientptr'], $inactiveAlso=false, $englishList=true);
			}
			$row['pets'] = $allPets[$appt['clientptr']];
		}
		$row['note'] = 	$appt['note'];
		$row['client'] = 	$secureMode ? $clientDetails[$appt['clientptr']]['clientid'] : $clientDetails[$appt['clientptr']]['clientname'];
		$row['keylabel'] = 	$clientDetails[$appt['clientptr']]['keyLabel'];
		$rows[] = $row;
		if(!$secureMode && $appt['address']) $rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=".(count($columns)).">- {$appt['address']}</td></tr>");
		if($appt['note']) $rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='color:red;' colspan=".(count($columns)).">Note: {$appt['note']}</td></tr>");
	}
	$services = join(',', array_unique($services));
	if($services) {
		$services = fetchAssociations("SELECT packageptr, if(recurring,'tblrecurringpackage', 'tblservicepackage') as servicetable 
																	FROM tblservice WHERE serviceid IN ($services)");
		$packages = array();
		foreach($services as $service) $packages[$service['servicetable']][] = $service['packageptr'];
		$notes = array();
		foreach($packages as $tbl => $ids) {
			$ids = join(',', $ids);
			$notes = array_merge($notes, fetchCol0("SELECT notes FROM $tbl WHERE packageid IN ($ids) AND notes IS NOT NULL"));
		}
//echo "ID: $id  DATE: $date<p>";
	}
	echo "<style>.joblistcolheader  {font-size: 1.05em;
  padding-bottom: 5px; 
  border-collapse: collapse;
  text-align:left;}
 </style>\n";
	echo "<div style='border: solid black 1px'>";
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
  tableFrom($columns, $rows, 'WIDTH=100%', 'jobstable', 'joblistcolheader', null, 'jobstablecell');
	echo "</div>";
}

function appointmentsTable($appointments) {
	$providerNames = getProviderNames();
	$columns = explodePairsLine('service|Service||pets|Pets||provider|Provider');
  $timeofday = null;
	$services = array();
	$packs = array();
	$clientid = null;
  foreach($appointments as $appt) {
		$clientptr = $appt['clientptr'];
		$packs[$appt['packageptr']] = $appt['recurringpackage'];
		$services[] = $appt['serviceptr'];
		if($appt['timeofday'] != $timeofday) {
			$timeofday = $appt['timeofday'];
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow' colspan=".(count($columns)).">$timeofday</td></tr>");
		}
		$row = array();
		$row['service'] = $_SESSION['servicenames'][$appt['servicecode']];
		$row['pets'] = 	$appt['pets'];
		$row['provider'] = 	$providerNames[$appt['providerptr']];
		if($appt['note']) $rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow' style='color:red;' colspan=".(count($columns)).">{$appt['note']}</td></tr>");

		$rows[] = $row;
	}	

	
	
	foreach((array)$packs as $packageptr => $recurring) {
		$currPack = findCurrentPackageVersion($packageptr, $clientptr, $recurring);
		if($currPack) $currPacks[$currPack] = getPackage($currPack, ($recurring ? 'R' : 'N'));
	}
	
	foreach((array)$currPacks as $currPack) {
		if($currPack['notes']) $notes[] = cleanseString(trim($currPack['notes']));
	}
	if($notes) $notes = withHTMLBreaks(join("\n\n", $notes));
	
	
	/*$services = join(',', array_unique($services));
	if($services) {
		$services = fetchAssociations("SELECT packageptr, if(recurring,'tblrecurringpackage', 'tblservicepackage') as servicetable 
																	FROM tblservice WHERE serviceid IN ($services)");
		$packages = array();
		foreach($services as $service) $packages[$service['servicetable']][] = $service['packageptr'];
		foreach($packages as $tbl => $ids) {
			$ids = join(',', $ids);
			$notes = array_merge($notes, fetchCol0("SELECT notes FROM $tbl WHERE packageid IN ($ids) AND notes IS NOT NULL"));
		}
		$notes = htmlize(join("\n\n", array_map('safeValue', $notes)));
//echo "ID: $id  DATE: $date<p>";
	}*/
	if($notes) {
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=".(count($columns)).">&nbsp;</td></tr>");
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='sortableListCell' colspan=".(count($columns))."><b>Notes:</b><br>$notes</td></tr>");
	}
	echo "<div style='border: solid black 1px'>";
  tableFrom($columns, $rows, 'WIDTH=100%', 'jobstable');
	echo "</div>";
}

function htmlize($text) {
	return str_replace("\n",'<br>', str_replace("\n\n",'<p>', cleanseString($text)));
}

function dumpAlarmTable($excludeFirstHR=null, $oneCol=0, $specificData=null) {
	global $data;
	if(!$specificData) $specificData = $data;
	$companyandphone = $specificData['alarmcompany'] ? $specificData['alarmcompany'] : '';
	$companyandphone .= $specificData['alarmcophone'] ? ($companyandphone ? " - $alarmcophone" : '').$specificData['alarmcophone'] : '';
	if($companyandphone) $specificData['companyandphone'] = $companyandphone;
  //$fields = explodePairsLine('companyandphone|Company||alarmpassword|Password||armalarm|Arm||disarmalarm|Disarm||alrmlocation|Location');
  $fields = explodePairsLine('companyandphone|Company||alarminfo|Alarm Info');
	$anyalarm = '';
  foreach(array_keys($fields) as $field) $anyalarm .= isset($specificData[$field]) ? $specificData[$field] : '';
  $colSpan = $oneCol ? 1 : 2;
	if(!$excludeFirstHR) echo "<tr><td colspan=$colSpan><hr></td></tr>";
  if(!$anyalarm) echo "<tr><td colspan=$colSpan>No Alarm<hr></td></tr>";
  else {
		$tableLabel = 'Alarm';
		if(!$oneCol) labelRow("<u>$tableLabel</u>", '', '', 'labelcell','dataCell','','',$raw);
		else oneByTwoLabelRows("<u>$tableLabel</u>", '', '', 'labelcell','dataCell','','',$raw);
		dumpFields($fields, $oneCol, $specificData);
		echo "<tr><td colspan=$colSpan><hr></td></tr>";
	}
}


function providerAppointmentsTableForEmail($appointments, &$clientDetails, $noKeyIDsAtAll=false) {
	$secureMode = $_SESSION['preferences']['secureClientInfo'];
	
	$providerNames = getProviderNames();
	$columns = explodePairsLine('client|Client||keylabel|Key||service|Service||pets|Pets');
	
	$clientids = array_keys($clientDetails);
	if($clientids) {
		$firstLabel = $clientDetails[$clientids[0]]['label'];
		if(strpos($firstLabel['mode'], 'pets') !== FALSE) unset($columns['pets']);
	}
	
	
	
	if($noKeyIDsAtAll) unset($columns['keylabel']);
  $timeofday = null;
	$services = array();
  foreach($appointments as $appt) {
		$services[] = $appt['serviceptr'];
		if($appt['timeofday'] != $timeofday) {
			$timeofday = $appt['timeofday'];
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=".(count($columns))."><b>$timeofday</b></td></tr>");
		}
		$row = array();
		$row['service'] = $_SESSION['servicenames'][$appt['servicecode']];
		$row['pets'] = 	$appt['pets'];
		if($row['pets'] == 'All Pets') {
			if(!isset($allPets[$appt['clientptr']])) {
				require_once "pet-fns.php";
				$allPets[$appt['clientptr']] = getClientPetNames($appt['clientptr'], $inactiveAlso=false, $englishList=true);
			}
			$row['pets'] = $allPets[$appt['clientptr']];
		}
		$row['note'] = 	$appt['note'];
		if(dbTEST('mobilemutts,mobilemuttsnorth')) {
			$row['client'] = 	$row['pets']. " ({$clientDetails[$appt['clientptr']]['lname']})";
		}
 		else $row['client'] = 	$clientDetails[$appt['clientptr']]['label']['label'];
		$row['keylabel'] = 	$clientDetails[$appt['clientptr']]['keyLabel'];
		$rows[] = $row;
		if(!$secureMode && $appt['address']) $rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=".(count($columns)).">- {$appt['address']}</td></tr>");
		if($appt['note']) $rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='color:red;' colspan=".(count($columns)).">Note: {$appt['note']}</td></tr>");
	}
	$services = join(',', array_unique($services));
	if($services) {
		$services = fetchAssociations("SELECT packageptr, if(recurring,'tblrecurringpackage', 'tblservicepackage') as servicetable 
																	FROM tblservice WHERE serviceid IN ($services)");
		$packages = array();
		foreach($services as $service) $packages[$service['servicetable']][] = $service['packageptr'];
		$notes = array();
		foreach($packages as $tbl => $ids) {
			$ids = join(',', $ids);
			$notes = array_merge($notes, fetchCol0("SELECT notes FROM $tbl WHERE packageid IN ($ids) AND notes IS NOT NULL"));
		}
//echo "ID: $id  DATE: $date<p>";
	}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
  tableFrom($columns, $rows, 'WIDTH=100% border=1 bordercolor=black', 'jobstable', 'joblistcolheader', null, 'jobstablecell');
}

function clientLabel($client) {
	// return array('mode'=>'name/pets', 'label'=>$label)
	require_once "preference-fns.php";
	$wagPrimaryNameMode = getUserPreference($_SESSION['auth_user_id'], 'provuisched_client');
	if(!$wagPrimaryNameMode) $wagPrimaryNameMode = 'fullname';

	if(strpos($wagPrimaryNameMode, 'pets') !== FALSE) {
		require_once "pet-fns.php";
		$allPets = array(); 
		foreach(getPetNamesForClients(array($client['clientid']), $inactiveAlso=false) as $cnid => $val)
			$displayPets = $val;
	}
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($clients);exit;; }	
	$clientlabel = $wagPrimaryNameMode == 'fullname' ? $client['clientname'] : (
					 $wagPrimaryNameMode == 'name/pets' ? "{$client['lname']} ($displayPets)" : (
					 $wagPrimaryNameMode == 'pets' ? $displayPets : (
					 $wagPrimaryNameMode == 'pets/name' ? "$displayPets ({$client['lname']}) " : (
					 $wagPrimaryNameMode == 'fullname/pets' ? "{$client['clientname']} ($displayPets)" :  
					'???'))));
	return array('mode'=>$wagPrimaryNameMode, 'label'=>$clientlabel);
}