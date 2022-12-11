<? // vets-us-find.php

// bought at http://www.data-lists.com/veterinarians-database/
// OR http://www.data-lists.com/veterinarians-database/
require_once "common/init_session.php";
if($_GET['id']) {
	require_once "common/init_db_petbiz.php";
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
}
require_once "common/init_db_common.php";
require_once "gui-fns.php";

locked('o-');

$nameMin = 3;
$addMin = 2;
$limit = 25;

if($_GET['xmlfor']) {  // AJAX
	$source = fetchFirstAssoc("SELECT * FROM vetclinic_us where clinicid = {$_GET['xmlfor']}");
	echo "<clinic>";
	foreach($source as $k=>$v)  echo "<$k><![CDATA[$v]]></$k>";
	echo "</clinic>";
	exit;
}

if($_GET['id']) {  // AJAX
	displayGlobalClinicSummary($_GET['id']);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$clinics = fetchAssociations(
		"SELECT * 
			FROM tblclinic
			WHERE officephone = '{$source['officephone']}'
				OR clinicname = '{$source['clinicname']}'
				OR CONCAT_WS('/', fname, lname) = '{$source['fname']}/{$source['lname']}'
				OR CONCAT_WS('/', street1, street2, city, state, zip) 
					= '{$source['street1']}/{$source['street2']}/{$source['city']}/{$source['state']}/{$source['zip']}'");
	if($clinics) {
		require "vet-fns.php";
		echo "<div style='display:block;background:lightgrey'><b>Clinics in your list similar to this one:</b><p>";
		foreach($clinics as $clinic) {
			displayClinicSummary($clinic['clinicid']);
		}
		echo "</div>";
	}
	exit;
}
 
if($_GET['addressPat'] || $_GET['namePat']) {  // AJAX
 $addressPat = ''.$_GET['addressPat'];
 $namePat = ''.$_GET['namePat'];
 if(strlen($addressPat) < $addMin && strlen($namePat) < $nameMin) {
	 exit;
 }
 if(strlen($namePat) >= $nameMin) $filter[] = "clinicname LIKE '%$namePat%'";
 if(strlen($addressPat) >= $addMin) 
 		$filter[] = "CONCAT_WS(' ', street1, street2, CONCAT_WS(', ', city, state), zip) LIKE '%$addressPat%'";
 $result = doQuery(
	 "SELECT clinicid, clinicname, city, state 
	 		FROM vetclinic_us
 			WHERE ".join(' AND ', (array)$filter)
 			." ORDER BY clinicname, state, city");
 $found = mysqli_num_rows($result);
 $found = $found > $limit ? "$found found. $limit shown." : "$found found.";
//echo "<ERROR>SELECT clinicname, city, state  	 		FROM vetclinic_us WHERE ".join(' AND ', (array)$filter)." ORDER BY clinicname, state, city LIMIT 25</ERROR>";exit;
 echo "<clinics><found>$found</found>";
 while(($clinic = mysqli_fetch_array($result, MYSQL_ASSOC)) && $n < $limit) {
	$n++;
	 echo "<c><nm><![CDATA[{$clinic['clinicname']}]]></nm>";
	 echo "<cid><![CDATA[{$clinic['clinicid']}]]></cid>";
	 echo "<add><![CDATA[{$clinic['city']}, {$clinic['state']}]]></add></c>";
 }
 echo "</clinics>";
 exit;
}
function displayGlobalClinicSummary($clinicId) {
	require_once "gui-fns.php";
	global $source, $clinicFieldLabels;
	$source = fetchFirstAssoc("SELECT * FROM vetclinic_us where clinicid = $clinicId");
	echo "\n<table width=100% border=1 bordercolor=black bgcolor=white>\n";
	echo "<tr><td colspan=2>";
	$buttonLabel = $_GET['addDetails'] ? 'Add These Details to the Clinic' : 'Add This Clinic To My List';
	echoButton('', $buttonLabel, "chooseAndClose($clinicId)");
	echo "</td></tr>";
	echo "<tr><td valign=top>\n<table>\n"; // COL 1
	globalClinicLabelRow('clinicname');
	$principal = $source['lname'] 
		? "{$source['fname']} {$source['lname']} {$source['creds']}"
		: '';
	if($principal)	labelRow('Owner/Manager:', '', $principal, null, null, null, null, true);
	$addr = array();
	foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $source[$k];
	$oneLineAddr = oneLineAddress($addr);
	$addr = htmlFormattedAddress($addr);
	if($addr)	labelRow('Address:', '', $addr, null, null, null, null, true);
	echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>"; // COL 2

	globalClinicLabelRow('email');
	globalClinicLabelRow('officephone');
	globalClinicLabelRow('cellphone');
	globalClinicLabelRow('homephone');
	globalClinicLabelRow('fax');
	globalClinicLabelRow('pager');
	echo "</td></tr></table></td></tr>"; // END COL 2

	/*
		var indirect = 'url,category,subcategory,webmetatitle,webmetadescription,webmetakeys'.split(',');
		var labels = {url:'URL', category:'Service', subcategory:'Specialty',
									webmetatitle:'Web Title',webmetadescription:'Web Description',webmetakeys:'Web Keys'};
		for(var j=0;j<indirect.length;j++) {
			if(tag == indirect[j]) {
				if(notes != '') notes += '\n';
				notes += labels[tag]+': '+val;
*/

	echo "<tr><td valign=top colspan=2><table>"; // NOTES
	foreach(explode(',', 'url,category,subcategory,webmetatitle,webmetadescription,webmetakeys') as $field)
		globalClinicLabelRow($field);
	echo "</table></td></tr>"; // ROW 2
	echo "<tr><td colspan=2>&nbsp;</td></tr>";
	

	echo "</table>";

}

function globalClinicLabelRow($field, $rowId=null) {
  global $source;
  static $clinicFieldLabels;
	if(!$clinicFieldLabels) {
		$props =
		"clinicname|Clinic Name
		solepractitioner|Sole Practitioner
		fname|First Name
		lname|Last Name
		street1|Address
		street2|Address 2
		city|City
		state|State
		zip|ZIP
		email|Email
		officephone|Office Phone
		cellphone|Cell Phone
		homephone|Home Phone
		fax|Fax
		pager|Pager
		notes|Notes
		afterhours|After Hours
		directions|Directions
		url|URL
		category|Service
		subcategory|Specialty
		webmetatitle|Web Title
		webmetadescription|Web Description
		webmetakeys|Web Keys";

		$clinicFieldLabels = array();
		foreach(explode("\n",$props)  as $line) {
			$pair = explode("|",trim($line));
			$clinicFieldLabels[$pair[0]] = $pair[1];
		}
	}
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	$rowStyle = $rowId ? 'font-family:inherit;' : '';
	$label = $clinicFieldLabels[$field] ? $clinicFieldLabels[$field] : $field;
	if($source[$field])	labelRow("$label:", $field, $source[$field], '', '', $rowId, $rowStyle);
}

// ############################################
require "frame-bannerless.php";

$matchesDIV = "<div id='matches' style='display:block;height:305px;overflow:auto;border:solid darkgrey 1px;'></div>";
$screenIsIPad = strpos($_SERVER["HTTP_USER_AGENT"], 'iPad') !== FALSE;
if($screenIsIPad || $_SESSION["mobiledevice"] || $_SESSION["tabletdevice"]) {
	$matchesDIV = "<div id='matches' style='display:block;height:260px;overflow:auto;border:solid darkgrey 1px;'></div>";
	require_once "js-gui-fns.php";
	ob_start();
	ob_implicit_flush(0);
	pagingBox($matchesDIV);
	$matchesDIV = ob_get_contents();
	ob_end_clean();
}

/*
function pagingBox($content, $name='pagingBox') {
	echo "<table class='pagingBox'>\n";
	echo "<tr><td class='pagingBoxUp' onClick='pb_pageUp(\"$name\")'><img src='art/sort_up.gif' width=20 height=15></td></tr>\n";
	echo "<tr><td id='pagingBox'>$content</td></tr>\n";
	echo "<tr><td class='pagingBoxDown' onClick='pb_pageDown(\"$name\")'><img src='art/sort_down.gif' width=20 height=15></td></tr>\n";
	echo "</table\n";
}

function dumpPagingBoxJS($includescripttags) {

*/

?>
<table width=90%><tr>
<td><span  class='fontSize1_3em boldfont'>Pick a Vet</span> <span class='tiplooks'>Enter part of a name and/or address.  Click names to see details.</span></td>
<td style='text-align:right;'><? echoButton('', 'Quit', 'closeThis()'); ?></td></tr>
</table>
<p>
Clinic Name: <input id='namePat' name='namePat' onkeyup='search()' value='<?= $_GET['findExact'] ? $_GET['findExact'] : '' ?>'>
<img src='art/spacer.gif' width=20 height=1>
Address: <input id='addressPat' name='addressPat' onkeyup='search()'>
<table><tr>
<td style='vertical-align:top;'><?= $matchesDIV ?></td>
<td id='detail' style='vertical-align:top'></td>
</tr>
</table>

<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function search() {
	var namePat = document.getElementById('namePat').value;
	var addressPat = document.getElementById('addressPat').value;
	if(namePat.length < <?= $nameMin ?> && addressPat.length < <?= $addMin ?>) return;
	ajaxGetAndCallWith('vets-us-find.php?addressPat='+escape(addressPat)+'&namePat='+escape(namePat), showClinics, 1);
}

function showClinics(arg, resultxml) {
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	var found = '';
<? " ?>
	found = root.getElementsByTagName('found')[0].firstChild.nodeValue;
	var appts = root.getElementsByTagName('c');
	var atable = 'None found.';
	if(appts.length > 0) {
		atable = "<table>";
		if(found) atable += "<tr><td colspan=2><b>"+found+"</b></td></tr>";
		for(var i=0; i < appts.length; i++) {
			var nm = appts[i].getElementsByTagName('nm')[0].firstChild.nodeValue;
			var clinicid = appts[i].getElementsByTagName('cid')[0].firstChild.nodeValue;
			nm = "<a href='javascript:fetchClinic("+clinicid+")'>"+nm+"</a>";
			atable += "<tr><td style='border-top:solid black 1px;'>"+nm
				+"</td></tr><tr><td style='padding-bottom:7px;'>"
				+appts[i].getElementsByTagName('add')[0].firstChild.nodeValue+"</td>"
				+"</tr>";
		}
		atable += "</table>";
	}
	document.getElementById('matches').innerHTML = atable;
	document.getElementById('detail').innerHTML = '';
}

function fetchClinic(id) {
	var addDetails = <?= $_GET['findExact'] ? "'&addDetails=1'" : '""' ?>;
	ajaxGetAndCallWith('vets-us-find.php?id='+id+addDetails, showClinic, 1);
}

function showClinic(arg, html) {
	document.getElementById('detail').innerHTML = html;
}

function chooseAndClose(id) {
	// update parent
	if(parent && parent.update) parent.update('clinic', id);
	else alert('no parent');
	// close lightbox
	closeThis();
}

function closeThis() {
	if(!parent) ;
	else if(parent.$) parent.$.fn.colorbox.close();
	else window.close();
}

<? if($_GET['findExact']) echo "search();"; ?>
<? if($screenIsIPad || $_SESSION["mobiledevice"] || $_SESSION["tabletdevice"]) {
dumpPagingBoxJS($includescripttags=false);
}
?>

</script>