<? // export-fns.php

function petOutput($petptr, $extraFields=null, $status=null, $target=null) {
	static $customFields, $cols;
	require_once "custom-field-fns.php";
	$customFields = $customFields ? $customFields : getCustomFields(true, false, getPetCustomFieldNames());
	$cols = $cols ? $cols : getPetColumns($extraFields);
	$fields = getPetSQLFields($extraFields);
	if(in_array("clientname", $cols))  $joins[] = "LEFT JOIN tblclient c ON clientid = ownerptr";
	if($joins)$joins = join(' ', $joins);
	$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '');
	$sql = "SELECT ".join(',', $fields)." FROM tblpet $joins WHERE petid = $petptr $status LIMIT 1";
	$pet = fetchFirstAssoc($sql);
//if(mattOnlyTEST())	{print_r( $sql."\n\n");exit;	}
//if(mattOnlyTEST() && $clientptr==747) {print_r( $sql."\n\n");exit;	}
	$booleans = explode(',', 'active,fixed');
	foreach($cols as $field) {
		if(strpos($field, '.')) $field = substr($field, strpos($field, '.')+1);
		$val = $pet[$field];
		if(in_array($field, $booleans)) $val = $val ? 'yes' : 'no';
		else if(strpos($field, 'date')) $val = $val ? date('m/d/Y', strtotime($val)) : ''; // use shortDate?
		$row[] = 
			$target == 'csv' ? csv($val) : (
			$target == 'xml' ? htmlentities($val) : (
			$target == 'raw' ? 	$val : $val));
	}
	
	if($customFields) {
		$fields = explode(',',$extraFields);
		if(in_array('full', $fields) || in_array('custom', $fields)) {
			$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = $petptr");
			foreach($customFields as $key => $custField) {
				$val = custValue($custField, $custVals[$key]);
				$row[] = 
					$target == 'csv' ? csv($val) : (
					$target == 'xml' ? htmlentities($val) : (
					$target == 'raw' ? 	$val : $val));
			}
		}
	}
	return $row;
}


	
function clientOutput($clientptr, $extraFields=null, $status=null, $target=null) { // sitter, vet, full
	static $cols;
	global $customFields, $bizFlags, $billingFlags, $loginIds, $exportBillingFlagsGlobal;
	require_once "custom-field-fns.php";
	require_once "pet-fns.php";
	$customFields = $customFields ? $customFields : getCustomFields($activeOnly=true); 
	$cols = $cols ? $cols : getClientColumns($extraFields);
	$fields = getClientSQLFields($extraFields);
	
	if(in_array("sittername", $cols))  $joins[] = "LEFT JOIN tblprovider p ON providerid = defaultproviderptr";
	if(in_array('emergencyname', $cols))  $joins[] = "LEFT JOIN tblcontact e ON e.clientptr = $clientptr AND e.type = 'emergency'";
	if(in_array('neighborname', $cols))  $joins[] = "LEFT JOIN tblcontact n ON n.clientptr = $clientptr AND n.type = 'neighbor'";
	if(in_array('referralname', $cols))  $joins[] = "LEFT JOIN tblreferralcat r ON referralid = referralcode";
	if(in_array('vetptr', $fields))  $joins[] = "LEFT JOIN tblvet v ON vetid = vetptr";
	if(in_array('clinicid', $fields))  $joins[] = "LEFT JOIN tblclinic c ON clinicid = tblclient.clinicptr";

	if($joins)$joins = join(' ', $joins);
	$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '');
	$sql = "SELECT ".join(',', $fields)." FROM tblclient $joins WHERE clientid = $clientptr $status LIMIT 1";
//echo print_r($fields);exit;	
	$client = fetchFirstAssoc($sql);
	if(!$client['active'] && !$client['deactivationdate']) { // look for deactivation date
		$client['deactivationdate'] = fetchRow0Col0(
			"SELECT time 
				FROM tblchangelog 
				WHERE itemptr = $clientptr AND itemtable='tblclient' AND note = 'Deactivated' 
				ORDER BY time DESC LIMIT 1", 1);
	}
	
	if($target == 'csv' && in_array('pets', $cols)) { // include a pets column in text sheets (xml version has pets in a separate sheet)
		$client['pets'] = getClientPetNames($clientptr, $inactiveAlso=false, $englishList=false);
	}
	// An unknown error sometimes causes creation of 2+ keys for a client
	// ... so fetch keys separately
	$key = fetchFirstAssoc("SELECT keyid,bin,description FROM tblkey WHERE clientptr = $clientptr LIMIT 1");
	$client['keyid'] = $key 
		? ($_SESSION['preferences']['mobileKeyDescriptionForKeyId'] ? $key['description'] : $key['keyid'])
		: '';
	$client['hook'] = $key['bin'];
//if(mattOnlyTEST())	{print_r( $sql."\n\n");exit;	}
//if(mattOnlyTEST() && $clientptr==747) {print_r( $sql."\n\n");exit;	}
	if($loginIds) $client['userid'] = $loginIds[$client['userid']];
//if($clientptr = 659)	print_r($sql);
	$booleans = explode(',', 'active,prospect,emergencycarepermission,nokeyrequired,mailtohome,emergencyhaskey,neighborhaskey');
	
	if(in_array('referralname', $cols) && $client['referralcode']) {
		require_once "referral-fns.php";
		$path = getReferralPath($client['referralcode']);
		if($path && $path[0] == '*inactive*') {
			$inactiveReferral = true;
			unset($path[0]);
		}
		if(!$path) $path = '';
		else $path = join('>', $path);
		$client['referralname'] = $path;
	}	
	
	require_once "field-utils.php";
	foreach($cols as $fullfield) {
		$field = $fullfield;
		if(strpos($fullfield, '.')) $field = substr($fullfield, strpos($fullfield, '.')+1);
		$val = $client[$field];
		if(strpos($fullfield,'phone') !== FALSE) {
			$rawphones[$field] = numeralsOnly($val);
		}
		if(in_array($field, $booleans)) $val = $val ? 'yes' : 'no';
		else if(strpos($field, 'date')) $val = $val ? date('m/d/Y', strtotime($val)) : ''; // use shortDate?
//if(mattOnlyTEST() && (strpos($field, 'treet') || strpos($field, 'name'))) $val = "XXXXXX";
		$row[] = 
			$target == 'csv' ? csv($val) : (
			$target == 'xml' ? htmlentities($val) : (
			$target == 'raw' ? 	$val : $val));
	}
	
	if($customFields) {
		$fields = explode(',',$extraFields);
		if(in_array('full', $fields) || in_array('custom', $fields)) {
			$custVals = fetchKeyValuePairs("SELECT fieldname, value FROM relclientcustomfield WHERE clientptr = $clientptr");
			foreach($customFields as $key => $custField) {
				$val = custValue($custField, $custVals[$key]);
				$row[] = 
					$target == 'csv' ? csv($val) : (
					$target == 'xml' ? htmlentities($val) : (
					$target == 'raw' ? 	$val : $val));
			}
		}
	}
	if($bizFlags) {
		$fields = explode(',',$extraFields);
		if(in_array('full', $fields) || in_array('flags', $fields)) {
			foreach(getClientFlags($clientptr) as $flag) $flags[$flag['flagid']] = $flag;
			foreach($bizFlags as $bizflag) {
				$flag = $flags[$bizflag['flagid']];
				$val = $flag['note'] ? $flag['note'] : ($flag ? $flag['title'] : '');
			$row[] = 
				$target == 'csv' ? csv($val) : (
				$target == 'xml' ? htmlentities($val) : (
				$target == 'raw' ? 	$val : $val));
			}
		}
	}
	
	if($exportBillingFlagsGlobal) {
		global $billingFlags;
		if(!$billingFlags) {
			require_once "client-flag-fns.php"; // require_once sometimes zaps globals
			$billingFlags = getBillingFlagList();
		}
		$fields = explode(',',$extraFields);
		if(in_array('full', $fields) || in_array('billingflags', $fields)) {
			foreach(getClientBillingFlags($clientptr) as $flag) $clientbillflags[$flag['flagid']] = $flag;
			foreach($billingFlags as $billflag) {
				$flag = $clientbillflags[$billflag['flagid']];
				$val = $flag['note'] ? $flag['note'] : ($flag ? ($billflag['title'] ? $billflag['title'] : 'yes') : '');
			$row[] = 
				$target == 'csv' ? csv($val) : (
				$target == 'xml' ? htmlentities($val) : (
				$target == 'raw' ? 	$val : $val));
			}
		}
	}
	
	global $includeVisitCounts;
if($includeVisitCounts) {	// visitCount
//print_r($row);echo "\n\n";
	$result = doQuery(
		"SELECT date 
			FROM tblappointment 
			WHERE clientptr = {$client['clientid']} AND canceled IS NULL and completed IS NOT NULL
			ORDER BY date", 1);
	while($assoc = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$firstDate = $firstDate ?  $firstDate  : $assoc['date'];
		$lastDate = $assoc['date'];
		$totalVisitCount += 1;
	}

	// find visit count, first and last visit dates
	foreach(array($totalVisitCount, $firstDate, $lastDate) as $index => $val) {
		$val = $val && $index > 0 ? shortDate(strtotime($val)) : $val;
		$row[] = 
			$target == 'csv' ? csv($val) : (
			$target == 'xml' ? htmlentities($val) : (
			$target == 'raw' ? 	$val : $val));
		}
	
//print_r($row);//exit;
}

if(FALSE && mattOnlyTEST()) {
	foreach($rawphones as $k=>$v)
		$row[] = $v;
}

	return $row;
}

function clientCSV($clientptr, $extraFields=null, $status=null) { // sitter, vet, full
	return join(',', clientOutput($clientptr, $extraFields, $status, 'csv') );
}

function custValue($field, $value) {
	if($field[2] == 'boolean') return $value ? 'yes' : 'no';
	else return $value;
}


function providerCSV($providerptr, $extraFields=null, $status=null) { // sitter, vet, full
	static $cols;
	$cols = $cols ? $cols : getProviderColumns($extraFields);
	$fields = getProviderSQLFields($extraFields);
	
	if($joins)$joins = join(' ', $joins);
	$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '');
	$sql = "SELECT ".join(',', $fields)." FROM tblprovider $joins WHERE providerid = $providerptr $status LIMIT 1";
//echo print_r($fields);exit;	
//echo $sql;exit;	
	$provider = fetchFirstAssoc($sql);

	$booleans = explode(',', 'active,noncompetesigned');
	
	foreach($cols as $field) {
		if(strpos($field, '.')) $field = substr($field, strpos($field, '.')+1);
		$val = $provider[$field];
		if(in_array($field, $booleans)) $val = $val ? 'yes' : 'no';
		else if(strpos($field, 'date')) $val = $val ? date('m/d/Y', strtotime($val)) : ''; // use shortDate?
		$row[] = csv($val);
	}
	
	return join(',', $row);
}

function getProviderColumns($extraFields=null) {
	$fields = getProviderSQLFields($extraFields);
	foreach($fields as $field) {
		if(strpos($field, ' as ')) $cols[] = substr($field, strrpos($field, ' ')+1);
		else $cols[] = $field;
	}
	return $cols;
}

function getProviderSQLFields($extraFields=null) {
	$fields = 'providerid,lname,fname,email,homephone,cellphone,workphone,fax,pager'
		  .',street1,street2,city,state,zip'
		  .',active';
	foreach(explode(',', $fields) as $field) {
		if(strpos($field, ' as ') !== FALSE)$providercols[] = $field;
		else $providercols[] = "tblprovider.$field as $field";
	}
	$fields = join(',', $providercols);
		  
	$fullFields = "$fields,taxid,emergencycontact,maritalstatus,tblprovider.notes,employeeid,"
								."jobtitle,hiredate,terminationdate,terminationreason,labortype,noncompetesigned,"
								."nickname,paymethod,paynotification,ratetype"; //,CUSTOMFIELDS
	
	if ($extraFields == 'full') $fields =  $fullFields;
	else {
		if($extraFields) $extraFields = array_map('trim', explode(',', $extraFields));
		foreach((array)$extraFields as $tag) {
			//if($tag == 'vet') $fields .= ",$vetFields";
			//else if($tag == 'sitter') $fields .= ",$providerFields";
		}
	}
//print_r( 	array_map('deComma', explode(',', $fields)));exit;
	return array_map('deComma', explode(',', $fields));
}

function getClientColumns($extraFields=null, $withCustomFields=false, $withFlags=false, $withBillingFlags=false) {
	$fields = getClientSQLFields($extraFields);
	foreach($fields as $field) {
		if(strpos($field, ' as ')) $field = substr($field, strrpos($field, ' ')+1);
		$cols[] = $field;
		// add key hook
		if($field == 'keyid') $cols[] = 'hook';
	}
	if(TRUE || mattOnlyTEST()) {  // quick shutoff if including pets becomes a problem
		// insert pets column after email
		$newcols = array();
		foreach($cols as $i => $label) {
			$newCols[$i] = $label;
			if($label == 'email') break;
		}
		$newCols[] = 'pets';
		for($j=$i+1; $j < count($cols); $j++)
			$newCols[] = $cols[$j];
		$cols = $newCols;
		
	}
	if($withCustomFields) {
		global $customFields;
		require_once "custom-field-fns.php";
		$customFields = $customFields ? $customFields : getCustomFields($activeOnly=true); 
		$fields = explode(',',$extraFields);
		if(in_array('full', $fields) || in_array('custom', $fields)) {
			foreach($customFields as $key => $custField) {
				$cols[] = trim($custField[0]);
			}
		}
	}
	if($withFlags) {
		require_once "client-flag-fns.php";
		global $bizFlags;
		$bizFlags = getBizFlagList(); 
		foreach($bizFlags as $flag) {
				$cols[] = "flag/".trim($flag['title'] ? $flag['title'] : $flag['src']);
		}
	}
	if($withBillingFlags) {
		global $billingFlags;
		if(!$billingFlags) {
			require_once "client-flag-fns.php"; // require_once sometimes zaps globals
			$billingFlags = getBillingFlagList();
		}
		foreach((array)$billingFlags as $flag) {
				$cols[] = "billingflag/".trim($flag['title'] ? $flag['title'] : $flag['flagid']);
		}
	}
if(FALSE && mattOnlyTEST()) $cols[] = 'completedVisits';// visitCount -- could not make it line up!
	return $cols;
}
		
function getClientSQLFields($extraFields=null) {
	$fields = 'clientid,lname,fname,email,userid,homephone,cellphone,workphone,fax,pager'
		  .",lname2,fname2,CONCAT_WS(' '#COMMA# tblclient.fname2#COMMA# tblclient.lname2) as alternate,email2,cellphone2"
		  .',null as keyid'
		  .',street1,street2,city,state,zip'
		  .',mailstreet1,mailstreet2,mailcity,mailstate,mailzip'
		  .',active,prospect'; // ,birthday -- not surfaced in UI
	foreach(explode(',', $fields) as $field) {
		if(strpos($field, ' as ') !== FALSE)$clientcols[] = $field;
		else $clientcols[] = "tblclient.$field as $field";
	}
	$fields = join(',', $clientcols);
		  
	$vetFields = "vetptr,CONCAT_WS(' '#COMMA#v.fname#COMMA# v.lname) as vetname,ifnull(v.officephone#COMMA# ifnull(v.cellphone#COMMA# v.homephone)) as vetphone"
		.",clinicid,clinicname,ifnull(c.officephone#COMMA# ifnull(c.cellphone#COMMA# c.homephone)) as clinicphone";
	$providerFields = "defaultproviderptr,CONCAT_WS(' '#COMMA# p.fname#COMMA# p.lname) as sittername";
	$emergencyFields = "e.name as emergencyname,e.location as emergencylocation,e.homephone as emergencyhomephone,e.workphone as emergencyworkphone,e.cellphone as emergencycellphone,e.note as emergencynote,e.haskey as emergencyhaskey";
	$neighborFields = "n.name as neighborname,n.location as neighborlocation,n.homephone as neighborhomephone,n.workphone as neighborworkphone,n.cellphone as neighborcellphone,n.note as neighbornote,n.haskey as neighborhaskey";
	$fullFields = "$fields,$vetFields,$providerFields,tblclient.notes,officenotes,tblclient.directions,alarmcompany,alarminfo,alarmcophone,"
								."setupdate,activationdate,deactivationdate,"
								."$emergencyFields,$neighborFields,"
								.'leashloc,foodloc,parkinginfo,garagegatecode,emergencycarepermission,'
								.'nokeyrequired,r.label as referralname,referralnote,referralcode,mailtohome'; //,CUSTOMFIELDS
	
	if ($extraFields == 'full') $fields =  $fullFields;
	else {
		if($extraFields) $extraFields = array_map('trim', explode(',', $extraFields));
		foreach((array)$extraFields as $tag) {
			if($tag == 'vet') $fields .= ",$vetFields";
			else if($tag == 'sitter') $fields .= ",$providerFields";
		}
	}
//print_r( 	array_map('deComma', explode(',', $fields)));exit;
	return array_map('deComma', explode(',', $fields));
}

function getPetColumns($extraFields=null, $withCustomFields=false) {
	$fields = getPetSQLFields($extraFields);
	foreach($fields as $field) {
		if(strpos($field, ' as ')) $cols[] = substr($field, strrpos($field, ' ')+1);
		else $cols[] = $field;
	}
	if($withCustomFields) {
		static $customFields;
		require_once "custom-field-fns.php";
		$customFields = $customFields ? $customFields : getCustomFields(true, false, getPetCustomFieldNames()); 
		foreach($customFields as $key => $custField) {
			$cols[] = $custField[0];
		}
	}
	
	return $cols;
}
		
function getPetSQLFields($extraFields=null) {
	$fields = "petid,ownerptr,CONCAT_WS(' '#COMMA# c.fname#COMMA# c.lname) as clientname,type,active,name,breed,sex,color,fixed,dob,description,notes,birthday";
	foreach(explode(',', $fields) as $field) {
		if(strpos($field, ' as ') !== FALSE) $petcols[] = $field;
		else $petcols[] = "tblpet.$field as $field";
	}
	$fields = join(',', $petcols);
		  
//print_r( 	array_map('deComma', explode(',', $fields)));exit;
	return array_map('deComma', explode(',', $fields));
}

function deComma($str) {return str_replace('#COMMA#', ',', $str);}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}