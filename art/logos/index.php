<? // index.php ?>
Click to view.  Right-click on displayed image and "Save Image..." as needed.<p>
<?
$exts = explode(',', 'gif,jpg,png,svg,pdf');

foreach(glob('*') as $f) {
	$lf = strtolower($f);
	$ext = strpos($lf, '.');
	$found = 0;
	foreach($exts as $x) if(strpos($lf, ".$x")) $found = 1;
	if(!$found) continue;
	$bn = basename($f);
	$sz = filesize($f)/1024;
	$sz = number_format($sz);
	echo "<span onclick='show(\"$bn\")' onclick>$bn ($sz K)</span><br>";
}
?>
<div id=showcase></div>
<script language='javascript'>
function show(im) {
	document.getElementById('showcase').innerHTML = 
	 "<div style='border:solid black 1px;width:300px;padding:5px;'>"+im+"</div><br><img src='"+im+"'>";
}
</script>