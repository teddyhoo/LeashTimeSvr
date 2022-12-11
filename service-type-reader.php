<? // service-type-reader.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

/* Accept a block of text copied from the setup excel sheet and initilaize service types. */


// Determine access privs
$locked = locked('o-');
if(!staffOnlyTEST()) {
  echo "LeashTime Staff Use Only.";
  exit;
 }
$rawdata = $_REQUEST['rawdata'];
$sample = "

Pet Sitting 1/4 hr visit	 $23.15x 	75%	80%
Pet Sitting 1/2 hr visit	 $28.00 	75%	80%
Pet Sitting 3/4 hr visit	 $33.00 	75%	80%
Pet Sitting 1 hr visit	 $38.00 	75%	80%
Overnight Pet Sitting	 $50.00 	75%	80%
House Sitting	 $60.00 	75%	80%
Day Care 2 hr visit	 $50.00 	75%	80%
Day Care 3 hr visit	 $60.00 	75%	80%
Day Care 4 hr visit	 $70.00 	75%	80%
Day Care 6 hr visit	 $90.00 	75%	80%
Dog Walking 1/2 hr 	 $25.00 	75%	80%
Dog Walking 3/4 hr 	 $30.00 	75%	80%
Dog Walking 1 hr	 $35.00 	75%	80%
Surcharge Public Holidays	 $10.00 	75%	80%
Pick up or Drop off Key per trip	 $5.00 	75%	80%
House/Garden visit no pets 1/2 hr	 $28.00 	75%	80%
Dogs on Vacation Care   1 dog	 $30.00 	$12	$14
Dogs on Vacation  2 dogs  	 $56.00 	$24	$28
Doggy Day Care  Casual  1 dog	 $24.00 	$12	$12
Doggy Day Care  Casual  2 dogs	 $42.00 	$24	$24
Doggy Day Care  Set Booking  1 dog	 $22.00 	$12	$12
Doggy Day Care Set Booking  2 dogs	 $38.00 	$24	$24";

$n  = 1; $created=0;
if($_REQUEST['clearAll'] && !$_POST) {
	deleteTable('tblservicetype', '1=1', 1);
	$_SESSION['user_notice'] = '<h2>All service types have been deleted.</h2>';
	globalRedirect("service-type-reader.php");
	exit;
}
else if($rawdata) {
	$lines = array_map('trim', explode("\n", $rawdata));
	for($i=0;$i<count($lines);$i++) {
		if(!$lines[$i]) continue;
		$sepr = $_REQUEST['csv'] ? ',' : "\t";
		$row = array_map('trim', explode($sepr, $lines[$i]));
		$label = $row[0];
		if(!$label) {
			$errors[] = "Row $n has no label.";
			continue;
		}
		$charge = 0+preg_replace("/[^0-9\.]+/", '',  $row[1]);
		if(!$charge) $charge = "0.0";
		if($row[2]) $rate = getDollarOrPercent($row[2]);
		if($row[3]) $rate = getDollarOrPercent($row[3]);
		if(!$rate) $rate = array('0', '0');
		$rate = (array)$rate;
		if(!fetchRow0Col0("SELECT label FROM tblservicetype WHERE label = '".mysqli_real_escape_string($label)."' LIMIT 1")) {
			insertTable('tblservicetype', 
				array('label'=>$label, 'defaultrate'=>$rate[0], 'ispercentage'=>$rate[1], 'defaultcharge'=>$charge, 'taxable'=>0,
								'active'=>1, 'hoursexclusive'=>0, 'menuorder'=>$created), 1);
			$created += 1;
		}
	}
	if($errors) echo "Errors:<p><ol><li>".join('<li>', $errors)."</ol>";
	else {
		globalRedirect("service-types.php");
		exit;
	}
}

function getDollarOrPercent($val) {
	$percent = strpos($val, '%');
	return array(0+preg_replace("/[^0-9\.]+/", '',  $val), ($percent ? '1' : '0'));
}

$pageTitle = "Import Service Types";
include "frame.html";
$sample="<table style='position:absolute;left:200px;top:0px;border:solid black 1px;padding: 5px'><tr><td>
<table style='' border=1 bordercolor=1>
<tr><td>Pet Sitting 1/4 hr visit<td>$23.15<td>$15
<tr><td>Pet Sitting 1/2 hr visit<td>$28.00<td>80%
<tr><td>Pet Sitting 3/4 hr visit<td>$33.00<td>75%<td>80%
</table><br>
<td class='tiplooks'>Format: label[tab]charge[tab]rate<br>
If two rates are supplied, second one is used.
<p>-- or --<p>
CSV groups separated by pipes from the Biz Questionnaire:<br>
e.g., Dog Walk 20 Minutes,18,9|Cat Sit,15,8
</td></table>";

// ***************************************************************************
echo "<b>Service Type Descriptions:</b>  $sample<form method='post' name='importer'>";
echo' ';
echoButton('', 'Import', 'document.importer.submit()');
echo' ';
echoButton('', 'Clear All Services', 'clearAll()', 'HotButton', 'HotButtonDown');
echo "<p>
<textarea class='fontSize1_2em' rows=30 cols=80 id='rawdata' name='rawdata' $maxlength
	onpaste='processPaste()' oninput='processPaste()'>{$_REQUEST['rawdata']}</textarea>\n";
hiddenElement('csv', 0);
echo'</form>';
?>
<script language='javascript'>
function clearAll() {
	if(confirm('Clear ALL Service Types?'))
		document.location.href='service-type-reader.php?clearAll=1';
}

function processPaste(x,y) {
	var data = document.getElementById('rawdata').value;
	if(data.indexOf('|')) document.getElementById('csv').value=1;
	document.getElementById('rawdata').value = data.replace(/\|/g,"\n");
}
</script>
<?
// ***************************************************************************
include "frame-end.html";
