<? // cleanse.php
require_once "field-utils.php";
$fname = str_replace("\\", '/', $_REQUEST['file']);


$ext = substr($fname, strrpos($fname, '.'));
if($ext == '.gz') {
	$data = decompress($fname);
	for($i=0;$i<strlen($data);$i++)
		if(ord($data[$i])) echo $data[$i];
	exit;	
}
else $strm = fopen($fname, 'r');

function decompress($zipname) {
	ob_start();
	ob_implicit_flush(0);
	readgzfile($zipname);
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}


$strm = fopen($fname, 'r');





while ($s = fgets($strm)) echo str_replace(chr(0), '', cleanseString($s));