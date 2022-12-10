<? //client-list-mobile.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "field-utils.php";
require_once "pet-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

// Determine access privs
$locked = locked('+o-,+p-,#cl');

$max_rows = 25;

$columns = array('name'=>'Name', 'email'=>'Email / Phone'/*, 'packages'=>'Services', 'pets'=>'Pets'*/);
if($_SESSION['preferences']['suppresscontactinfo'] && userRole() == 'p') {
	unset($columns['email']);
}

if(userRole() == 'p') unset($columns['services']);
$colKeys = array_keys($columns);
$columnSorts = array('name'=>'asc','email'=>null);
$colClasses = array('name'=>'nameCol');
extract($_REQUEST);

$pattern = mysql_escape_string($pattern);
$patternParam = !isset($pattern) ? '' : $pattern;
$pattern = !isset($pattern) ? '' : 
           (strpos($pattern, '*') !== FALSE ? str_replace('*', '%', $pattern) : "%$pattern%");
$petnames = isset($petnames) ? true : false;

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require "common/init_db_common.php";
$loginIds = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE bizptr = {$_SESSION['bizptr']} AND rights LIKE 'c-%'");
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

$inactive = isset($inactive) ? $inactive : false;
$prospect = isset($prospect) ? $prospect : false;

if(isset($sort)) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  if($sort_key == 'name') 
    $orderClause = "ORDER BY lname $sort_dir, fname $sort_dir";
  else $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY lname asc, fname asc';

$whereClause = $prospect ? 'WHERE prospect = 1' : 'WHERE active = '.($inactive ? 0 : 1);
if(userRole() == 'p') {
	$activeClients = join(',',getActiveClientIdsForProvider($_SESSION["providerid"]));
	$whereClause .= $activeClients ? " AND clientid IN ($activeClients)" : " AND 1=0";
}
if(!$petnames) $numClients = fetchRow0Col0("SELECT count(*) FROM tblclient $whereClause");
else {
	if($pattern == '%--%') $clientIds = findClientIdsForPetlessClients();
  else $clientIds = findClientIdsForPetsMatching($pattern, $inactive);
  $numClients = count($clientIds);
}

if($freshsearch) $offset = 0;

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
  $patternClause = $petnames ? "AND clientid IN (".join(',',$clientIds).")" 
                            : ($pattern ? "AND CONCAT_WS(' ',fname,lname) like '$pattern'" : '');

	$addressFrag = ", CONCAT_WS(', ', street1, street2, city) as address";
	
  $clients = fetchAssociationsKeyedBy(
	  "SELECT clientid, userid, email, homephone, cellphone, workphone, prospect,
	  defaultproviderptr as provider,
    $nameFrag
    FROM tblclient $whereClause $patternClause $orderClause $limitClause", 'clientid');
  $allPets = getPetNamesForClients(array_keys($clients));
	$providerNames = getProviderShortNames();

}
else $clients = array();

$data = array();
$packageSummaries = getPackageSummaries($clients, 'excludePast');
$rowClass = 'rowstripewhite';
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
				$val = fauxLink($label, "document.location.href=\"client-edit.php?id={$client['clientid']}&tab=services\"", 1);
				//"<a href=''>".$val."</a>";
		}
    if($col == 'name') {
			$editScript = userRole() == 'p' ? 'visit-sheet-mobile.php?noappointments=1&' : '';
			$editLink = "<a href='$editScript"."id={$client['clientid']}' $nameTitle> $val</a>";
			if(strpos($_SESSION["rights"], 'qk')) $editLink .= "&nbsp;<a href='client-quick.php?id={$client['clientid']}'><b>Q</b></a>";
			$prospectTag = $client['prospect'] ? '<b title="Prospect"> [P]</b> ' : '';
			$pets = $allPets[$client['clientid']] ? $allPets[$client['clientid']] : '<i>No Pets</i>';
      $val = "$prospectTag$editLink ($pets)";
		}
    else if($col == 'provider') $val = $providerNames[$val];
    else if($col == 'email') {
			//$val = $val ? makeEmailLink($val, $val, '', 24) : '';
			$val = clientEmailLink($client['clientid'], $val, $val, 24);
			$phonefield = primaryPhoneField($client);
			if($phonefield) {
				if($val) $val .= '<br>';
				$phone = $client[$phonefield];
				$sms = textMessageEnabled($phone);
				$phone = strippedPhoneNumber($phone);
				$name = safeValue("{$client['fname']} {$client['lname']}");
				$val .= fauxLink($phone, "openCallBox(\"$name\", \"$phone\", \"$sms\")", 1);
			}
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
  $baseUrl = thisURLMinusParams(null, array('newClient','deletedClient','offset'));
  $andInactive = "&inactive=$inactive";
  
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
  


$pageIsPrivate = 1;
// ***************************************************************************
include "mobile-frame.php";

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
<form method=post name=clientsearch><table>
<?
hiddenElement('inactive', $inactive);
hiddenElement('freshsearch', 0);
?><tr><td><?
if(userRole() == 'o') {/*
	echoButton('', "Search", 'document.clientsearch.freshsearch.value=1;document.clientsearch.submit()');
	echo "</td><td><input name='pattern' value='".stripslashes(safeValue($patternParam))."' autocomplete='off'></td><td style='padding-left: 20px;'>";
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
	else {
		echoButton('', "Show Inactive Clients", "document.location.href=$baseUrl+\"&inactive=1\"");
		echo " ";
		echoButton('', "Show Clients Without Default Sitters", "document.location.href=\"client-orphan-list.php\"");
		echo " ";
		echoButton('', "Show Prospects", "document.location.href=$baseUrl+\"&prospect=1\"");
	}
		
	echo "</td></tr>"; //safeValue($patternParam)
	echo "<tr><td>&nbsp;</td><td><input name='petnames' id='petnames' type='checkbox' ".($petnames ? 'CHECKED' : '')."'><label for='petnames'> Search pet names</label>";
*/}
echo "</td></tr></table></form>\n";

echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "<td>"; 
if(userRole() == 'o' || userRole() == 'd' && (adequateRights('#cl'))) 
	echoButton('', "Add Client", "document.location.href=\"client-edit.php\"");
echo "</td></tr></table>";

//tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses, $colClasses);
echo "<table>";
foreach($data as $row)
	echo "<tr><td>{$row['name']}</td><td>{$row['email']}</td></tr>
<tr><td class='visitlistsepr' colspan=3>&nbsp;</tr>
";
echo "</table>";

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


// ***************************************************************************
?>
<script language='javascript' src='common.js'></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="jquery.busy.js"></script> 	
<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script language='javascript'>
<? optionalAlert(); ?>
function viewClient(id) {
  var w = window.open("",'viewclient',
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=500,height=700');
  w.document.location.href='client-view.php?id='+id;
  if(w) w.focus();
	
}

var callBox = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=false, $class=false); ?>";
var callBoxSMS = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=true, $class=false); ?>";

function openCallBox(telname, tel, sms) {
	var box = sms ? callBoxSMS : callBox;
	box = box.replace('#NAME#', telname);
	box = box.replace(/#TEL#/g, tel);
	$.fn.colorbox({	html: box,	width:"280", height:"200", iframe:false, scrolling: "auto", opacity: "0.3"});
}


</script>
