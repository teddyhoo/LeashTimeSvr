<? // import-server-file-chooser.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
?>
<style>
.longinput {width:300px;}
</style>
<?
$locked = locked('o-');
$dir = $_REQUEST['dir'];
if($dir) {
	$dir = "/var/data/clientimports/$dir";
	if($dir[strlen($dir)-1] !== "/") $dir .= "/";
	$files = glob("$dir*");
}
?>
<form method='POST' name='getfiles'>
<?
if(strpos($dir, "/var/data/clientimports/") === 0) $dir = substr($dir, strlen("/var/data/clientimports/"));
labeledInput('Directory:', 'dir', $dir, null, 'longinput');
echoButton('', "List", 'document.getfiles.submit()');
?>
</form>
<?

echo "<h2>Files in $dir</h2>";

if(!$files) {echo "No files found."; exit;}
?>
<form method='POST'>
<?
foreach($files as $i => $file) {
	labeledCheckbox(basename($file), "file_$file", 0, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
	echo "<br>";
}
echoButton('', "Done", $_REQUEST['doneaction']);
echo " ";
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
?>
</form>

<script language='javascript'>
function getSels() {
	var arr = new Array();;
	var all = document.getElementsByTagName('input');
	for(var i=0;i<all.length;i++)
		if(all[i].type='checkbox' && all[i].checked && all[i].id.indexOf('file_') != -1)
			arr[arr.length] = all[i].id.substring('file_'.length);
	return arr.join(',');
}
</script>