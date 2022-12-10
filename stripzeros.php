<? // stripzeros.php

$in = fopen(($f = $_REQUEST['file']), 'r');
$ext = substr($f, strrpos($f, '.'));
$root = substr($f, 0, strrpos($f, '.'));
$out = fopen('bizfiles/biz_117/'.basename($root.'STRIPPED'.$ext), 'w');

while($s = fgets($in))
	fputs($out, str_replace(chr(0), '', $s));