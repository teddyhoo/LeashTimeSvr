<? // intake-form-launcher.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "intake-form-fns.php";


if(userRole() == 'p' && $_SESSION['preferences']['providerCanPrintIntakeForms']) locked('p-');
else locked('o-');

extract(extractVars('clientname,numpets', $_REQUEST));
$clientid = $_REQUEST['clientid'];
if($_POST) {
//print_r($_POST);	
	echo getBizLogoImage();
?>
<script language='javascript'>
function printPage() {
	document.getElementById("printthis").style.display="none";
	if(confirm("Do you want all check boxes cleared first?")) {
		var allEls = document.getElementsByTagName('input');
		for(var i=0;i<allEls.length;i++)
			if(allEls[i].type == 'checkbox')
				allEls[i].checked = false;
	}
	window.print();
}
</script>
<?
  //echo " <a id='printthis' href='javascript:document.getElementById(\"printthis\").style.display=\"none\";window.print()'>Print this page</a> ";
  echo " <a id='printthis' href='javascript:printPage()'>Print this page</a> ";
  include "intake-form-client.php";
  require_once "pet-fns.php";
  if($clientid) {
		$pets = getActiveClientPets($clientid);
		$numpets += count($pets);
	}
  if($numpets) 
  	for($petNum=1; $petNum <= $numpets; $petNum++) {
			$pet = $pets[$petNum - 1];
  		include "intake-form-pet.php";
		}
  		
  exit;
}
if($clientid) {
	$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '$clientid' LIMIT 1");
	$numPets = fetchRow0Col0("SELECT COUNT(*) FROM tblpet WHERE ownerptr = '$clientid' AND active = 1");
	$additional = ' additional ';
}
require_once "frame-bannerless.php";
?>
<h2>Print a Client Intake Form</h2>
<form method='POST' name='intakeform'>
<b>Client Name (optional):</b> <input name='clientname' value='<?= $clientname ?>'> <b>Number of<?= $additional ?>Pets:</b>
<select name='numpets'>
<? if($additional) echo "<option>0"; ?>
<option>1
<option>2
<option>3
<option>4
<option>5
<option>6
<option>7
<option>8
<option>9
<option>10
</select>
<p>
<input type='hidden' name='id' value='<?= $clientid ?>'>
<input type='submit' value='Generate Form'>
</form>
