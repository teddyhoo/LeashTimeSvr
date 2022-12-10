<? // zip-lookup.php

function fetchCities($allZips) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$zips = fetchAssociations("SELECT city, zipcode as zip FROM zipcodes2 WHERE zipcode IN ('".join("','", $allZips)."') ORDER BY city, zipcode");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $zips;
}

function zipFromCoords($latitude, $longitude, $multiple=null, $radius=null)  {
	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	static $zipcodedb, $partialAttribute, $nonuniqueZipAttribute;
	$zipcodedb = $zipcodedb ? $zipcodedb : getI18Property('zipcodedb', $default='zipcodes2');
	if(!$zipcodedb) return;
	$qry = "SELECT zipcode, (((acos(sin((".$latitude."*pi()/180)) * sin((`lat`*pi()/180))+cos((".$latitude."*pi()/180)) * cos((`lat`*pi()/180)) * cos(((".$longitude."- `lon`)*pi()/180))))*180/pi())*60*1.1515) as distance 
					FROM `$zipcodedb` 
					WHERE lat AND lon
					ORDER BY distance ASC";
	if($multiple) $qry .= " LIMIT $multiple";
	if($multiple && !$radius) return fetchAssociations($qry);
	else if($radius) {
		$rows = array();
		$result = doQuery($qry);
//echo "Q: $qry<br>";
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
//echo "D: {$row['distance']}<br>";
		 	if($row['distance'] <= $radius)
		 		$rows[] = $row;
		 	else break;
		}
//print_r($rows);
		return $rows;
	}
	else {
		$result = fetchFirstAssoc($qry);
		//echo "$qry<p>{$result['zipcode']}: {$result['distance']} mi";
		return $result['zipcode'];
		//Read more: http://www.marketingtechblog.com/technology/calculate-distance/#ixzz1bSt0zxEJ
	}
}

function lookUpZip($zip, $noEcho=false) {
	// initialize the shared database
	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	static $zipcodedb, $partialAttribute, $nonuniqueZipAttribute;
	$zipcodedb = $zipcodedb ? $zipcodedb : getI18Property('zipcodedb', $default='zipcodes2');
	if($zipcodedb && !$partialAttribute) {
		$partialAttribute = fetchFirstAssoc("SELECT * FROM $zipcodedb LIMIT 1");
		$partialAttribute = $partialAttribute['partial'] ? 1 : -1;
	}
	if($zipcodedb && !$nonuniqueZipAttribute) {
		$nonuniqueZipAttribute = fetchFirstAssoc("SELECT * FROM $zipcodedb LIMIT 1");
		$nonuniqueZipAttribute = $nonuniqueZipAttribute['nonunique'] ? 1 : -1;
	}
	if($zipcodedb) {
		if($nonuniqueZipAttribute == 1) {  // AU.  one postcode covers many towns
			$cities = fetchCol0("SELECT CONCAT_WS('|',city,state) FROM $zipcodedb WHERE zipcode LIKE '$zip'");
			foreach($cities as $i => $city) if(substr($city,-1) == '|') $cities[$i] .= ' ';
			if($cities) $result = join('||', $cities);
			else $result = 'NO_CITIES';
			//return;
		}
		if(!$result) {
			if($partialAttribute == 1) {  // UK.  e.g. zip "AB51 1LT" => "AB1"
				$zip = trim($zip);
				$zip = strrpos($zip, ' ') ? substr($zip, 0, strrpos($zip, ' ')) : $zip;
			}
			$result = fetchRow0Col0("SELECT CONCAT_WS('|',city,state) FROM $zipcodedb WHERE zipcode LIKE '$zip' LIMIT 1", 1);
			if(strpos($result, '|') === FALSE) $result .= '|-';
		}
  	if($noEcho) return $result;
  	else echo $result;
	}
}

function lookUpProtectedZip($zip) {
	// find this biz's organization
	if($_SESSION["orgptr"]) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]}");
		reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
		$knownZip = fetchFirstAssoc("SELECT zip, branchptr, tblbranch.* FROM tblzipcodeassignments LEFT JOIN tblbranch ON branchid = branchptr WHERE zip = '$zip' LIMIT 1");
		// if zip known and protected, echo message about contacting
		if($knownZip) {
			if($knownZip['branchptr'] == -1) ;
			else if($knownZip['branchptr'] == 0) echo "<ERROR>ZIP Code $zip is unassigned, but protected.\n\nPlease contact {$org['orgname']} for details.</ERROR>";
			else if($knownZip['branchptr']) {
				echo "<ERROR>ZIP Code $zip belongs to {$knownZip['name']}.\n\nPlease pass this lead on to {$knownZip['fname']} {$knownZip['lname']}";
				echo $knownZip['email'] ? "\n\n({$knownZip['email']}).</ERROR>" : ".</ERROR>";
			}
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	}
}

//SELECT CONCAT_WS('|',city,state) FROM zipcodes WHERE zipcode = '22190' LIMIT 1

function dumpZipLookupJS() {
	static $zipcodedb;
	$zipcodedb = $zipcodedb ? $zipcodedb : getI18Property('zipcodedb', $default='zipcodes2');
	if(!strpos($zipcodedb, '_') || strpos($zipcodedb, '_US')) $regexCheck =    // US database
"	var regex = /((^\d{5}([- |]\d{4})?$)|(^[A-Z]\d[A-Z][- |]\d[A-Z]\d$))/;
	if(!regex.test(zip)) return;\n";
	
	echo <<<JS
// zip-lookup.js
// REQUIRED: ajax-fns.js must be included in the calling page.
//
// supplyLocationInfo(cityState,addressGroupId) should be implemented by the page that includes this script
// cityState is in the form "city|state"
// addressGroupId is an id that the calling script will use to determine which elements to populate

function lookUpZip(zip, addressGroupId, urlPrefix) {
  // if(!isValidZip() return;
  if(urlPrefix == undefined) urlPrefix = '';
	zip = zip.toUpperCase().replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	if(zip.length == 0) return;
	$regexCheck
  var xh = getxmlHttp();
  xh.open("GET",urlPrefix+"zip-lookup-ajax.php?zip="+zip,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { supplyLocationInfo(xh.responseText, addressGroupId); } }
  xh.send(null);
}

function checkUnprotectedZip(zip, addressGroupId, urlPrefix, selId) {
	// If zip is found in selection options, clear this input, select the zip in the pullown, and invoke lookUpZip
	// else look up zip in parent org database.  
	//    If protected, show a message and clear this input
	//    else lookUpZip
	zip = zip.toUpperCase().replace(/^\s\s*/, '').replace(/\s\s*$/, '');
  if(urlPrefix == undefined) urlPrefix = '';
  var sel = document.getElementById(selId);
  for(var i=0; i<sel.options.length;i++)
  	if(sel.options[i].value  == zip) {
			sel.selectedIndex = i;
			lookUpZip(zip, addressGroupId, urlPrefix);
			return;
		}
	var args = "<args id='"+selId+"_unprotected' urlPrefix='"+urlPrefix+"' addressGroupId='"+addressGroupId+"' zip='"+zip+"' />";
	ajaxGetAndCallWith(urlPrefix+"zip-lookup-protected-ajax.php?zip="+zip, protectedZipCallback, args);
}

function protectedZipCallback(args, resultxml) {
//alert(resultxml);	
	var root = getDocumentFromXML(resultxml).documentElement;
	args = getDocumentFromXML(args).documentElement;
	if(root && root.tagName == 'ERROR') {
		alert(root.firstChild.nodeValue);
		document.getElementById(args.getAttribute('id')).value = '';
		document.getElementById('label_'+args.getAttribute('addressGroupId')+'city').innerHTML = '';
		document.getElementById('label_'+args.getAttribute('addressGroupId')+'state').innerHTML = '';
		return;
	}
	lookUpZip(args.getAttribute('zip'), args.getAttribute('addressGroupId'), args.getAttribute('urlPrefix'));
}

function openGoogleMap(prefix) {
	var addr = '';
	if(document.getElementById(prefix+'street1').value) addr += document.getElementById(prefix+'street1').value;
	if(false && document.getElementById(prefix+'street2').value) {
		if(addr) addr += '+';
		addr += document.getElementById(prefix+'street2').value;
	}
	if(document.getElementById(prefix+'zip').value) {
		if(addr) addr += '+';
		addr += document.getElementById(prefix+'zip').value;
	}
	if(!addr) alert('Please supply a full or partial address first.');
	else {
		addr = escape(addr);
		openConsoleWindow('googlemap', 'http://maps.google.com/maps?q='+addr,800,800);
	}
}

JS;
}
?>