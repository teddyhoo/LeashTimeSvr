<? // providers-reader.php
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

$n  = 1; $created=0;
if($_REQUEST['clearAll'] && !$_POST) {
	//deleteTable('tblservicetype', '1=1', 1);
	//$_SESSION['user_notice'] = '<h2>All service types have been deleted.</h2>';
	//globalRedirect("service-type-reader.php");
	//exit;
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
		if($row[2]) $rate = getDollarOrPercent($row[2]);
		//if($row[3]) $rate = getDollarOrPercent($row[3]);
		$rate = (array)$rate;
		$parts = array_map('trim', explode(' ', $label));
		if(count($parts)) {
			$lname = array_pop($parts);
			if(count($parts)) $fname = join(' ', $parts);
			else {$fname = $lname; $lname = null;}
		}

		if(!fetchRow0Col0("SELECT providerid FROM tblprovider WHERE fname = '"
				.mysql_real_escape_string($fname)
				."' AND lname = '"
				.mysql_real_escape_string($lname)."' LIMIT 1")) {
			$sitter = array('lname'=>($lname ? $lname : 'UNKNOWN'), 'fname'=>($fname ? $fname : 'UNKNOWN'), 'active'=>1);
			if($_POST['nicknames']) $sitter['nickname'] = $fname;
			insertTable('tblprovider', $sitter, 1);
			$created += 1;
		}
	}
	if($errors) echo "Errors:<p><ol><li>".join('<li>', $errors)."</ol>";
	else {
		globalRedirect("provider-list.php");
		exit;
	}
}

function getDollarOrPercent($val) {
	$percent = strpos($val, '%');
	return array(0+preg_replace("/[^0-9\.]+/", '',  $val), ($percent ? '1' : '0'));
}

//$pageTitle = "Import Service Types";
include "frame.html";
$sample="<table><tr><td class='h2'>Import Sitters<td><table style='border:solid black 1px;padding: 5px'><tr><td>
<table style='' border=1 bordercolor=1>
<tr><td>Jane Doe
<tr><td>Martin O'Malley
<tr><td>G Khan
</table><br>
<td class='tiplooks'>Format: one name per line.
<p>-- or --<p>
Names separated by pipes
e.g., Jane Doe|Martin O'Malley|G Khan
</td></table></table>";

$sitters = fetchAssociations("SELECT CONCAT_WS(' ', fname, lname) as name, active FROM tblprovider");
foreach($sitters as $i => $sitter)
	$sitterNames[] = !$sitter['active'] ? "<i>{$sitter['name']}</i>" : $sitter['name'];
echo "$sample<p><b>Existing sitters (<i>inactive in italics</i>):</b> ".join(', ', $sitterNames).'<p>';

// ***************************************************************************
echo "<b>Sitters:</b>  <form method='post' name='importer'>";
echo' ';
echoButton('', 'Import', 'document.importer.submit()');
labeledCheckbox(' Set up Nicknames', 'nicknames', $nicknames);
echo "<p>
<textarea class='fontSize1_2em' rows=30 cols=80 id='rawdata' name='rawdata' $maxlength
	onpaste='processPaste()' oninput='processPaste()'>{$_REQUEST['rawdata']}</textarea>\n";
hiddenElement('csv', 0);
echo'</form>';
?>
<script language='javascript'>
function clearAll() {
	if(confirm('Clear ALL Service Types?'))
		document.location.href='Xservice-type-reader.php?clearAll=1';
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
