<? //client-orphan-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "field-utils.php";
require_once "pet-fns.php";
require_once "provider-fns.php";

// Determine access privs
$locked = locked('#ec');


$max_rows = 25;

$columns = array('name'=>'Name', 'provider'=>'Sitter', /*'services' => 'Services',*/ 'email'=>'Email / Phone', 'address'=>'Address', 'pets'=>'Pets');

$colKeys = array_keys($columns);
$columnSorts = array('name'=>'asc','email'=>null);
$colClasses = array('name'=>'nameCol');
extract($_REQUEST);

if(isset($sort)) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  if($sort_key == 'name') 
    $orderClause = "ORDER BY lname $sort_dir, fname $sort_dir";
  else $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY lname asc, fname asc';

$whereClause = $clients ? "WHERE clientid IN ($clients)"
	: 'WHERE active = 1 and (defaultproviderptr = 0 or defaultproviderptr is null)';

$numClients = fetchRow0Col0("SELECT count(*) FROM tblclient $whereClause");

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

  $clients = fetchAssociationsKeyedBy($sql = "SELECT clientid, email, homephone, cellphone, workphone,
    $nameFrag, CONCAT_WS(', ', street1, street2, city) as address
    FROM tblclient $whereClause $orderClause $limitClause", 'clientid');
  
  $pets = getPetNamesForClients(array_keys($clients));
	$providerNames = getProviderShortNames();

}
else $clients = array();

//if(mattOnlyTEST()) {echo "[[[".print_r($sql, 1)."]]]";exit;}
//if(mattOnlyTEST()) echo "[[[".print_r($clients[924], 1)."]]]";
//if(mattOnlyTEST()) echo "[[[".print_r($numClients, 1)."]]]";

$data = array();
$rowClass = 'rowstripewhite';
foreach($clients as $client) {
  $datum = array();
	$rowClasses[] = $rowClass;
	$rowClass = $rowClass == 'rowstripewhite' ? 'rowstripegrey' : 'rowstripewhite';
  foreach($colKeys as $col) {
    $val = htmlentities(trim($client[$col]));
    if($col == 'address') {
			if($val == ", ,") $val = '';
			else $val = truncatedLabel($val, 24);
		}
    if($col == 'name') {
			$editScript = userRole() == 'o' ? 'client-edit.php' : 'client-prov-edit.php';
			$editLink = "<a href='$editScript?id={$client['clientid']}'> $val</a>";
			if(strpos($_SESSION["rights"], 'qk')) $editLink .= "&nbsp;<a href='client-quick.php?id={$client['clientid']}'><b>Q</b></a>";
      $val = "<img src='art/snapshot.gif' onClick='viewClient({$client['clientid']})' title='View this client.'>$editLink";
		}
    else if($col == 'services')
			$val = buttonDiv("<a href='client-edit.php?id={$client['clientid']}&tab=services'>Visits</a> ")/*." ".
			       buttonDiv("<a href='client-request-list.php?id={$client['clientid']}'>Requests</a>")*/;

    else if($col == 'provider') $val = providerSelectElement($client['clientid']);
    else if($col == 'email') {
			$val = $val ? makeEmailLink($val, $val, '', 24) : '';
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

function providerSelectElement($clientid) {
	ob_start();
	ob_implicit_flush(0);
	availableProviderSelectElement($clientid, $date=null, "providerFor_$clientid", '--No Sitter Assigned--', $choice=null, "setProviderForClient(this, $clientid)");
	$element = ob_get_contents();
	ob_end_clean();
	return $element;
	//$activeProviders = array_merge(array('--No Sitter Assigned--' => ''), getActiveProviderSelections());
	//return selectElement('', "providerFor_$clientid", $value=null, $activeProviders, "setProviderForClient(this, $clientid)", null, null, 'noEcho');
}

function buttonDiv($label) {
	return "\n<div style='display:inline; cursor:pointer;background:lightblue;border: solid darkgrey 1px; font: small-caps 0.8em arial;'>$label</div>";
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
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numClients - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numClients - $numClients % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  
  


$pageTitle = $provider ? "Former Clients of ".$providerNames[$provider] : "Clients without Default Sitter";
$breadcrumbs = "<a href='client-list.php'>Clients</a>";
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
<form method=post name=clientsearch><table>
<?
hiddenElement('inactive', $inactive);
hiddenElement('freshsearch', 0);
?><tr><td><?
//if(userRole() == 'o') { // NOW GATED BY #EC
	echoButton('', "Done", "document.location.href=\"index.php\"");
	echo "</td></tr>";
//}
echo "</td></tr></table></form>\n";

echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "</tr></table>";
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {

tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses, $colClasses);




// ***************************************************************************
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
<? optionalAlert(); ?>

function setProviderForClient(sel, $clientid) {
	var prov = sel.options[sel.selectedIndex].value;
	prov = prov ? prov : '0';
	ajaxGetAndCallWith('default-provider-set-ajax.php?client='+$clientid+'&provider='+prov, assignmentSuccess, '')
}

function assignmentSuccess(arg, text) {
	if(text != 'ok') alert(text);
}

function viewClient(id) {
  var w = window.open("",'viewclient',
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=500,height=700');
  w.document.location.href='client-view.php?id='+id;
  if(w) w.focus();
	
}
</script>
<?
include "frame-end.html";
?>

