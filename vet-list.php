<?
//vet-list.php

// vets list is initialized with current list + "add vet" option
// "add vet" option opens a new window, passing in selectElementId and selected clinic, if any
// on closing, "add vet" window may call updateVetChoices(selectElementId, newVetId)


$pageTitle = "Veterinarians";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
locked('o-');
$breadcrumbs = "<a href='clinic-list.php'>Veterinary Clinics</a>";
include "frame.html";

// ***************************************************************************
?>
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<?
include "vet-fns.php";
$max_rows = 25;

$columns = array('fullname'=>'Name', 'clinicname'=>'Clinic', 'city' => 'City', 'officephone'=>'Office Phone', 'email'=>'Email');
$colKeys = array_keys($columns);
$columnSorts = array('fullname'=>'asc', 'clinicname'=>'asc','city'=>null,'email'=>null);
extract($_REQUEST);
if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY tblvet.lname asc, tblvet.fname asc';
$numVets = fetchRow0Col0("SELECT count(*) FROM tblvet");
if($offset) {
	$offset = min($offset, $numVets - 1);
}
else $offset = 0;
$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;
if($numVets > $max_rows) {
	$limitClause = "LIMIT $max_rows OFFSET $offset";
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numVets - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}

$vets = fetchAssociations("SELECT vetid, clinicid, clinicname, CONCAT_WS(' ',tblvet.fname, tblvet.lname) as fullname, 
tblvet.city, tblvet.officephone, tblvet.email FROM tblvet LEFT JOIN tblclinic ON clinicid = clinicptr $orderClause $limitClause");
$data = array();
if($vets) foreach($vets as $vet) {
  $datum = array();
  foreach($colKeys as $col) {
    $val = $vet[$col];
    if($col == 'fullname') $val = "<a href=# onClick=\"openConsoleWindow('addvet', 'viewVet.php?id={$vet['vetid']}',700,500)\">$val</a>";
    else if($col == 'clinicname') $val = "<a href=# onClick=\"openConsoleWindow('addvet', 'viewClinic.php?id={$vet['clinicid']}',700,500)\">$val</a>";
    if($col == 'email') $val = makeEmailLink($val, $val);
    $datum[$col] = $val;
  }
  $data[] = $datum;
}

if(isset($newVet)) {
	$vetName = fetchRow0Col0("SELECT CONCAT_WS(' ',fname, lname) as fullname FROM tblvet WHERE vetid = $newVet");
	echo "<span class='pagenote'>$vetName was successfully added.</span><p>";
}
if(isset($deletedVet)) {
	echo "<span class='pagenote'>$deletedVet was deleted.</span><p>";
}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
$searchResults = ($numVets ? $numVets : 'No')." veterinarians found.  ";    
$searchResults .= min($numVets - $offset, $max_rows).' veterinarians shown. ';
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

if($numVets > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('newVet','deletedVet','offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numVets - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numVets - $numVets % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
  
  
	echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>";
}
echo "<td>"; 
echoButton('', "Add New Vet", "openConsoleWindow(\"addvet\", \"addNewVet.php?sel=\",700,500)");
  echo "</td></tr></table>";
echo "</tr></table></td>";

tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts);


?>
<script language='javascript'>
var sURL = unescape('<?= $notelessURL ?>'); //(window.location.pathname);

function openEditor(id) {
	openConsoleWindow('addvet', 'editVet.php?id='+id,700,500);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function updateVetChoices(unusedSelectVar, vetId) {
	document.location.href='vet-list.php?newVet='+vetId;
}

function updateAfterDeletion(vetName, dummy) {
	document.location.href='vet-list.php?deletedVet='+vetName;
}



function refresh()
{
    //  This version of the refresh function will cause a new
    //  entry in the visitor's history.  It is provided for
    //  those browsers that only support JavaScript 1.0.
    //
    window.location.href = sURL;
}
//-->
</script>

<script language="JavaScript1.1">
<!--
function refresh()
{
    //  This version does NOT cause an entry in the browser's
    //  page view history.  Most browsers will always retrieve
    //  the document from the web-server whether it is already
    //  in the browsers page-cache or not.
    //  
    window.location.replace( sURL );
}
//-->
</script>

<script language="JavaScript1.2">
<!--
function refresh()
{
    //  This version of the refresh function will be invoked
    //  for browsers that support JavaScript version 1.2
    //
    
    //  The argument to the location.reload function determines
    //  if the browser should retrieve the document from the
    //  web-server.  In our example all we need to do is cause
    //  the JavaScript block in the document body to be
    //  re-evaluated.  If we needed to pull the document from
    //  the web-server again (such as where the document contents
    //  change dynamically) we would pass the argument as 'true'.
    //  
    window.location.reload( false );
}
//-->
</script>
<p><img src='art/spacer.gif' height=300>
<?
// ***************************************************************************

include "frame-end.html";
?>
