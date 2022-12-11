<? //provider-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "provider-fns.php";
require_once "field-utils.php";

// Determine access privs
if(userRole() == 'o') $locked = locked('o-,#pl');
else $locked = locked('d-,#pl');

$max_rows = 25;
$canEditProviders = adequateRights('#es');

$columns = array('impersonate'=>'','name'=>'Name', 'address'=>'Address', 'phone' => 'Phone', 'email'=>'Email', 'numJobs'=>'# Visits', 'numClients'=>'# Clients');
if(!$canEditProviders) unset($columns['impersonate']);

$colKeys = array_keys($columns);
$columnSorts = array('name'=>'asc','email'=>null, 'numClients'=>null);
extract($_REQUEST);
$inactive = isset($inactive) ? $inactive : false;
if(isset($sort)) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  if($sort_key == 'name') 
    $orderClause = "ORDER BY lname $sort_dir, fname $sort_dir";
  else $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY lname asc, fname asc';

$whereClause = 'WHERE active = '.($inactive ? 0 : 1);

$numProvs = fetchRow0Col0("SELECT count(*) FROM tblprovider $whereClause");
if($offset) {
	$offset = min($offset, $numProvs - 1);
}
else $offset = 0;
$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;
if($numProvs > $max_rows) {
	$limitClause = "LIMIT $max_rows OFFSET $offset";
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numProvs - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}

$apptQuery = "select count(*) from tblappointment where providerptr = providerid and date = CURRENT_DATE()";
$clientQuery = "select count(*) from tblclient where defaultproviderptr = providerid and active=1";
$nameFrag ="CONCAT_WS(' ',fname, lname) as name";
$sql = "SELECT ($apptQuery) as numJobs, ($clientQuery) as numClients, providerid, email, homephone, cellphone, workphone,
  $nameFrag, nickname, CONCAT_WS(', ', street1, street2, city) as address
  FROM tblprovider $whereClause $orderClause $limitClause";
if(mattOnlyTEST()) screenLog( $sql);  
$t0 = microtime(true);
$provs = fetchAssociations($sql, 1);

$error = mysqli_error();
//if(mattOnlyTEST() && $error) bagText($sql, $referringtable='provider-list');  
if(mattOnlyTEST()) screenLog("query time: ".(microtime(true)-$t0).' secs.');  
if($error) screenLog("ERROR: $error");  
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog(count($provs2,1)); }
$providersWithLogins = fetchCol0("SELECT providerid FROM tblprovider WHERE userid IS NOT NULL");
$data = array();
foreach($provs as $prov) {
  $datum = array();
	$rowClass = 'rowstripewhite';
	$providerMoniker = $prov['nickname'] ? $prov['nickname'] : $prov['name'];
  foreach($colKeys as $col) {
		$rowClasses[] = $rowClass;
		$rowClass = $rowClass == 'rowstripewhite' ? 'rowstripegrey' : 'rowstripewhite';
    $val = htmlentities($prov[$col]);
    if($col == 'name') {
			$nickname = $prov['nickname'] ? " ({$prov['nickname']})" : '';
			$val = "$val$nickname";
			if($canEditProviders) $val = "<a href='provider-edit.php?id={$prov['providerid']}'>$val</a>";
		}
    else if($col == 'address') {
			if($val == ", ,") $val = '';
			else $val = truncatedLabel($val, 24);
		}
    else if($col == 'impersonate') 
    	$val = !in_array($prov['providerid'], $providersWithLogins) ? '&nbsp;' : makeImpersonationLink($prov['providerid'], $providerMoniker);
    else if($col == 'email') $val = makeEmailLink($val, $val, '', 24);
    else if($col == 'phone') $val = primaryPhoneNumber($prov);
    else if($col == 'numJobs') 
      $val = "<a href='prov-schedule-cal.php?provider={$prov['providerid']}&starting=".date("Y-m-d")."&ending=".date("Y-m-d")."' title='View provider&#39;s visits for today'>$val</a>";
    else if($col == 'numClients') 
      $val = "<a href='#' onClick='openConsoleWindow(\"providerclients\", \"prov-clients-view.php?provider={$prov['providerid']}\",400,400)' title='Open a window listing sitter&#39;s clients'>$val</a>";
    $datum[$col] = $val;
  }
  $data[] = $datum;
}

//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
$searchResults = ($numProvs ? $numProvs : 'No')." sitter".($numProvs == 1 ? '' : 's')." found.  ";    
if($numProvs > $max_rows) $searchResults .= min($numProvs - $offset, $max_rows).' sitters shown. ';
if($numProvs > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('newProvider','deletedProvider','offset', 'inactive'));
  $andInactive = "&inactive=$inactive";
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).$andInactive.">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0$andInactive>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).$andInactive.">Show Next ".min($numProvs - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numProvs - $numProvs % $max_rows).$andInactive.">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  
  
function makeImpersonationLink($provider, $provname) {
	return fauxLink("<img src='art/impersonate.gif'>", "impersonate(\"$provider\", \"$provname\")", true, "Login as this sitter.");
}	

$pageTitle = ($inactive ? 'Inactive' : 'Active')." Sitters";

$breadcrumbs = fauxLink("Sitter Map", 'document.location.href="providers-map.php"', 1, "Map of sitter homes.");
include "frame.html";
// ***************************************************************************

if(isset($newProvider)) {
	$provName = fetchRow0Col0("SELECT $nameFrag FROM tblprovider WHERE providerid = $newProvider LIMIT 1");
	echo "<span class='pagenote'>The sitter $provName was successfully added.</span><p>";
}
if(isset($savedProvider) && $savedProvider) {
	$provName = fetchRow0Col0("SELECT $nameFrag FROM tblprovider WHERE providerid = $savedProvider LIMIT 1");
	echo "<span class='pagenote'>The sitter $provName was successfully saved.</span>";
	if(isset($unassignedAppointments)) {
		$apptList = appointmentsUnassignedFrom($savedProvider);  // date and appointment id
		// for now, we will provide a link to the reassignment page for the first appointment date.
		// later we'll find a way to zero in on the appointments unassigned from this user
//print_r($apptList);	exit;	
		$starting = "&date={$apptList[0]['date']}";
		echo "<br><span class='pagenote'>$unassignedAppointments of $provName"."'s appointments ".($unassignedAppointments == 1 ? 'was' : 'were').
					" just unassigned because of the sitter's time off. Please go to ".
					"<a href='job-reassignment.php?fromprov=-1$starting'>Job Reassignment</a> to assign them to other sitters.</span>";
	}
	echo "<p>";
}
if(isset($deletedProvider)) {
	$provName = fetchRow0Col0("SELECT $nameFrag FROM tblprovider WHERE providerid = $deletedProvider LIMIT 1");
	echo "<span class='pagenote'>The sitter $provName was deactivated.</span><p>";
}

$provTOLists = array();

foreach(getUpcomingTimeOff(-1, 14) as $row) $provTOLists[$row['providerptr']][] = $row;
$activeProviderPtrs = fetchCol0("SELECT providerid FROM tblprovider WHERE active = 1");
if($provTOLists) {
	$shortNames = getProviderShortNames();
	echo "<b>Sitter Time Off in the next 14 days:</b><br>";
	$upcomingTimeOff = array();
	foreach($provTOLists as $provptr => $timeoff) {
		if(!in_array($provptr, $activeProviderPtrs)) continue; //  && !mattOnlyTEST()
		$shortName = $shortNames[$provptr];
//if(!in_array($provptr, $activeProviderPtrs)) $shortName = "***$shortName";
		$upcomingTimeOff = array();
		foreach($timeoff as $row)
			$upcomingTimeOff[] = representTimeOffRange($row);
		$ptoLink = $canEditProviders ? "<a href='provider-edit.php?id=$provptr'>$shortName</a>" : $shortNames[$provptr];
		echo "$ptoLink - ".join(', ', array_unique($upcomingTimeOff)).'<br>';
	}
	echo "<p>";
}

if($inactive) echoButton('', "Show Active Sitters", "document.location.href=\"provider-list.php\"");
else echoButton('', "Show Inactive Sitters", "document.location.href=\"provider-list.php?inactive=1\"");
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "<td>"; 
echoButton('', "Add Sitter", "document.location.href=\"provider-edit.php\"");
echo " "; 
if(staffOnlyTEST()) echoButton('', 'Import Sitters', 'document.location.href="providers-reader.php"');
echo "</td></tr></table>";

tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);

echo "<img src='art/spacer.gif' height=100>";



// ***************************************************************************
?>
<script language='javascript'>
function impersonate(prov, provname) {
	if(confirm("Login as "+provname+"?"))
		document.location.href='impersonate.php?provider='+prov;
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}
</script>
<?
include "frame-end.html";
?>

