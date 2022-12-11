<? // client-flag-fns.php
require_once "preference-fns.php";

/*
In art folder, interpret any flag-*.jpg as a flag image
All biz flags are in preferences as: biz_flag_1 => officeonly|src|title
All client tags are refs to biz flag keys in client preferences as flag_1 => 13|note
*/

function getAllClientFlagImages() {
	$customflagsdir = "bizfiles/biz_{$_SESSION['bizptr']}/flags";
	$customglobs = array_merge(glob("$customflagsdir/*.jpg"), glob("$customflagsdir/*.png"));
	$allFlags = array_merge(glob("art/flag-*.jpg"), glob("art/flag-*.png"), $customglobs);
if(dbTEST('dogslife')) $allFlags[] = "art/red_blinking_led_18.gif";
	return $allFlags;
}

function getAllClientBillingFlagImages() {
	//$customflagsdir = "bizfiles/biz_{$_SESSION['bizptr']}/flags";
	//$customglobs = array_merge(glob("$customflagsdir/*.jpg"), glob("$customflagsdir/*.png"));
	$allFlags = array_merge(glob("art/billing-block/*")/*, $customglobs*/);
	sort($allFlags);
	$exclude = explode(',', "billing-all.png");
	for($i=0; $i<count($allFlags); $i++) 
		foreach($exclude as $x)
			if(strpos($allFlags[$i], $x))
				unset($allFlags[$i]);
	$allFlags = array_merge($allFlags);
	return $allFlags;
}

function getBizFlagList() {
	$nums = array();
	$flags = array();
	$prefs = $_SESSION['preferences'] ? $_SESSION['preferences'] : fetchPreferences();
	foreach(array_keys($prefs) as $key)
		if(strpos($key, 'biz_flag_') === 0)
			$nums[] = substr($key, strlen('biz_flag_'));
	sort($nums);
	foreach($nums as $i) {
		$flag = $prefs["biz_flag_$i"];
		if(!$flag) continue;
		$flags[$i] = array('flagid'=>$i,
												'officeOnly'=>substr($flag, 0, 1),
												'src'=>substr($flag, 2, ($titleStart = strpos($flag, '|', 2))-2),
												'title'=>substr($flag, strpos($flag, '|', $titleStart)+1));
	}
	return $flags;
}

function getClientFlags($clientid, $officeOnly=false) {
	$flags = array();
	$bizFlags = getBizFlagList();
	for($i=1; $flag = getClientPreference($clientid, "flag_$i"); $i++) {
		$id = ($divider = strpos($flag, '|')) ? substr($flag, 0, $divider) : $flag;
		$bizFlag = $bizFlags[$id];
		if(!$officeOnly || !$bizFlag['officeOnly']) {
			$bizFlag['note'] = $divider ? substr($flag, $divider+1) : '';
			$flags[] = $bizFlag;
		}
	}
	return $flags;
}

function getClientFlagIDs($clientid, $officeOnly=false) {
	$flags = getClientFlags($clientid, $officeOnly);
	foreach($flags as $flag) $ids[] = $flag['flagid'];
	return $ids;
}

function dropClientFlag($clientid, $flagId) {
	$clientFlags = getClientFlags($clientid);
	clearFlags($clientid);
	foreach($clientFlags as $flag)
		if($flag['flagid'] != $flagId)
			addClientFlag($clientid, $flag['flagid'], $flag['note']);
}

function clearFlags($clientid) {
	deleteTable('tblclientpref', "clientptr = $clientid AND property LIKE 'flag_%'", 1);
}

function addClientFlag($clientid, $flagId, $note='') {
	// return true if added, false if already there
	
	for($i=1; $flag = getClientPreference($clientid, "flag_$i"); $i++) {
		$id = ($divider = strpos($flag, '|')) ? substr($flag, 0, $divider) : $flag;
		if($flagId == $id) return;
	}
	setClientPreference($clientid, "flag_$i", "$flagId|$note");
	return true;
}

function clientFlagPanel($clientid, $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, $includeBillingFlags=false) {
	global $emailingVisitSheet; // kludge
	if($emailingVisitSheet) $srcPrefix = globalURL('');
	ob_start();
	ob_implicit_flush(0);

	$flags = getClientFlags($clientid, $officeOnly);
	if(!$officeOnly && !$noEdit) $onClick = "editFlags($clientid)";
	if($onClick) $onClick = "onclick='$onClick'";
	if(!$contentsOnly) echo "<div id='flagpanel' style='padding-left:10px;font-size:8pt;display:inline;cursor:pointer;' $onClick >";
	if(!$flags) {
		if(!$officeOnly && !$noEdit) echo "Click to enter flags";
	}
	else {
		foreach($flags as $flag) {
			if(!$flag['src']) continue;
			$title = safeValue($flag['note'] ? $flag['note'] : $flag['title']);
			echo " <img src='{$srcPrefix}{$flag['src']}' title='$title'>";
		}
	}
	if($includeBillingFlags) echo "<img src='art/spacer.gif' height=1 width=20>".
		clientBillingFlagPanel($clientid, $officeOnly, $noEdit, $contentsOnlyForBillingFlagPanel=true, $onClick, $omitClickToEnter=true);
	
	
	if(!$contentsOnly) echo "</div>";
	$panel = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $panel;
}

function clientBillingFlagPanel($clientid, $officeOnly=false, $noEdit=false, $contentsOnly=false, $onClick=null, $omitClickToEnter=false, $flagsize=20) {
	// implemented same as clientFlagPanel to permit maximum flexibility
	if($officeOnly) return '';
	ob_start();
	ob_implicit_flush(0);
	$flags = getClientBillingFlags($clientid, $officeOnly);
	if(!$noEdit) $onClick = "editFlags($clientid)";
	if($onClick) $onClick = "onclick='$onClick'";
	if(!$flags && !$omitClickToEnter) {
		if(!$noEdit) $ims[] =  "Click to enter flags";
	}
	else {
		$flagsize = $flagsize ? "width=$flagsize height=$flagsize" : '';
		foreach($flags as $flagNum => $flag) {
			if(!$flag['src']) continue;
			$title = safeValue($flag['note'] ? $flag['note'] : $flag['title']);
			$ims[] = " <img src='{$flag['src']}' title='$title' $flagsize>";
			$flagClasses[] = "cbillflag$flagNum";
		}
	}
	$flagClasses = $flagClasses ? "class = 'billflagpanel ".join(' ', $flagClasses)."'" : "class = 'billflagpanel'";
	if(!$contentsOnly) echo "<div id='flagpanel$clientid' $flagClasses style='padding-left:10px;font-size:8pt;display:inline;cursor:pointer;' $onClick >";
	foreach((array)$ims as $im) echo $im;
	if(!$contentsOnly) echo "</div>";
	$panel = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $panel;
}

function clientFlagLegend($clientid, $officeOnly=false, $class=null, $style=null) {
	// No EOLs!
	ob_start();
	ob_implicit_flush(0);
	$flags = getClientFlags($clientid, $officeOnly);
	if(!$flags) return null;
	if($style) $style = "style='$style'";
	if($class) $class = "class='$class'";
	echo "<!-- COUNT[".count($flags)."] -->";
	echo "<table $style>";
	foreach($flags as $flag) {
		$title = str_replace('"', '\"', safeValue($flag['note'] ? $flag['note'] : $flag['title']));
		echo "<td><img src='{$flag['src']}'></td><td>$title</td></tr>";
	}
	echo "</table>";
	$panel = ob_get_contents();
	ob_end_clean();
	return $panel;
}

function clientFlagPanelJS($includeClientName=false) {
	$withname = $includeClientName ? "+'&withname=1'" : '';
	echo "\nfunction editFlags(clientid) {
	$.fn.colorbox({href: \"client-flag-picker.php?clientptr=\"+clientid$withname, width:\"600\", height:\"470\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});
		}\n";
}

function clientFlagPicker($clientid) {
	$includeBillingFlags = 
	  	staffOnlyTEST() 
			|| $_SESSION['preferences']['betaBillingEnabled'] 
			|| $_SESSION['preferences']['betaBilling2Enabled'];
	$bizFlags = getBizFlagList();
	if(!$bizFlags && !$includeBillingFlags) {
		echo "No flags are defined for this business";
		return;
	}
	
	$clientFlags = array();
	foreach(getClientFlags($clientid, $officeOnly) as $flag)
		$clientFlags[$flag['flagid']] = $flag;
		
	// flag number, checkbox, office only, flag, title
	echo "<form name='flagpicker' method='POST'>";
	hiddenElement('clientptr', $clientid);
	hiddenElement('includeBillingFlags', (includeBillingFlags ? 1 : ''));
	if($bizFlags || $includeBillingFlags) echoButton('', "Save Changes", 'document.flagpicker.submit();');
	echo " ";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
	echo "<span style='color:red;'> <span style='font-size:1.5em;'>*</span> Office Only</span>";

	$bizFlagList = getBizFlagList();
	$billingFlagList = (array)getBillingFlagList();
	$totalCount = count($bizFlagList) + ($includeBillingFlags ? count($billingFlagList) : 0) + 1;
	
	$tabletPaging = $_SESSION['tabletdevice'] && $totalCount > 10;
	if($tabletPaging) {
		ob_start();
		ob_implicit_flush(0);
	}

	echo "<table width=100% ><tr><th>&nbsp;</th><th>&nbsp;</th><th style='text-align:left'>Flag</th><th>Title</th><th>Note</th></tr>";
	if($includeBillingFlags) {
		$clientBillFlags = array();
		foreach(getClientBillingFlags($clientid, $officeOnly) as $flag)
			$clientBillFlags[$flag['flagid']] = $flag;
		echo "<tr><td colspan=4>Billing Flags</td><tr>";
		foreach($billingFlagList as $i => $flag) {
			echo "<tr>";
			echo "<td style='color:red;font-size:1.5em;'><b>".($flag['officeOnly'] ? '*' : '&nbsp;')."</td>";
			echo "<td>"; labeledCheckBox('', "billingFlag_$i", in_array($i, array_keys($clientBillFlags))); echo "</td>";
			$src = $flag ? $flag['src'] : 'art/emptyFlagIcon.jpg';
			echo "<td><label for='billingFlag_$i'><img class='flag' src='$src' bordercolor=black width=20 height=20 border=1></label></td>";
			echo "<td>{$flag['title']}</td>";
			echo "<td>";
			countdownInput(250, "billflagnote_$i", $clientBillFlags[$i]['note'], $inputClass='streetInput', 
				$onBlur="document.getElementById(\"countdown_billflagnote_$i\").innerHTML=\"\";if(this.value.length > 0) document.getElementById(\"billingFlag_$i\").checked = 1", 
				$position='afterinput');
			echo "</td>";
			echo "</tr>";
		}
		echo "<tr><td colspan=4>Other Client Flags</td><tr>";
	}
			
	foreach($bizFlagList as $i => $flag) {
		echo "<tr>";
		echo "<td style='color:red;font-size:1.5em;'><b>".($flag['officeOnly'] ? '*' : '&nbsp;')."</td>";
		echo "<td>"; labeledCheckBox('', "bizFlag_$i", in_array($i, array_keys($clientFlags))); echo "</td>";
		$src = $flag ? $flag['src'] : 'art/emptyFlagIcon.jpg';
		echo "<td><label for='bizFlag_$i'><img id='src_$i' class='flag' src='$src' bordercolor=black border=1></label></td>";
		echo "<td>{$flag['title']}</td>";
		echo "<td>";
		countdownInput(250, "flagnote_$i", $clientFlags[$i]['note'], $inputClass='streetInput', 
			$onBlur="document.getElementById(\"countdown_flagnote_$i\").innerHTML=\"\";if(this.value.length > 0) document.getElementById(\"bizFlag_$i\").checked = 1", 
			$position='afterinput');
		echo "</td>";
		echo "</tr>";
	}
	echo "</table>";
	if($tabletPaging) {
		$content = ob_get_contents();
		ob_end_clean();
		require_once "js-gui-fns.php";
		$content = "<div style='width:500px;height:300px;overflow:scroll;display:block;'>$content</div>";
		
		pagingBox($content);
	}
	
	echo "</form>";
	if($tabletPaging) {
		echo '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>';
		dumpPagingBoxStyle();
		dumpPagingBoxJS('includescripttags');
	}
	
}

function compactClientFlagPicker($selectedFlags, $selectedBillingFlags='', $includeBillingFlags=false, $maxCols=10) {
	// $selectedFlags "1,3,4,8,..."
	$selectedFlags = explode(',', (string)$selectedFlags);
	$selectedBillingFlags = explode(',', (string)$selectedBillingFlags);
	$bizFlagList = getBizFlagList();
	if(!$bizFlagList && !$includeBillingFlags) {
		echo "No flags are defined for this business";
		return;
	}
	
	// flag number, checkbox, office only, flag, title
	//echo "<form name='flagpicker' method='POST'>";
	hiddenElement('includeBillingFlags', (includeBillingFlags ? 1 : ''));

	$billingFlagList = (array)getBillingFlagList();
	$totalCount = count($bizFlagList) + ($includeBillingFlags ? count($billingFlagList) : 0) + 1;
	
	$tabletPaging = FALSE && $_SESSION['tabletdevice'] && $totalCount > 10;
	if($tabletPaging) {
		ob_start();
		ob_implicit_flush(0);
	}
	$col = 0;
	echo "<table>";
	foreach($bizFlagList as $i => $flag) {
		if($col == $maxCols) {
			echo "</tr>";
			$col = 0;
		}
		if($col == 0) echo "<tr>";
		$col += 1;
		$selected = in_array($i, $selectedFlags) ? '1' : '0';
		$selectedClass = in_array($i, $selectedFlags) ? 'selected' : '';
		$title = safeValue($flag['title']);
		$src = $flag ? $flag['src'] : 'art/emptyFlagIcon.jpg';
		echo "<td class='flagtd $selectedClass' onclick='toggleFlag($(this), $i)' title='$title'>";
		hiddenElement("flag_$i", $selected);
		echo "<img id='src_$i' class='flag' src='$src' bordercolor=black border=1></td>";
	}
	echo "</tr>";
	if($includeBillingFlags) {
		echo "<tr><td colspan=4>Billing Flags</td><tr>";
		foreach($billingFlagList as $i => $flag) {
			echo "<tr>";
			echo "<td style='color:red;font-size:1.5em;'><b>".($flag['officeOnly'] ? '*' : '&nbsp;')."</td>";
			echo "<td>"; labeledCheckBox('', "billingFlag_$i", in_array($i, array_keys($clientBillFlags))); echo "</td>";
			$src = $flag ? $flag['src'] : 'art/emptyFlagIcon.jpg';
			echo "<td><label for='billingFlag_$i'><img class='flag' src='$src' bordercolor=black border=1 width=20 height=20></label></td>";
			echo "<td>{$flag['title']}</td>";
			echo "<td>";
			countdownInput(250, "billflagnote_$i", $clientBillFlags[$i]['note'], $inputClass='streetInput', 
				$onBlur="document.getElementById(\"countdown_billflagnote_$i\").innerHTML=\"\";if(this.value.length > 0) document.getElementById(\"billingFlag_$i\").checked = 1", 
				$position='afterinput');
			echo "</td>";
			echo "</tr>";
		}
		echo "<tr><td colspan=4>Other Client Flags</td><tr>";
	}
			
	echo "</table>";
	if($tabletPaging) {
		$content = ob_get_contents();
		ob_end_clean();
		require_once "js-gui-fns.php";
		$content = "<div style='width:500px;height:300px;overflow:scroll;display:block;'>$content</div>";
		
		pagingBox($content);
	}
	
	//echo "</form>";
	if($tabletPaging) {
		echo '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>';
		dumpPagingBoxStyle();
		dumpPagingBoxJS('includescripttags');
	}
	
}

$maxBillingFlags = staffOnlyTEST() ? 10 : 5;
function getMaxBillingFlags() {
	return staffOnlyTEST() ? 10 : 5;
}

function useBlockBillingFlags() {
	//return $_SESSION['preferences']['betaBilling2Enabled'];
	return true;
}

function billFlagSrc($i) {
	if(useBlockBillingFlags()) return "art/billing-block/billflag-$i.png";
	else return "art/billflag-$i.jpg";
}

function updateBillingFlags() {
	$maxBillingFlags = getMaxBillingFlags();
	require_once "preference-fns.php";
	$prefs = fetchPreferences();
	for($i=1; $i<=$maxBillingFlags; $i++) {
		if($cust = $prefs["billing_flag_$i"]) { // art/billing-block/billflag-1.png|Credit
			$cust = explode('|', $cust);
			$cust = trim("{$cust[1]}");
			setPreference("billing_flag_$i", billFlagSrc($i).'|'.$cust);
		}
	}
}

function NEWbillingFlagTable() {
	global $maxBillingFlags;
	$billingFlags = getBillingFlagList();
	//print_r($billingFlags);
	$flagCounts = fetchKeyValuePairs(
		"SELECT SUBSTRING(property, CHAR_LENGTH('billing_flag_')+1) as flagid, count(*) FROM tblclientpref WHERE property LIKE 'billing_flag_%' GROUP BY flagid");
	echo "<table width=50%><tr><th>&nbsp;</th><th>Flag</th><th>Title</th></tr>";
	for($i = 1; $i <= $maxBillingFlags; $i++) {
		$flag = $billingFlags[$i];
		echo "<tr><td class='fontSize1_1em'>Billing Flag #$i</td>";
		$src = $flag ? $flag['src'] : billFlagSrc($i);
		//echo "<td><img class='billingflag'  id='bill_src_$i' src='$src' bordercolor=black border=1><td>";
		echo "<td><img id='bill_src_$i' class='billingflag' src='$src' onclick='pickBillingFlagIcon($i)' style='cursor:pointer;width:20px;height:20px;' bordercolor=black border=1><td>";
		hiddenElement("billflag_src_$i", $src);
		labeledInput('', "billflag_title$i", $flag['title']);
		//if($flagCounts[$i]) billflag_src_
		//	fauxLink(" Clients : ".($flagCounts[$i] ? $flagCounts[$i] : '0'), "flagReport($i)");
		echo "</td></tr>";
	}
	echo "</table>";
	echo "
<script language='javascript'>
//function flagReport(flagid) {
//	$.fn.colorbox({href: \"client-flags.php?flagid=\"+flagid, width:\"600\", height:\"470\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});
//}	

function pickBillingFlagIcon(i) {
	var imgs = selectedBillingFlagImages().join('|');
	var src = document.getElementById('bill_src_'+i).src; $TEST
	$.fn.colorbox({href: \"client-flag-icon-picker.php?billing=1&index=\"+i+\"&imgs=\"+escape(imgs)+
		\"&src=\"+encodeURIComponent(src), width:\"615\", height:\"470\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});
}

function selectedBillingFlagImages() {
	var flags = new Array();
	for(var i=0; i < document.images.length; i++) {
		var img = document.images[i];
		if(img.className == 'billingflag' && img.src)
			flags[flags.length] = img.src;
	}
	return flags;
}



</script>";
}



function billingFlagTable() {
	if(staffOnlyTEST() /* BILLFLAGCHOOSER */) return NEWbillingFlagTable();
	global $maxBillingFlags;
	$billingFlags = getBillingFlagList();
	$flagCounts = fetchKeyValuePairs(
		"SELECT SUBSTRING(value, 1, LOCATE('|', value)-1) as flagid, count(*) FROM tblclientpref WHERE property LIKE 'billingflag_%' GROUP BY flagid");
	echo "<table width=50%><tr><th>&nbsp;</th><th>Flag</th><th>Title</th></tr>";
	for($i = 1; $i <= $maxBillingFlags; $i++) {
		$flag = $billingFlags[$i];
		echo "<tr><td class='fontSize1_1em'>Billing Flag #$i</td>";
		$src = billFlagSrc($i);
		echo "<td><img class='billingflag' src='$src' bordercolor=black border=1><td>";
		hiddenElement("billflag_src_$i", $src);
		labeledInput('', "billflag_title$i", $flag['title']);
		if($flagCounts[$i]) 
			fauxLink(" Clients : ".($flagCounts[$i] ? $flagCounts[$i] : '0'), "flagReport($i)");
		echo "</td></tr>";
	}
	echo "</table>";
}

function getBillingFlagList() {
	$maxBillingFlags = getMaxBillingFlags();
	if($_SESSION['preferences']) $prefs = $_SESSION['preferences'];
	else $prefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
	$nums = array();
	foreach(array_keys($prefs) as $key)
		if(strpos($key, 'billing_flag_') === 0)
			$nums[] = substr($key, strlen('billing_flag_'));
	sort($nums);
	for($i = 1; $i <= $maxBillingFlags; $i++) {
		$flag = $prefs["billing_flag_$i"] ? $prefs["billing_flag_$i"] : billFlagSrc($i)."|";
		$titleStart = strpos($flag, '|', 2) !== false ? strpos($flag, '|', 2)+2 : strlen($flag);
		$flags[$i] = array('flagid'=>$i,
												'src'=>substr($flag, 0, $titleStart-2),
												'title'=>substr($flag, strpos($flag, '|')+1));
	}
	return $flags;
}

function getClientBillingFlags($clientid, $officeOnly=false) {
	$maxBillingFlags = getMaxBillingFlags();
	$flags = array();
	if($officeOnly) return $flags;
	$bizFlags = getBillingFlagList();
//if($clientid == 468) {echo "[[".print_r($bizFlags, 1)."]]";}
	
	for($i=1; $i <= $maxBillingFlags; $i++) {
		if(is_array($flag = getClientPreference($clientid, "billing_flag_$i", $arrayIfDefault=true))) continue;
		$bizFlag = $bizFlags[$i];
		$bizFlag['note'] = ($flag == '|' ? '' : $flag);
		$flags[$i] = $bizFlag;
	}
	return $flags;
}



function bizFlagTable() {
	$flagCounts = fetchKeyValuePairs(
		"SELECT SUBSTRING(value, 1, LOCATE('|', value)-1) as flagid, count(*) FROM tblclientpref WHERE property LIKE 'flag_%' GROUP BY flagid");
	$bizFlags = getBizFlagList();
	$maxFlags = count(getAllClientFlagImages());
	echo "<table width=100%><tr><th>&nbsp;</th><th>Office<br>Only</th><th>Flag</th><th>Title</th></tr>";
	for($i = 1; $i <= $maxFlags; $i++) {
		$flag = $bizFlags[$i];
		echo "<tr><td class='fontSize1_1em'>Flag #$i</td>";
		$src = $flag ? $flag['src'] : 'art/emptyFlagIcon.jpg';
		echo "<td>";labeledCheckBox('', "officeonly_$i", $flag['officeOnly']);
		echo "</td>";
		echo "<td><img id='src_$i' class='flag' src='$src' onclick='pickIcon($i)' style='cursor:pointer' bordercolor=black border=1><td>";
		hiddenElement("flag_src_$i", $src);
		labeledInput('', "flag_title$i", $flag['title']);
		if($flagCounts[$i]) 
			fauxLink(" Clients : ".($flagCounts[$i] ? $flagCounts[$i] : '0'), "flagReport($i)");
		echo "</td></tr>";
	}
	echo "</table>";
//$TEST = mattOnlyTEST() ? "alert(\"client-flag-icon-picker.php?index=\"+i+\"&imgs=\"+escape(imgs)+\"&src=\"+escape(src));" : '';;
//$TEST = mattOnlyTEST() ? "document.location.href=\"client-flag-icon-picker.php?index=\"+i+\"&imgs=\"+escape(imgs)+\"&src=\"+escape(src);" : '';;
//$TEST = mattOnlyTEST() ? "alert(encodeURIComponent(document.getElementById('src_'+i).src));" : '';;
//$TEST = mattOnlyTEST() ? "alert(imgs);return;" : '';;
		
	echo "
<script language='javascript'>
function pickIcon(i) {
	var imgs = selectedImages().join('|');
	var src = document.getElementById('src_'+i).src; $TEST
	$.fn.colorbox({href: \"client-flag-icon-picker.php?index=\"+i+\"&imgs=\"+escape(imgs)+
		\"&src=\"+encodeURIComponent(src), width:\"615\", height:\"470\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});
}

function selectedImages() {
	var flags = new Array();
	for(var i=0; i < document.images.length; i++) {
		var img = document.images[i];
		if(img.className == 'flag' && img.src 
				&& (img.src.indexOf('/flag-') != -1
						|| img.src.indexOf('/flags/') != -1) // for business-specific flags
					) flags[flags.length] = img.src;
	}
	return flags;
}

function flagReport(flagid) {
	$.fn.colorbox({href: \"client-flags.php?flagid=\"+flagid, width:\"600\", height:\"470\", iframe:true, scrolling: \"auto\", opacity: \"0.3\"});
}	

</script>";
}

function bizBillingFlagPicker($i, $imgs, $src) {
	
	$allFlags = getBillingFlagList();
	
	$inuse = array_map('strtolower', explode('|', $imgs));
	$currentIcon = $src == 'art/emptyFlagIcon.jpg' ? "(None)" : "<img src='$src' width=20 height=20>";
	echo "<h3>Current icon: $currentIcon</h3>";
	if($src != 'art/emptyFlagIcon.jpg') echoButton('', 'Drop Current Icon for this Flag', "drop($i)");
	echo "<h3>Choose a new image</h3>";
	$src = strtolower(globalURL(src));
	foreach(getAllClientBillingFlagImages() as $flag) {
		$url = strtolower(globalURL($flag));
		if(in_array($url, $inuse) && $url != $src) continue;
		echo "<img src='$flag' onclick='picked($i, \"$flag\")' style='cursor:pointer;width:20px;height:20px;'> ";
	}
	echo "<p>";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');

	echo "
<script language='javascript'>
function picked(i, src) {
	parent.document.getElementById('bill_src_'+i).src = src;
	parent.document.getElementById('billflag_src_'+i).value = src;
	parent.$.fn.colorbox.close();
}

function drop(i) {
	parent.document.getElementById('bill_src_'+i).src = 'art/emptyFlagIcon.jpg';
	parent.document.getElementById('billflag_src_'+i).src = 'art/emptyFlagIcon.jpg';
	parent.$.fn.colorbox.close();
}
</script>";	
}

	
function bizFlagPicker($i, $imgs, $src) {
	
	$allFlags = getBizFlagList();
	$inuse = array_map('strtolower', explode('|', $imgs));
	$currentIcon = $src == 'art/emptyFlagIcon.jpg' ? "(None)" : "<img src='$src'>";
	echo "<h3>Current icon: $currentIcon</h3>";
	if($src != 'art/emptyFlagIcon.jpg') echoButton('', 'Drop Current Icon for this Flag', "drop($i)");
	echo "<h3>Choose a new image</h3>";
	$src = strtolower(globalURL(src));
	foreach(getAllClientFlagImages() as $flag) {
		$url = strtolower(globalURL($flag));
		if(in_array($url, $inuse) && $url != $src) continue;
		echo "<img src='$flag' onclick='picked($i, \"$flag\")' style='cursor:pointer'> ";
	}
	echo "<p>";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');

	echo "
<script language='javascript'>
function picked(i, src) {
	parent.document.getElementById('src_'+i).src = src;
	parent.document.getElementById('flag_src_'+i).value = src;
	parent.$.fn.colorbox.close();
}

function drop(i) {
	parent.document.getElementById('src_'+i).src = 'art/emptyFlagIcon.jpg';
	parent.$.fn.colorbox.close();
}
</script>";	
}

function flagReportTable($flagid) {
	$flag = $_SESSION['preferences']["biz_flag_$flagid"];
	$src = substr($flag, 2, ($titleStart = strpos($flag, '|', 2))-2);
	$title = substr($flag, strpos($flag, '|', $titleStart)+1);
	$sql = "SELECT clientptr, CONCAT_WS(' ', fname, lname) as name, 
									CONCAT_WS(',', lname, fname) as sortname, value as flagdata 
					FROM tblclientpref
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE value LIKE '$flagid|%'
					ORDER BY sortname";
	$clients = fetchAssociations($sql);
	echo "<h2 style='text-align:center;'>$title <img src='$src'> clients: ".count($clients)."</h2>\n";
	echo "<table width='90%'>";
	echo "<tr><th>Client</th><th>Note</th></tr>";
	foreach($clients as $client) 
		echo "<tr><td style='border-bottom:solid black 1px;'>{$client['name']}</td>"
					."<td style='border-bottom:solid black 1px;'>"
					.(($note = substr($client['flagdata'], strpos($client['flagdata'], '|')+1))
						? $note : '&nbsp;')
					."</td></tr>";
	echo "<table>";
}

function flagReportTableWithDelete($flagid) {
	$flag = $_SESSION['preferences']["biz_flag_$flagid"];
	$src = substr($flag, 2, ($titleStart = strpos($flag, '|', 2))-2);
	$title = substr($flag, strpos($flag, '|', $titleStart)+1);
	$sql = "SELECT clientptr, CONCAT_WS(' ', fname, lname) as name, 
									CONCAT_WS(',', lname, fname) as sortname, value as flagdata 
					FROM tblclientpref
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE value LIKE '$flagid|%'
					ORDER BY sortname";
	$clients = fetchAssociations($sql);
	echo "<h2 style='text-align:center;'>$title <img src='$src'> clients: ".count($clients)."</h2>\n";
	echo "<form name='dropflagforclients' method='POST'>";
	hiddenElement('flagidtodrop', $flagid);
	echo fauxLink('Select All', "selectAll(1)", 'Select all clients.');
	echo " - ";
	echo fauxLink('Deselect All', "selectAll(0)", 'Deselect all clients.');	
	echo " - ";
	echoButton('', "Remove flag from selected clients...", "unassignFlag()");
	echo "<table width='90%'>";
	echo "<tr><th></th><th>Client</th><th>Note</th></tr>";
	foreach($clients as $client) {
		$cbid = "client_{$client['clientptr']}";
		echo "<tr><td style='border-bottom:solid black 1px;'><input type='checkbox' id='$cbid' name='$cbid'></td>"
					."<td style='border-bottom:solid black 1px;'>{$client['name']}</td>"
					."<td style='border-bottom:solid black 1px;'>"
					.(($note = substr($client['flagdata'], strpos($client['flagdata'], '|')+1))
						? $note : '&nbsp;')
					."</td></tr>";
	}
	echo "<table></form>";
	
	echo <<<JS
<script language='javascript'>
function selectAll(onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) {
		if(els[i].type == 'checkbox' && !els[i].disabled && els[i].name.indexOf('client_') == 0) {
			els[i].checked = onoff;
		}
	}
}

function getSelections(emptyMsg) {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') > 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	if(sels.length == 0) {
		alert(emptyMsg);
	}
	return sels;
}

function unassignFlag() {
	var clients = getSelections("Please select at least one client first");
	if(clients.length == 0) return;
	if(!confirm("You are about to remove this flag from "+clients.length+" clients. Proceed?"))
		return;
	document.dropflagforclients.submit();
}
</script>
JS;
}
