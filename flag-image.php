<? //flag-image.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-');

$name = $_GET['name'];
$size = $_GET['size'];

if($_GET['list']) {
	echo "<table>";
	foreach(glob('art/flag-*') as $f) 
		echo "<tr><td><img src='$f'><td>".basename($f).'<tr>';
	echo "</table>";
	exit;
}

function flagExists($name, $dir) {
	$exts = explode('|', '.jpg|.png|.gif');
	foreach($exts as $ext) {
		//echo "try [$dir$name$ext]<br>";
		if(file_exists("$dir$name$ext")) return "$dir$name$ext";
	}
}

$dir = 
		$size == 64 ? 'art/flags64/' : (
		$size == 32 ? 'art/flags32/' : 
		'art/');
//echo "$dir<p>";
if(!($file = flagExists($name, $dir)) && !($file = flagExists($name, 'art/')))
	echo "ERROR: $name not found";
else {
	$ctypes = array('gif'=>'gif', 'jpg'=>'jpeg', 'png'=>'png');
	$extension = substr($file, strrpos($file, '.')+1);
//if(mattOnlyTEST()) {echo "$file: Content-Type: image/{$ctypes[$extension]}";} else
	header("Content-Type: image/{$ctypes[$extension]}");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	//header("Content-Length: ".strlen($out));
	
	readfile($file);

}