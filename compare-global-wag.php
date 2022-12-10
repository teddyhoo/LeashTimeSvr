<?
if($_POST) {
	require_once "common/db_fns.php";
	ensureInstallationSettings();
	$db = $_REQUEST['dbname'];
	include 'common/init_db_petbiz.php';
}




?>
<form method='POST' enctype='multipart/form-data'>
Database: <input name=dbname value='<?= $_REQUEST['dbname'] ?>'><p>
<input type='file' name='datafile'><input type=submit>
</form>
<? // compare-global-wag.php
//date,provider name,client name
//								<td><a href="w_str_frcst_admn_s2.cfm?srvcdtl_id=67574&cstmr_nm=Garrison, Kelly&dt=7/29/2009">Lara</a></td>
//										<a href="w_str_frcst_admn_stime.cfm?srvcdtl_id=57658&cstmr_nm=Masingill, Adelina"><img src="images/clock-45.gif" width="10" height="10" border="0" align="absmiddle"></a>



if(!$_POST) exit;
$target_path = 'test/globalwag';
$formFieldName = 'datafile';

if(is_string($result = goMan())) {
	echo $result;
	exit;
}


$file = $target_path;

$lines = file($file);

$continued = false;
$tips = array();
$ptip = false;
$needTipDollars = false;
$n=0;
$rows =  array();
foreach($lines as $line) {
	if(strpos($line, "PTIP")) $ptip = true;
	else if($needTipDollars && ($tipStart = strpos($line, "</span>$"))) {
//echo "<p>NEEDTIP<p>";		
		$needTipDollars = false;
		$tipStart += strlen("</span>$");
		$tips[count($tips)-1]['tip'] = substr($line, $tipStart, strpos($line, "<", $tipStart+1) - $tipStart);
		$new = array();// don't count tips as appointments
		for($i=0;$i<count($rows)-1;$i++) $new[] = $rows[$i];
		$rows = $new;
		//unset($rows[count($rows)-1]);  <== DOES NOT WORK!
//echo "<font color=red>BANG!</font>: $n<br>";$n++;		
	}
	else if($continued) {
		$line = trim($line);
		if($line) {
			$row['provider'] = $line;
			$rows[] = $row;
//echo "<p>CONTINUED<p>";		
			$continued = false;
		}
	}
	else if(strpos($line, "<td><a href=\"w_str_frcst_admn_s2.cfm") && strpos($line, "dt=")) {
		$start = strpos($line, "cstmr_nm=")+strlen("cstmr_nm=");
		$end = strpos($line, "&", $start);
		$row = array('customer'=>substr($line, $start, $end-$start));
		
		$start = strpos($line, "dt=")+strlen("dt=");
		$end = strpos($line, "\"", $start);
		$row['date'] = substr($line, $start, $end-$start);
		
		$start = strpos($line, ">", $end)+1;
		$end = strpos($line, "<", $start);
		$row['provider'] = substr($line, $start, $end-$start);
		if($ptip) {
			$tips[] = $row;
			$ptip = false;
			$needTipDollars = true;
		}
		if(!$row['provider']) {
			$continued = true;
		}
		else {
			$continued = false;
//echo "<p>DONE<p>";		
			$rows[] = $row;
		}
	}
}

$date = date("Y-m-d", strtotime($rows[0]['date']));
foreach($rows as $row) $sigs[sigFor($row)] += 1;
ksort($sigs);
//foreach($sigs as $sig=>$num) echo "$sig: $num<br>";
//echo "count: ".count($sigs).'<br>';
echo "<P>Number of BW appointments for $date: ".array_sum($sigs).'<p>';

set_include_path('/var/www/petbizdev:');

$appts = fetchAssociations(
	"SELECT *, CONCAT_WS(', ',cl.lname, cl.fname) as customer, pr.nickname as provider
		FROM tblappointment
		LEFT JOIN tblclient cl ON clientid = clientptr
		LEFT JOIN tblprovider pr ON providerid = providerptr
		WHERE canceled IS NULL AND date = '$date'");
foreach($appts as $index => $row) {
	//$appts[$index]['date'] = date('
	$LTsigs[sigFor($row)] += 1;
}
	
echo "<P>Number of LT appointments for $date: ".count($appts).'<p>';
ksort($LTsigs);
//foreach($LTsigs as $sig=>$num) echo "<font color=red'>$sig: $num<br>";

echo "<P>DISCREPANCIES:  ".'<br>(Key: <font color=green> provider [customer] BW: {bluewave count} LT {leashtime count}</font>)<p>';
$out = array();
foreach($LTsigs as $sig=>$num) {
	if($sigs[$sig] != $num) {
		$bwNum = $sigs[$sig] ? $sigs[$sig] : 0;
		$out[] = "$sig - BW: $bwNum LT: $num<br>";
	}
}

foreach($sigs as $sig=>$num) {
	if($LTsigs[$sig] != $num) {
		$ltNum = $LTsigs[$sig] ? $LTsigs[$sig] : 0;
		$out[] = "$sig - BW: $num LT: $ltNum<br>";
	}
}
sort($out);
foreach($out as $line) echo "$line\n";

echo "<p>\n";

echo "<hr><b>TIPS:</b><p>";
foreach($tips as $row) {
	$client = fetchRow0Col0("SELECT clientid FROM tblclient WHERE CONCAT_WS(', ',lname, fname) = '{$row['customer']}'");
	$provider = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE nickname = '{$row['provider']}'");
	$tip = $row['tip'] ? $row['tip'] : '0.0';
	if($client && $provider) 
		$gratuity = fetchFirstAssoc(
				"SELECT * FROM tblgratuity WHERE clientptr = $client AND providerptr = $provider and amount = $tip");
	//echo "SELECT * FROM tblgratuity WHERE clientptr = $client AND providerptr = $provider and amount = $tip<br>";
	echo "BW Customer: [{$row['customer']}] => Provider: [{$row['provider']}] {$row['tip']} ";
	echo $gratuity ? "<font color=green>Found in Leashtime</font>" : "<font color=red>Not found in Leashtime</font>"
				."<br>";
}

echo "<p><b>Leashtime gratuities on or after $date</b><p>";
$gratuities = fetchAssociations(
		"SELECT tblgratuity.*, CONCAT_WS(', ',c.lname, c.fname) as client,
				ifnull(nickname, CONCAT_WS(' ',p.fname, p.lname)) as provider
   			FROM tblgratuity				
				LEFT JOIN tblclient c ON clientptr = clientid
				LEFT JOIN tblprovider p ON providerptr = providerid
				WHERE issuedate >= '$date'");
foreach($gratuities as $row)
	echo "LT Customer: [{$row['client']}] => Provider: [{$row['provider']}] {$row['amount']} Date: {$row['issuedate']}<br>";

function sigFor($row) {
	$prov = $row['provider'] ? $row['provider'] : '##UNKNOWN##';
	return strtoupper($prov).' ['.strtoupper($row['customer']).'] ';//.$row['date'];
}

// =====================================================================================

function ensureDirectory($dir) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, 0765);
}

function invalidUpload($formFieldName, $file) {
  global $maxPixels, $maxDim;
  $basefile = basename($file);
  $extension = strtoupper(substr($basefile, strpos($basefile, '.')+1));
  $oldError = error_reporting(E_ALL - E_WARNING);
  $failure = null;
  if($failure = $_FILES[$formFieldName]['error']) {
		if($failure == 1) $failure = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
		else if($failure == 2) $failure = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
		else if($failure == 3) $failure = "The uploaded file was only partially uploaded.";
		else if($failure == 4) $failure = "No file was uploaded.";
		else if($failure == 6) $failure = "Missing a temporary folder.";
		else if($failure == 7) $failure = "Failed to write file to disk.";
		else if($failure == 8) $failure = "File upload stopped by extension.";
	}
  else if($extension == 'JPG') {
		$size = getimagesize($_FILES[$formFieldName]['tmp_name']);
		$pixels = $size[0]*$size[1];
		if($pixels > $maxPixels) {
			$pixels = number_format($pixels);
		  $failure = "Photo dimensions are too big: ({$size[0]} X {$size[1]}) = $pixels pixels (Max: $maxPixels pixels, = approx. $maxDim X $maxDim)";
		}
    else {
      $jpg = imagecreatefromjpeg($_FILES[$formFieldName]['tmp_name']);
      if(!$jpg) $failure = "it does not contain a valid JPEG image.";
		}
  }
  else if($extension == 'ZIP') {
		require_once "zip-fns.php";
		$zipFile = $_FILES['$formFieldName']['tmp_name'];
    if(is_int($zip = zip_open($zipFile))) $failure = "File is not a valid ZIP archive.";
    $dir = getTargetPath();
    $existingPhotos = glob("$dir/*.jpg");
    foreach($existingPhotos as $index => $fname) $existingPhotos[$index] = basename($fname);
    $errors = invalidArchiveEntries($zip, $existingPhotos);
    if($errors)
			$failure = join("<br>\n", $errors);
		else {
			$newPhotos = array();
      $zip = zip_open($zipFile);
			$errors = unpackArchivePhotos($zip, $dir, $newPhotos, $existingPhotos, $maxPixels);
			foreach($newPhotos as $photo) registerPhoto($photo);
			echo join("<br>\n", $errors);
		}
  }
  error_reporting($oldError);
  return $failure;
}

function goMan() {
	global $target_path, $formFieldName;
	$formFieldName = 'datafile';
	$dot = strpos($_FILES[$formFieldName]['name'], '.htm');
//print_r($_FILES);echo "[{$_FILES[$formFieldName]['name']}]<p>";	
	if($dot === FALSE) return "Uploaded file MUST be HTML.";
	$originalName = $_FILES[$formFieldName]['name'];
	$extension = strtoupper(substr($_FILES[$formFieldName]['name'], $dot+1));
	if(!in_array($extension, array('HTM','HTML')))
		return "Photo Not uploaded!  Uploaded file MUST be HTML. [$originalName] does not qualify.";


	if($reason = invalidUpload($formFieldName, $target_path)) return "The file $originalName could not be used because $reason";
	if(file_exists($target_path)) unlink($target_path);
	ensureDirectory(dirname($target_path));
	//echo substr(sprintf('%o', fileperms(dirname($target_path))), -4);
	if(!move_uploaded_file($_FILES[$formFieldName]['tmp_name'], $target_path)) {
		return "There was an error uploading the file, please try again!";
	}
}

