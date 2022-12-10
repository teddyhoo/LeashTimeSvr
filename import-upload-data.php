<? //import-upload-data.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');

if(!file_exists($dir = '../../data/clientimports/'.$_SESSION['bizptr']))
	mkdir('../../data/clientimports/'.$_SESSION['bizptr']);
	

//if(file_exists($newFname)) unlink($newFname);
foreach(glob($dir.'/'.$_REQUEST['filekind'].'.*') as $f)
	unlink($f);

$fname = $_FILES['uploadedfile']['name'];
$ext = substr($fname, strrpos($fname, '.'));
if($ext == '.gz') {
	$uncompressedFname = substr($fname, 0, strlen($fname)-3);
	$ext = substr($uncompressedFname, strrpos($uncompressedFname, '.'));
	$gzExt = ".gz";
}

$newFname = $dir.'/'.$_REQUEST['filekind'].$ext.$gzExt;

if(!move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $newFname)) {
	echo "There was an error uploading the file, please try again! [{$_FILES['uploadedfile']['tmp_name']}] => [$newFname]";
}

if($gzExt) $newFname = decompress($newFname);

else if($_REQUEST['filekind'] == 'referrals') {
	$file = "{$_SESSION['bizptr']}/referrals$ext";
	$debug = 0;
	include "import-referrals.php";
}
else if($_REQUEST['filekind'] == 'petcategories') {
		$file = "{$_SESSION['bizptr']}/petcategories$ext";
		$debug = 0;
		include "import-petcategories.php";
}
else if($_REQUEST['filekind'] == 'itemlist') {
		$file = "{$_SESSION['bizptr']}/itemlist$ext";
		$debug = 0;
		include "import-item-list-bluewave.php";
}

function decompress($zipname) {
	ob_start();
	ob_implicit_flush(0);
	readgzfile($zipname);
	$out = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	file_put_contents($fname = substr($zipname, 0, strlen($zipname)-3), $out);
	unlink($zipname);
	return $fname;
}
	