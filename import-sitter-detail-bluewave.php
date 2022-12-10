<?// import-sitter-detail-bluewave.php

/*
UNDER CONSTRUCTION 11/28/2010

This script parses a Bluewave Sitter Detail Report, .

Sample: S:\clientimports\waggingtail\Wagging-Tails-Sitter-1.htm
https://leashtime.com/import-sitter-detail-bluewave.php?file=waggingtail/Wagging-Tails-Sitter-1.htm

*/
set_time_limit(30);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
locked('o-');
extract($_REQUEST);


if($biz) echo "<h2 style='color:yellow;'>{$biz['bizname']}</h2><h3 style='color:yellow;'>DB: $db</h3>";
else echo "<h2 style='color:orange;'>DB: $db</h2>";
echo "<hr>";

if($_REQUEST['files']) {
	foreach(explode(',', $_REQUEST['files']) as $file) {
		echo "<p>".basename($file).': ';
		if(strpos($file, 'gz')) {
			$dfile = decompress($file);
			$sitter = readSitter($dfile);
			unlink($dfile);
		}
		else $sitter = readSitter($file);
		applySitterDetails($sitter);
		$sitters[] = $sitter;
	}
	
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require  "common/init_db_common.php";
	foreach($sitters as $sitter) {
		$providerid = $sitter['providerid'];
		$loginid = trim($sitter['str_cd']);
		if(!$loginid) $emptyLoginIds++;
		else if($sitter['provideruserid']) $alreadyHaveLogins++;
		else $loginIds[] = mysql_real_escape_string($loginid);
	}
	if($loginIds) $badLoginIds = fetchCol0("SELECT loginid FROM tbluser WHERE loginid IN ('".join("','", $loginIds)."')");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	if($badLoginIds || $emptyLoginIds || $alreadyHaveLogins) {
		if($badLoginIds) echo "<font color=red>Not all logins were set up.  These Login IDs are already in use:<ul>"
			.join('<li>', $badLoginIds).'</ul></font>';
		if($emptyLoginIds) echo "<p><font color=red>$emptyLoginIds of the login ids are empty.</font>";
		if($alreadyHaveLogins) echo "<p><font color=red>$alreadyHaveLogins of the sitters already have logins.</font>";
	}
	$lowercaseBadLoginIds = array_map('strtolower', $badLoginIds);
	foreach($sitters as $sitter) {
		$providerid = $sitter['providerid'];
		$loginid = trim($sitter['str_cd']);
		if($sitter['provideruserid'] || !$providerid || !$loginid || in_array(strtolower($loginid), $lowercaseBadLoginIds)) continue;
		$data = array('bizptr'=>$_SESSION["bizptr"], 'orgptr'=>$_SESSION["orgptr"],
									'loginid'=>$loginid,
									'email'=>$sitter['provideremail'],
									'rights'=>basicRightsForRole('provider'),
									'active'=>1,
									'temppassword'=>randomToken()
									);
		$newuser = addSystemLogin($data, 'clientOrProviderOnly');
		if(is_string($newuser)) echo "<font color=red>$newuser</font><p>";
		else {
			$newusers++;
			updateTable('tblprovider', array('userid'=>$newuser['userid']), "providerid=$providerid", 1);
		}
	}
	if($newusers) echo "<font color=green>$newusers system logins added.</font><p>";
}

function applySitterDetails(&$sitter) {
	// str_rmrk
	if(!$sitter) {
		echo "<font color=red>No sitter id found: ".print_r($sitter, 1)."</font><br>";
		return;
	}
	$prov = fetchFirstAssoc("SELECT * FROM tblprovider WHERE employeeid = {$sitter['str_ID']} LIMIT 1");
	if(!$prov) {
		echo "<font color=red>No sitter found with employeeid = {$sitter['str_ID']}".print_r($sitter, 1)."</font><br>";
		return;
	}
	$sitter['providerid'] = $prov['providerid'];
	$sitter['provideremail'] = $prov['email'];
	$sitter['provideruserid'] = $prov['userid'];
	echo "BW sitter {$sitter['str_ID']} = LT sitter {$prov['providerid']}<br>";
	$mods['taxid'] = $sitter['ssn'];
	$mods['street1'] = $sitter['str_address1'];
	$mods['street2'] = $sitter['str_address2'];
	$mods['city'] = $sitter['str_city'];
	$mods['state'] = $sitter['str_st'];
	$mods['zip'] = $sitter['str_zip'];
	$mods['nickname'] = $sitter['str_fl_nm'];
	$mods['hiredate'] = $sitter['str_strt_dt'];
	$mods['terminationdate'] = $sitter['str_end_dt'];
	$mods['ratetype'] = $sitter['compensation'];
	$noteParts = explodePairsLine('Birthday|dob||Email2|str_eml2||Area|str_grphy||Text Message Address|str_txtmsg_adrs||Remarks|str_rmrk');
	foreach($noteParts as $label => $val)
		if(trim($sitter[$val])) $notes[] = "$label: ".($label == 'Remarks' ? "\n" : "").$sitter[$val];
	if($notes) $mods['notes'] = join("\n", $notes);
	foreach($mods as $k=>$v) if(!$v) unset($mods[$k]);
	echo "<b>{$prov['fname']} {$prov['lname']}</b><br>";foreach($mods as $k=>$v) echo "$k: ".($k=='notes' ? "<textarea row=5 cols=60>$v</textarea>" : $v)."<br>";
	updateTable('tblprovider', $mods, "providerid = {$prov['providerid']}", 1);
	//echo "Login ID: {$sitter['str_cd']}<br>";
}

function readSitter($file) {
	global $line;
//echo "<font color=lightblue>$file</font><br>";	
	$strm = fopen($file, 'r');
	while(getLine($strm)) {
		if(strpos($line, 'str_lst_nm') !== FALSE) $sitter['str_lst_nm'] = getValue($line);
		else if(strpos($line, 'str_frst_nm') !== FALSE) $sitter['str_frst_nm'] = getValue($line);
		else if(strpos($line, 'str_fl_nm') !== FALSE) $sitter['str_fl_nm'] = getValue($line); // nickname
		else if(strpos($line, 'str_phn_hm') !== FALSE) $sitter['str_phn_hm'] = getValue($line);
		else if(strpos($line, 'str_phn_mbl') !== FALSE) $sitter['str_phn_mbl'] = getValue($line);
		else if(strpos($line, 'str_phn_pgr') !== FALSE) $sitter['str_phn_pgr'] = getValue($line); // workphone
		else if(strpos($line, 'str_grphy') !== FALSE) $sitter['str_grphy'] = getValue($line); // area
		else if(strpos($line, 'str_eml"') !== FALSE) $sitter['str_eml'] = getValue($line); // email
		else if(strpos($line, 'str_eml2') !== FALSE) $sitter['str_eml2'] = getValue($line); // alternate email
		else if(strpos($line, 'str_txtmsg_adrs') !== FALSE) $sitter['str_txtmsg_adrs'] = getValue($line); // text message addr
		else if(strpos($line, 'str_address1') !== FALSE) $sitter['str_address1'] = getValue($line); // 
		else if(strpos($line, 'str_address2') !== FALSE) $sitter['str_address2'] = getValue($line); // 
		else if(strpos($line, 'str_city') !== FALSE) $sitter['str_city'] = getValue($line); // 
		else if(strpos($line, 'str_st"') !== FALSE) $sitter['str_st'] = getValue($line); // 
		else if(strpos($line, 'str_zip') !== FALSE) $sitter['str_zip'] = getValue($line); // 
		else if(strpos($line, 'dob') !== FALSE) $sitter['dob'] = getValue($line); // 
		else if(strpos($line, 'ssn') !== FALSE) $sitter['ssn'] = getValue($line); // 
		else if(strpos($line, 'str_strt_dt"') !== FALSE) $sitter['str_strt_dt'] = getValue($line, 'str_strt_dt"'); // 
		else if(strpos($line, 'str_end_dt"') !== FALSE) $sitter['str_end_dt'] = getValue($line, 'str_end_dt"'); // end date

		//						<option value="P" selected selected>Percentage</option>
		//					<option value="F" >Fixed</option>

		else if(strpos($line, 'selected>Percentage') !== FALSE) $sitter['compensation'] = 'percentage'; // 
		else if(strpos($line, 'selected>Fixed') !== FALSE) $sitter['compensation'] = 'fixed'; // 
		else if(strpos($line, 'str_cmpnstn') !== FALSE) $sitter['str_cmpnstn'] = getValue($line); // end date
		else if(strpos($line, 'str_cd"') !== FALSE) $sitter['str_cd'] = getValue($line); // username
		else if(strpos($line, 'value="A" selected>Admin') !== FALSE) $sitter['accessrights'] = 'admin'; // 
		else if(strpos($line, 'value="E" selected>Full') !== FALSE) $sitter['accessrights'] = 'full'; // 
		else if(strpos($line, 'value="R" selected>Limited') !== FALSE) $sitter['accessrights'] = 'limited'; // 
		else if(strpos($line, 'value="O" selected>Owner') !== FALSE) $sitter['accessrights'] = 'owner'; // 
		else if(strpos($line, 'str_ID') !== FALSE) $sitter['str_ID'] = getValue($line); // sitter ID
		else if(strpos($line, 'str_rmrk') !== FALSE) {
			$sitter['str_rmrk'] = getTextAreaContents($line);
			$readingTextArea = strpos($line, '</textarea') === FALSE;
		}
		else if($readingTextArea) {
			$sitter['str_rmrk'] .= getTextAreaContents($line);
			$readingTextArea = strpos($line, '</textarea') === FALSE;
		}

	}
	//print_r($sitter);
	//addSitter($sitter);	
	fclose($strm);
	return $sitter;
}

function getTextAreaContents($line) {
	if(($start = strpos($line, '<textarea')) !== FALSE) {
		$start = strpos($line, '>', $start)+1;
		if($end = strpos($line, '</textarea', $start))
			return substr($line, $start, $end-$start);
		else return substr($line, $start);
	}
	else if($end = strpos($line, '</textarea') === FALSE)
		return "\n".substr($line, $start, $end-$start);
	else return "\n".$line;
}



function htmlize($s) {return str_replace("\n", '<br>', $s);}


function getRow($strm) {
	global  $line, $lineNum; 
	while(getLine($strm)) {
		if(strpos($line, '<tr>') !== FALSE || strpos($line, '</table') !== FALSE) break;  // skip shim
	}
	if(strpos($line, '</table') !== FALSE) return -1;
	$row = array();
	while(strpos($line, '</tr>') === FALSE)
		$row[] = nextTDStripped($strm);
//echo "[[".print_r($row, 1).']]<p>';		
	return $row;
}

function getLine($strm) {
	global $line, $col, $lineNum;
	if(feof($strm) || ($line = fgets($strm)) === FALSE) return FALSE;
	$lineNum++;
	$line = trim(str_replace(chr(0), '', $line));
//if(strpos($line, 'Phone:') !== FALSE) {echo "[$lineNum] $line";;}
	if(strpos($line, '<tr') !== FALSE) $col = 0;
	if(strpos($line, '<td') !== FALSE) $col++;
	return true;
}

function nextTDStripped($strm) {
	global $line, $lineNum;
	$td = '';
	do {
		if(!getLine($strm)) return FALSE;
		if($td) $spacer = "\n"; else $spacer = '';
		$td .= $spacer.$line;
	} while (strpos($line, '</td>') === FALSE && strpos($line, '</tr>') === FALSE);
	return trim(strip_tags($td));
}

function getValue($line, $after=null) {
	//$pattern = '/value=".*"/gi';
	
	if($after) {
		$linestart = strpos($line, $after)+strlen($after);
		$lineend = strpos($line, '>', $linestart);
		$line = substr($line, $linestart, $lineend-$linestart);
		//echo "<p style='color:red;'>[$linestart] [$lineend] [$line]</p>";
	}
	
	$start = strpos($line, 'value="');
	if($start === FALSE) return;
	$start += strlen('value="');
	$end = strpos($line, '"', $start);
	return substr($line, $start, $end-$start);
}
	
	
	
function nextLineStripped($strm) {
	global $line;
	if(!getLine($strm)) return FALSE;
	$line = trim($line);
	return strip_tags($line);
}

function decompress($zipname) {
	ob_start();
	ob_implicit_flush(0);
	readgzfile($zipname);
	$out = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	file_put_contents($fname = sys_get_temp_dir().'/'.basename(substr($zipname, 0, strlen($zipname)-3)), $out);
	unlink($zipname);
	return $fname;
}
