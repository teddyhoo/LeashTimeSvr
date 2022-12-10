<?
//clinic-list.php

// vets list is initialized with current list + "add vet" option
// "add vet" option opens a new window, passing in selectElementId and selected clinic, if any
// on closing, "add vet" window may call updateVetChoices(selectElementId, newVetId)


$pageTitle = "Veterinary Clinics";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
locked('o-');
$breadcrumbs = "<a href='vet-list.php'>Veterinarians</a>";
include "frame.html";

// ***************************************************************************
?>
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<?
include "vet-fns.php";
$max_rows = 25;

$columns = array('clinicname'=>'Clinic Name', 'primevet'=>'Veterinarians', 'city' => 'City', 'officephone'=>'Office Phone', 'email'=>'Email');
$colKeys = array_keys($columns);
$columnSorts = array('clinicname'=>'asc','city'=>null,'email'=>null);
extract($_REQUEST);
if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY clinicname asc';
$numClinics = fetchRow0Col0("SELECT count(*) FROM tblclinic");
if($offset) {
	$offset = min($offset, $numClinics - 1);
}
else $offset = 0;
$nextButton = false;
$prevButton = false;
$firstPageButton = false;
$lastPageButton = false;
if($numClinics > $max_rows) {
	$limitClause = "LIMIT $max_rows OFFSET $offset";
	if($offset > 0) {
		$prevButton = true;
		$firstPageButton = true;
	}
	if($numClinics - $offset > $max_rows) {
		$nextButton = true;
		$lastPageButton = true;
	}
}

$clinics = fetchAssociations("SELECT (select count(*) from tblvet where clinicptr = clinicid) as numVets, clinicid, clinicname, solepractitioner, CONCAT_WS(' ',tblclinic.fname, tblclinic.lname) as primevet, tblclinic.city, tblclinic.officephone, tblclinic.email FROM tblclinic $orderClause $limitClause");
$data = array();
foreach($clinics as $clinic) {
  $datum = array();
  foreach($colKeys as $col) {
    $val = $clinic[$col];
    if($col == 'clinicname') $val = "<a href=# onClick=\"openConsoleWindow('addvet', 'viewClinic.php?id={$clinic['clinicid']}',700,500)\">$val</a>";
    else if($col == 'primevet') $val = $clinic['solepractitioner'] ? $clinic['primevet'] : "{$clinic['numVets']} veterinarians";
    if($col == 'email') $val = makeEmailLink($val, $val);
    $datum[$col] = $val;
  }
  $data[] = $datum;
}

if(isset($newClinic)) {
	$clinicName = fetchRow0Col0("SELECT clinicname FROM tblclinic WHERE clinicid = $newClinic");
	echo "<span class='pagenote'>The clinic $clinicName was successfully added.</span><p>";
}
if(isset($deletedClinic)) {
  $vetsDeleted = $vetsDeleted ? " along with its $vetsDeleted associated vets" : '';
	echo "<span class='pagenote'>The clinic $deletedClinic was deleted$vetsDeleted.</span><p>";
}
if(isset($deletedVet)) {
	echo "<span class='pagenote'>$deletedVet was deleted.</span><p>";
}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
$searchResults = ($numClinics ? $numClinics : 'No')." clinics found.  ";    
$searchResults .= min($numClinics - $offset, $max_rows).' clinics shown. ';
  echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";
  
if($numClinics > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('newClinic','deletedClinic','vetsDeleted','offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numClinics - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numClinics - $numClinics % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
  
  
  echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";
}
echo "<td>"; 
echoButton('', "Add New Clinic", "openConsoleWindow(\"addvet\", \"addNewClinic.php?sel=\",700,520)");
echo "</td></tr></table>";

tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts);


?>
<script language='javascript'>
var sURL = unescape('<?= $notelessURL ?>'); //(window.location.pathname);

function openEditor(id) {
	openConsoleWindow('addvet', 'editClinic.php?id='+id,700,500);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function updateClinicChoices(unusedSelectVar, clinicId) {
	var argument = clinicId ? 'newClinic='+clinicId : '';
	document.location.href='clinic-list.php?'+argument;
}

function updateAfterDeletion(clinicId, vetsDeleted) {
	if(vetsDeleted == -1) document.location.href='clinic-list.php?deletedVet='+clinicId;
	else document.location.href='clinic-list.php?deletedClinic='+clinicId+'&vetsDeleted='+vetsDeleted;
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
