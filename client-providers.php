<? // client-providers.php
// Allow maintenance of the client's Do Not Assign list, and show each active, eligible sitter
// in a list
/* Params
id*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";

extract($_REQUEST);

if(userRole() == 'o') locked('o-');
else locked('d-');

if(!$id) $error = "No client ID supplied.";
else if(!($client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $id", 1))) $error = "Client not found.";

if($error) {
	echo $error;
	include "frame-end.html";
	exit;
}

if($_POST) {
	
	updateClientsDoNotAssignList($id, $_POST, $severConnections=false);
	savePreferredProviderIds($id, $preferred);
	$_SESSION['frame_message'] = 'Saved Changes.';
};


$banned = providerIdsWhoWillNotServeClient($id);

$available = array_diff(fetchCol0("SELECT * FROM tblprovider WHERE active = 1", 1), $banned);

$distances = sittersByDistanceFrom($id, $available); // sitterid=>distance

$units = getI18Property('distanceunit');
$units = $units ? $units : "mile,mi";
$units = explode(',', $units);
$units = $units[1] ? $units[1] : 'mi';


// BLACKLIST / DO NOT SERVE FNS #########################
function dumpDoNotServeElements() {
	global $id;
	$blackList = blackListTable();
	$addBlackListButton = echoButton('', 'Add Sitter to Do Not Assign List', 'chooseSitterToBlackList()', null, null, 1, 'Remember to Save Changes afterward!');
	
	echo "<hr><h3>Do not Assign:</h3> $addBlackListButton<p>";
	echo "<div id='blacklistadditions' colspan=$numcols style='border-bottom:solid black 1px;'></div>";
	if(!$blackList) echo "<span class='tiplooks' id='anyproviders'>Any sitter can serve this client.</span>";
	else {
		echo "<p><span class='tiplooks'>Selected sitter names will not appear in sitter menus for this client.</span>";
		echo blackListTable();
	}
	echo "<hr>";
}

function dumpPreferredSittersListElements() {
	global $id, $client;
	$preferredSitters = preferredSittersList($id);
	$addPSButton = echoButton('', 'Add a Preferred Sitter', 'chooseAPreferredSitter()', null, null, 1, 'Remember to Save Changes afterward!');
	echo "<hr><h3>Preferred Sitters:</h3> $addPSButton<p>";
	if($client['defaultproviderptr']) 
		$providerNote = '<b>Default sitter: </b>'
			.fetchRow0Col0(
					"SELECT CONCAT_WS(' ', fname, lname)
						FROM tblprovider 
						WHERE providerid = {$client['defaultproviderptr']}
						LIMIT 1", 1);
	else $providerNote = "<span class='tiplooks'>This client does not have a default provider.</span>";
	echo "<p>$providerNote</p>";
	echo "<div id='prefferdadditions' colspan=$numcols style='border-bottom:solid black 1px;'></div>";
	if(!$preferredSitters) echo "<span class='tiplooks' id='nonepredferred'>No sitters designated.</span>";
	else {
		echo "<p class='tiplooks'>Checked sitters will appear ahead of others in sitter menus for this client.</p>
					<ol class='pagenote'>
					<li>Click arrows on the left or the Add button above to add sitters.
					<li>Drag and drop sitter names to change order.
					<li>Remember to <b>Save Changes</b>.
					</ol>";
		echo $preferredSitters;
	}
	echo "<hr>";
}

function preferredSittersList($clientid) {
	// generate an ordered list of sitter names with a checkbox for each
	$preferred = getPreferredProviderIds($clientid, $preferred);
	ob_start();
	ob_implicit_flush(0);
	echo "<ol id='preferredlist'>";
	if($preferred) $names = fetchKeyValuePairs(
		"SELECT providerid, CONCAT(fname, ' ', lname, if(nickname, CONCAT('(', nickname, ')'), ''))
			FROM tblprovider WHERE providerid IN (".join(',', $preferred).")", 1);
	foreach($preferred as $id) 
		preferredSitterElement($names[$id], $id);
	echo "</ol>";
	$list = ob_get_contents();
	ob_end_clean();
	return $list;
}

function preferredSitterElement($name, $id) {
	echo "<li title=$id><input type='checkbox' name='preferred[]' value='$id' checked>$name";
}

function blackListTable() {
	global $id;
	$blackList = join(',', providerIdsWhoWillNotServeClient($id));
	//$blackListReasons = doNotServeClientReasons($id);

	if(!$blackList) return;
	$providerDetails = fetchAssociations(
		"SELECT providerid, active, CONCAT_WS(' ', fname, lname) as providername, CONCAT_WS(', ', lname, fname) as sortname
			FROM tblprovider WHERE providerid IN ($blackList)
			ORDER BY sortname");
	$cols = array_chunk($providerDetails, max(count($providerDetails) / 4 + (count($providerDetails) % 4 ? 1 : 0), 1));
	$numcols = max(count($cols), 1);
	ob_start();
	ob_implicit_flush(0);
	echo "<table><tr>";
	foreach($cols as $col) {
		echo "<td style='padding-left:30px;vertical-align:top;'>";
		foreach($col as $provider) {
			$checked = 1;
			$providername = $provider['providername'];
			if(!$provider['active']) $providername = "<span style='color:#993333;font-style:italic;'>$providername</span>";
			labeledCheckbox(
				"$providername", 
				"dna_{$provider['providerid']}", $checked, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true) ;
			if(mattOnlyTEST()) {
				$reason = $blackListReasons[$provider['providerid']];
				$reason = $reason != 1 ? "Reason: $reason" : "Click to supply a reason";
				echo " <span style='cursor: pointer;' onclick='editReason({$provider['providerid']});' 
					title='$reason'>&#9998;</span>";
			}
			echo "<br>";
		}
		echo "</td>";
	}
	echo "</tr></table>";
	$table = ob_get_contents();
	ob_end_clean();
	return $table;
}


function dumpBlackListJavascript() {
	global $id;
	$blackList = join(',', providerIdsWhoWillNotServeClient($id));
	/*if(mattOnlyTEST()) {
		$blackListReasons = doNotServeClientReasons($id);
		foreach($blackListReasons as $i => $reason)
			$blackListReasons[$i] = "$i: \"".($blackListReasons[$i] == 1 ? "" : $blackListReasons[$i])."\"";
		$blackListReasons = "var blacklistreasons = {".join(', ', $blackListReasons)."};";
		$pleaseExplainWhy = "&note=Please+explain+why";
	}*/
	
if(mattOnlyTEST()) {
	$blackListReasons = doNotServeClientReasons($id);
	foreach($blackListReasons as $i => $reason)
		$blackListReasons[$i] = "$i: \"".($blackListReasons[$i] == 1 ? "" : $blackListReasons[$i])."\"";
	$blackListReasons = "var blacklistreasons = {".join(', ', $blackListReasons)."};";
	$pleaseExplainWhy = "&note=Please+explain+why";
}
	echo <<<JAVASCRIPT
// BLACKLIST / DO NOT SERVE
var blacklist = [$blackList];
function chooseSitterToBlackList() {
	url = "provider-chooser-lightbox.php?prompt=Do+not+assign&update=donotserve$pleaseExplainWhy";
	$.fn.colorbox({href:url, width:"450", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}

function chooseAPreferredSitter() {
	url = "provider-chooser-lightbox.php?prompt=Designate a sitter preferred&update=preferredlist";
	$.fn.colorbox({href:url, width:"450", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
}

$blackListReasons

function updateBlackist(value) { // e.g., "992|Joe Smith"
	value = value.split('|');
	providerid = value[0];
	providername = value[1];
	if(!addProviderToBlacklist(providerid)) alert(providername+" is already listed.");
	else {
		var blacklistadditions = document.getElementById('blacklistadditions');
		if(blacklistadditions.innerHTML == '') blacklistadditions.innerHTML = "Click <b>Save Changes</b> to add the following:<br>";
		if(typeof blacklistreasons != 'undefined') {//  && value.length == 3
			blacklistreasons[providerid] = value[2];
			//alert(clientid+" -- "+JSON.stringify(blacklistreasons));
			providername = providername + " <span style='cursor: pointer;' onclick='editReason("+providerid+");' title='Edit reason'>&#9998;</span>";
		}
		blacklistadditions.innerHTML = blacklistadditions.innerHTML+
			"<input type=checkbox id='dna_"+providerid+"' name='dna_"+providerid+"' CHECKED> "+providername+"<br>";
		if(document.getElementById('anyproviderids')) document.getElementById('anyproviderids').style.display='none';
	}
}

function addProviderToBlacklist(providerid) {
	for(var i=0; i<blacklist.length; i++)
		if(blacklist[i] == providerid) return false;
	blacklist[blacklist.length] = providerid;
	return true;
}

function editReason(providerid) {
	var reason = blacklistreasons[providerid];
	$.fn.colorbox({html:
		"Please explain why:<p><input id='blacklistreason' style='width:300px' value='"+reason+"'><br>"+
		"<input type='button' value='Save' onclick='blacklistreasons["+providerid+"] = $(\"#blacklistreason\").val();$.fn.colorbox.close();'>",
		width:'350', height:'170', iframe: false, scrolling: true, opacity: '0.3'});
}
// END BLACKLIST / DO NOT SERVE FNS #########################

JAVASCRIPT;
}
// END BLACKLIST / DO NOT SERVE FNS #########################





$breadcrumbs = "<a href='client-edit.php?id=$id'>{$client['fname']} {$client['lname']}</a>";

if(usingMobileSitterApp()) include "mobile-frame.php";
else include "frame.html";

$pageTitle = "Sitters for {$client['fname']} {$client['lname']}";

echo "<h2>$pageTitle</h2>";
echoButton('', 'Save Changes', 'saveChanges()');
echo "<p>";
echo "<form name='clientprovidersform' method='POST'>";
hiddenElement('thiswasposted', 1);
// BLACKLIST / DO NOT SERVE
echo "<table>\n"; // 
echo "<tr><td colspan=2 style='background:pink;vertical-align:top;padding:5px;'>";
dumpDoNotServeElements(); //$_SESSION['preferences']['donotserveenabled']
echo "</tr>";
echo "<tr><td style='vertical-align:top;padding:5px;'>";
echo "<h3>Nearest Active Sitters</h3>";
echo "<p class='tiplooks'>Sitters on the Do Not Assign list are excluded.</p>";

if($distances == 'no client address') echo "<p>There is no address on record for this client.";
else  if($distances) {
	$sitters = fetchAssociationsKeyedBy(
			"SELECT providerid, fname, lname, nickname 
				FROM tblprovider 
				WHERE providerid IN (".join(',', array_keys($distances)).")", 'providerid', 1);
if($sitters && (TRUE || mattOnlyTEST())) {

	$datesAndCounts = $sitters ? serviceDataFor($id, array_keys($sitters)) : array();
//print_r($datesAndCounts);
}
	echo "<table>";
	
	$rowCount = 0;
	foreach($distances as $provid => $dist) {
		if(!in_array($provid, $available)) continue;
		$sitter = $sitters[$provid];
		$nn = $sitter['nickname'] ? "({$sitter['nickname']})" : '';
		if($dist == '--') $dist = "<span title='No address on file.'>$dist</span>";
		else {
			$dist = number_format($dist, 2);
			$dist = "$dist $units";
		}
		$safeName = safeValue("{$sitter['fname']} {$sitter['lname']} $nn");
		$addThisButton = ' '.fauxLink('&#9654;', $onClickOfLink="addPreferredSitter(\"$safeName\", {$sitter['providerid']})",
													$noEchoOfLink=true, $titleOfLink='Add to preferred sitters.', $idOfLink=null, $classOfLink='fauxlinknoline', $styleOfLink=null);
if(TRUE ||mattOnlyTEST()) {
		$latestdate = $datesAndCounts[$provid]['date'] ? date('n/j/y', strtotime($datesAndCounts[$provid]['date'])) : '';
		$count = $latestdate ? $datesAndCounts[$provid]['num'] : 0;
		$count = $count > 99 ? '100+' : ($count ? $count : 'No');
		$stats = "<br><i>$count visits.".($latestdate ? "  Latest visit: $latestdate" : "")."</i>";
		
}
		echo "<tr class='futuretask'><td>{$sitter['fname']} {$sitter['lname']} $nn$stats</td><td align='right'>$dist$addThisButton</td></tr>\n";
		
		
		
	}
	echo "</table>";
}
echo "</td>";
echo "<td id='preferredlistcell' style='background:palegreen;vertical-align:top;padding:5px;'>";
dumpPreferredSittersListElements();
echo "</td>";
echo "</tr></table>";
echo "</table>\n"; // 
echo "</form>";
?>
<script language='javascript'>
<?
dumpBlackListJavascript();

?>
function saveChanges() {
	document.clientprovidersform.submit();
}


function update(target, value) {
	if(target == 'donotserve') {
		updateBlackist(value); // e.g., "992|Joe Smith"
	}
	else if(target == 'preferredlist') {
		value = value.split('|');
		providerid = value[0];
		providername = value[1];
		addPreferredSitter(providername, providerid); // e.g., "992|Joe Smith"
	}
}

function addPreferredSitter(name, id) {
	if($("input[name='preferred[]'][value='"+id+"']").toArray().length > 0)
		alert(name+" is already listed.");
	else $("#preferredlist").append('<li title='+id+' style="font-weight:bold;"><input type="checkbox" name="preferred[]" value='+id+' checked>'+name+'</li>');
}
</script>

<script language='javascript' src='jquery-sortable-min.js'></script>
<script language='javascript'>
$(function  () {
  $("#preferredlist").sortable();
});
</script>

<style>
body.dragging, body.dragging * {
  cursor: move !important;
}

.dragged {
  position: absolute;
  opacity: 0.5;
  z-index: 2000;
}

#preferredlist li.placeholder {
  position: relative;
  /** More li styles **/
}
#preferredlist li.placeholder:before {
  position: absolute;
  /** Define arrowhead **/
}
</style>
<?
include "frame-end.html";


