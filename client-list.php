<? //client-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "field-utils.php";
require_once "pet-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "preference-fns.php";

// Determine access privs
$locked = locked('+o-,+p-,#cl');
//$showflags = mattOnlyTEST();

//$flagTEST = true; // staffOnlyTEST() || dbTEST('peakcitypuppy');

$clickToAddFlagsSpan ="<span style=\"cursor:pointer\" title=\"Click to add flags\">&#x2691; &#x2690; &#x2691;</span>";

$max_rows = 25;
$noContactInfo = $_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p';
$columns = array('name'=>'Name', /*'services' => 'Services',*/ 'email'=>'Email / Phone', 'packages'=>'Services'/*'address'=>'Address'*/, 'provider'=>'Sitter', 'pets'=>'Pets');
$colClasses = array('name'=>'nameCol');
if($db == 'leashtimecustomers') {
	unset($columns['packages']);
	unset($columns['provider']);
	unset($columns['pets']);
	unset($colClasses['name']);
	$columns['flags'] = 'Flags';
}
if($noContactInfo) unset($columns['email']);
if(userRole() == 'p') unset($columns['services']);
$colKeys = array_keys($columns);
$columnSorts = array('name'=>'asc','email'=>null);
extract($_REQUEST);

$pattern = mysql_escape_string($pattern);
$patternParam = !isset($pattern) ? '' : $pattern;
$pattern = !isset($pattern) ? '' : 
           (strpos($pattern, '*') !== FALSE ? str_replace('*', '%', $pattern) : "%$pattern%");
$petnames = isset($petnames) ? $petnames : false;

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require "common/init_db_common.php";
$loginIds = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE bizptr = {$_SESSION['bizptr']} AND rights LIKE 'c-%'");
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

$inactive = isset($inactive) ? $inactive : false;
$prospect = isset($prospect) ? $prospect : false;


$nameSort = getUserPreference($_SESSION['auth_user_id'], 'sortClientsByFirstName') ? array('fname', 'lname') : array('lname', 'fname');

if(isset($sort)) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  if($sort_key == 'name') 
    $orderClause = "ORDER BY {$nameSort[0]} $sort_dir, {$nameSort[1]} $sort_dir";
  else $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = "ORDER BY {$nameSort[0]} asc, {$nameSort[1]} asc";

$whereClause = $prospect ? 'WHERE prospect = 1' : ($ids ? 'WHERE 1=1' : 'WHERE active = '.($inactive ? 0 : 1));
if(userRole() == 'p') {
	$activeClients = join(',',getActiveClientIdsForProvider($_SESSION["providerid"]));
	$whereClause .= $activeClients ? " AND clientid IN ($activeClients)" : " AND 1=0";
}
//if($ids) $ids = $_SESSION['clientListIDString'];  // abandon use of passed-in ids since it fails at larger numbers
if($clearfilter) unset($_SESSION['clientListIDString']);
if(!$ids || $ids == 'IGNORE') {
	$ids = $_SESSION['clientListIDString'];
	//if($ids && count(explode(',', $ids)) <= $max_rows) 
	//	unset($_SESSION['clientListIDString']);
}
if($ids && !$clearfilter) $whereClause .= " AND clientid IN ($ids)";
else if(!$ids && array_key_exists('clientListIDString', $_SESSION)) $whereClause .= " AND clientid =-99999"; // cause zero returns
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo $whereClause;
if(!$petnames) {
	$clientIds = fetchCol0("SELECT clientid FROM tblclient $whereClause");
	$numClients = count($clientIds);
}
else {
	if($pattern == '%--%') $clientIds = findClientIdsForPetlessClients();
  else $clientIds = findClientIdsForPetsMatching($pattern, $inactive);
  $numClients = count($clientIds);
}
//if(mattOnlyTEST()) echo "clientListIDString size: ".count(explode(',', "".$_SESSION['clientListIDString']))."SELECT clientid FROM tblclient $whereClause  [$numClients]<hr>";

if($freshsearch) {
	$offset = 0;
	unset($_SESSION['clientFilterJSON']);
}

if($offset) {
	$offset = min($offset, $numClients - 1);
}
else $offset = 0;
$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;

if($numClients > $max_rows) {
	$limitClause = "LIMIT $max_rows OFFSET $offset";
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numClients - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}

$nameFrag ="CONCAT_WS(' ',fname, lname) as name";
if($numClients) {
  $patternClause = 
  	$petnames ? "AND clientid IN (".join(',',$clientIds).")" 
              : ($pattern ? "AND (CONCAT_WS(' ',fname,lname) like '$pattern' OR CONCAT_WS(' ',fname2,lname2) like '$pattern')" : '');

	$addressFrag = ", CONCAT_WS(', ', street1, street2, city) as address";
	
  $clients = fetchAssociationsKeyedBy(
	  "SELECT clientid, userid, email, homephone, cellphone, workphone, prospect,
	  defaultproviderptr as provider,
    $nameFrag
    FROM tblclient $whereClause $patternClause $orderClause $limitClause", 'clientid');
  $pets = getPetNamesForClients(array_keys($clients));
	$providerNames = getProviderShortNames();

}
else $clients = array();

if(TRUE && !$petnames) $numClients = fetchRow0Col0("SELECT COUNT(*) FROM tblclient $whereClause $patternClause");
//if(mattOnlyTEST()) {echo "numClients: $numClients";exit;}

$data = array();
$packageSummaries = getPackageSummaries($clients, 'excludePast');
$rowClass = 'rowstripewhite';

$flagsAreEditable = dbtest('dogonfitness,dogslife') && adequateRights('#ec');

foreach($clients as $clientid => $client) {
  $datum = array();
	$rowClasses[] = $rowClass;
	$rowClass = $rowClass == 'rowstripewhite' ? 'rowstripegrey' : 'rowstripewhite';
  foreach($colKeys as $col) {
    $val = htmlentities(trim($client[$col]));
    /*if($col == 'address') {
			if($val == ", ,") $val = '';
			else $val = truncatedLabel($val, 24);
		} */
		if($col == 'packages') {
			$val =  $packageSummaries[$clientid];
			if(userRole() != 'p') 
				$val = "<a href='client-edit.php?id={$client['clientid']}&tab=services'>".$val."</a>";
		}
    if($col == 'name') {
			$clientLoginId = $noContactInfo ? 'Client has a system login' : "Login ID: {$loginIds[$client['userid']]}";
			$nameTitle = "title='".addslashes($client['userid'] ? $clientLoginId : "No system login")."'";
			if(userRole() == 'p') {
				$editScript = $_SESSION['preferences']['sittersCanRequestClientProfileChanges'] 
						? 'client-prov-edit.php?'
						: 'client-view.php?banner=1&';
			}
			else $editScript = 'client-edit.php?tab=services&';
			$editLink = "<a href='$editScript"."id={$client['clientid']}' $nameTitle> $val</a>";
if(staffOnlyTEST() && in_array(userRole(), array('o', 'd'))) 	$editLink = "<a target=ADDRESS href='client-edit.php?id={$client['clientid']}&tab=address'> [A]</a> $editLink";
			if(FALSE && strpos($_SESSION["rights"], 'qk')) $editLink .= "&nbsp;<a href='client-quick.php?id={$client['clientid']}'><b>Q</b></a>";
			$prospectTag = $client['prospect'] ? '<b title="Prospect"> [P]</b> ' : '';
//if($flagTEST) {
			require_once "client-flag-fns.php";
			if($flagsAreEditable) $editOnClick = "onclick='editFlagsFor({$client['clientid']})'";
			$flags = '';
			if($showflags) {
				$flags = clientFlagPanel($client['clientid'], $officeOnly=false, $noEdit=true, $contentsOnly=true);
				if(!$flags && $editOnClick) $flags = $clickToAddFlagsSpan;
				$flags = "<br><span id='flags_{$client['clientid']}' $editOnClick>$flags</span>";
			}
			if($db == 'leashtimecustomers') {
				$datum['flags'] = $flags;
				$flags = '';
			}
//}
			$val = "<img src='art/snapshot.gif' onClick='viewClient({$client['clientid']})' title='View this client.'>$prospectTag$editLink$flags";
		}
		else if($col == 'flags') continue;  // leashtimecustomers db only
    else if($col == 'provider') $val = $providerNames[$val];
    else if($col == 'email') {
			//$val = $val ? makeEmailLink($val, $val, '', 24) : '';
			$val = clientEmailLink($client['clientid'], $val, $val, 24);
			$phone = primaryPhoneNumber($client);
			if($phone) {
				if($val) $val .= '<br>';
				$val .= $phone;
			}
		}
    //else if($col == 'phone') $val = primaryPhoneNumber($client);
    else if($col == 'pets') {
			$val = $pets[$client['clientid']] ? $pets[$client['clientid']] : '<i>No Pets</i>';
			if(userRole() == 'o') 
      	$val = "<a href='client-edit.php?id={$client['clientid']}&tab=pets'>$val</a>";
		}
    $datum[$col] = $val;
  }
  $data[] = $datum;
}

function findClientIdsForPetlessClients() {
	$allActiveClients = fetchCol0("SELECT clientid FROM tblclient WHERE tblclient.active");
	$sql = "SELECT clientid FROM tblclient JOIN tblpet ON ownerptr = clientid WHERE tblclient.active";
	$ids = fetchCol0($sql);
	return array_diff($allActiveClients, $ids);
//print_r($ids);exit;	
}

function findClientIdsForPetsMatching($pattern, $inactive) {
	$patternClause = $pattern ? "name LIKE '$pattern' AND " : '';
	$sql = "SELECT DISTINCT ownerptr FROM tblpet JOIN tblclient ON clientid = ownerptr WHERE $patternClause tblclient.active=";
	$sql .= $inactive ? 0 : 1;
	return fetchCol0($sql);
}


$searchResults = ($numClients ? $numClients : 'No')." client".($numClients == 1 ? '' : 's')." found.  ";    
if($numClients > $max_rows) $searchResults .= min($numClients - $offset, $max_rows).' clients shown. ';
if($numClients > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('newClient','deletedClient','offset', 'petnames', 'showflags', 'inactive'));
  if($patternParam) 
  	$baseUrl .= (substr($baseUrl, -1) == '?' ? "pattern=".urlencode($patternParam) : "&pattern=".urlencode($patternParam)).'&';
//if(mattOnlyTEST()) echo "$baseUrl";
  $andInactive = "&inactive=$inactive&petnames=$petnames&showflags=$showflags";
  
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).$andInactive.">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).$andInactive.">Show Next ".min($numClients - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numClients - $numClients % $max_rows).$andInactive.">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  
else {
	$nextButton = '';
	$lastPageButton = '';
}


$pageTitle = ($prospect ? 'Prospective' : ($inactive ? 'Inactive' : 'Active'))." Clients";

include "frame.html";
// ***************************************************************************

if(isset($newClient)) {
	$clientName = fetchRow0Col0("SELECT $nameFrag FROM tblclient WHERE clientid = $newClient LIMIT 1");
	echo "<span class='pagenote'>The client $clientName was successfully added.</span><p>";
}
if(isset($savedClient)) {
	$clientName = fetchRow0Col0("SELECT $nameFrag FROM tblclient WHERE clientid = $savedClient LIMIT 1");
	echo "<span class='pagenote'>The client $clientName was successfully saved.</span><p>";
}
if(isset($deletedClient)) {
	$clientName = fetchRow0Col0("SELECT $nameFrag FROM tblclient WHERE clientid = $deletedClient LIMIT 1");
	echo "<span class='pagenote'>The client $clientName was deactivated.</span><p>";
}

?>
<style>
.nameCol {width:120px;
  font-size: 1.05em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  
}
</style>
<form method='post' name='clientsearch'><table>
<?
hiddenElement('inactive', $inactive);
hiddenElement('freshsearch', 0);
hiddenElement('lastFilterResults', '');
hiddenElement('refreshURL', $_SERVER['REQUEST_URI']);
?><tr><td><?
if(userRole() == 'o' || adequateRights('#cl')) {
	echoButton('', "Search", 'freshSearch()'); //'document.clientsearch.freshsearch.value=1;document.clientsearch.submit()');
	echo "</td><td><input id='pattern' name='pattern' value='".stripslashes(safeValue($patternParam))."' autocomplete='off'></td><td style='padding-left: 20px;'>";
	$baseUrl = '"client-list.php?pattern="+escape(document.clientsearch.pattern.value)';
	if($prospect) {
		echoButton('', "Show Active Clients", "document.location.href=$baseUrl");
		echo " ";
		echoButton('', "Show Inactive Clients", "document.location.href=$baseUrl+\"&inactive=1\"");
	}
	else if($inactive) {
		echoButton('', "Show Active Clients", "document.location.href=$baseUrl");
		echo " ";
		echoButton('', "Show Prospects", "document.location.href=$baseUrl+\"&prospect=1\"");
	}
	else if(userRole() != 'p') {
		echoButton('', "Show Inactive Clients", "document.location.href=$baseUrl+\"&inactive=1\"");
		echo " ";
		echoButton('', "Show Clients Without Default Sitters", "document.location.href=\"client-orphan-list.php\"");
		echo " ";
		echoButton('', "Show Prospects", "document.location.href=$baseUrl+\"&prospect=1\"");
	}
	
	$newFilterUI = true; //mattOnlyTEST();
	
	if(!$newFilterUI) {
		echo " ";
		echoButton('', "Filter", "filterClients($baseUrl)");
	}
	if($newFilterUI) {
		echo " <img src='art/magnifier.gif' onclick='filterClients($baseUrl)' title='Advanced search' style='cursor:pointer;'>";
		echo " <input type='hidden' name='clearfilter' id='clearfilter'>";
		if($_SESSION['clientListIDString'])
			echo " <img src='art/magnifier-crossed.gif' onclick='freshSearch(true)' title='Clear the advanced search filter' style='cursor:pointer;'>";
	}
	echo "</td></tr>"; //safeValue($patternParam)
	echo "<tr><td>&nbsp;</td>";
	echo "<td><input name='petnames' id='petnames' type='checkbox' ".($petnames ? 'CHECKED' : '')."><label for='petnames'> Search pet names</label></td>";
	echo "<td>";
	
	if(!$newFilterUI) {
		echo "<input name='showflags' id='showflags' type='checkbox' ".($showflags ? 'CHECKED' : '')."><label for='showflags'> Show client flags</label>";
		if($ids)
			echo " <input name='clearfilter' id='clearfilter' type='checkbox'><label for='clearfilter'> Clear Filter</label>";
	}
	
	if($clientIds && adequateRights('#ex')) {
		//$idstring = join(',', $clientIds); // hmmmm .. why not set ids in the filter script
		//$_SESSION['clientListIDString'] = $idstring;  // use instead of &ids=$idstring
		echo "<img src='art/spacer.gif' width=30 height=1><a href='export-clients.php?fields=full'><img src='art/spreadsheet-32x32.png' height=16 width=16 border=0> Export All Clients</a>";
	}
	if($ids && adequateRights('#ex')) { // export Filtered List
		echo "<img src='art/spacer.gif' width=30 height=1><a href='export-clients.php?fields=full&filteredlist=1'><img src='art/spreadsheet-32x32.png' height=16 width=16 border=0> Export Filtered Clients</a>";
		if(staffOnlyTEST() || dbTEST('themonsterminders')) {
			echo "<img src='art/spacer.gif' width=30 height=1>"
			.fauxLink('Phone Number List',
						"openConsoleWindow(\"emailcomposer\", \"reports-phone-clients.php\",500,500);", 1);
		}

	}
	
	hiddenElement('displayedclientids', join(',', (array)$clientIds));
	echo "</td>";
}
echo "</tr></table></form>\n";

echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "<td>"; 
if(userRole() == 'o' || userRole() == 'd' && (adequateRights('#cl'))) echoButton('', "Add Client", "document.location.href=\"client-edit.php\"");
if(staffOnlyTEST()) {
	echo "<img src='art/spacer.gif' width=15>";
	echoButton('', "Edit Flags", "editFlags()", $class='', $downClass='', $noEcho=false, $title='Apply/Remove flags on the clients listed.');
}
echo "</td></tr></table>";
if($numClients) tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses, $colClasses);

echo "<table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table>";
/*echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "<td>"; 
echo "</td></tr></table>";*/


function clientEmailLink($clientid, $email, $label=null, $length=null, $nullCase=null) {
	if(userRole() == 'o' || $_SESSION['preferences']['trackSitterToClientEmail']) {
		if(!$email) return $nullCase;
		if($length) $label = truncatedLabel($label, $length);
		return fauxLink($label, "openConsoleWindow(\"emailcomposer\", \"comm-composer.php?client=$clientid\",500,500);", 1);
 	}
	else return makeEmailLink($email, $email, '', 24);
}

// ***************************************************************************
?>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
<? optionalAlert(); ?>

function editFlags() {
	var displayedclientids = $('#displayedclientids').val();
	var error = false;
	if(displayedclientids == '') error = 'No clients to work with.';
	else if(displayedclientids.split(',').length >= 1350) error = 'Too many clients.  Try filtering the list first.  Or try this from the report for a flag on the Client Flags page';
	if(error) {
		alert(error);
		return;
	}
	openConsoleWindow("editClientFlags", "client-flags-apply.php?displayedclientids="+displayedclientids,600,700);
}

function freshSearch(clearFilter) {
	if(typeof clearFilter == 'undefined') clearFilter = false;
	if(clearFilter && !confirm('This will clear the Advanced Search Filter.  Proceed?')) return;
	document.clientsearch.freshsearch.value=1;
	document.clientsearch.clearfilter.value=clearFilter;
	document.clientsearch.submit();
}


var flagPanelPicked = null;
function editFlagsFor(id) {
	flagPanelPicked = id;
	$.fn.colorbox({href:"client-flag-picker.php?omitbillingflags=1&clientptr="+id, iframe: "true", width:600, height:550, scrolling: true, opacity: "0.3"});
}

function viewClient(id) {
  var w = window.open("",'viewclient',
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=500,height=700');
  w.document.location.href='client-view.php?id='+id;
  if(w) w.focus();
	
}
var baseUrl;
function filterClients(url) {
	//baseUrl = url;
	baseUrl = 'client-list.php?pattern='+escape(document.getElementById('pattern').value);
	var props = ['petnames', 'showflags', 'clearfilter'];
	var args = new Array();
	for(var i=0; i<3; i++) {
		//alert(props[i]+"="+document.getElementById(props[i]).checked);
		if(document.getElementById(props[i]))
			args[args.length] = props[i]+"="+(document.getElementById(props[i]).checked ? 1 : 0);
	}
	baseUrl += "&"+args.join('&');
	
	openConsoleWindow('filterwindow', 'filter-clients.php',880,680);
}


function update(aspect, data) {
	if(aspect == 'refresh') {
			//document.location.href='<?= $_REQUEST['REQUEST_URI'] ?>';
			document.location.href= $('#refreshURL').val();
			return;
	}
		
	if(aspect == 'flags') {
		if(data.length == 0) data = '<?= $flagsAreEditable ? $clickToAddFlagsSpan : '' ?>';
		document.getElementById('flags_'+flagPanelPicked).innerHTML = data;
		return;
	}
	if(aspect != 'filter') return;
	if(aspect == 'filter') {
		//document.getElementById('filterXML').value = data;
		var root = getDocumentFromXML(data).documentElement;
		var nodes = root.getElementsByTagName('ids');
		var ids = '';
		if(nodes.length == 1 && nodes[0].firstChild) {
			ids = nodes[0].firstChild.nodeValue;
			if(baseUrl.indexOf('ids=') >= 0) 
				baseUrl = baseUrl.substring(0, baseUrl.indexOf('ids='));
			else 
				baseUrl = baseUrl.substring(0, baseUrl.length - 1);
			document.location.href=
				baseUrl
				+(baseUrl.indexOf('?') == -1 ? '?' : '&')
				+'ids='+ids;
		}
		else alert('No clients matching this filter found.');
	}

}
$('#pattern').select();

</script>
<?
include "frame-end.html";
?>

