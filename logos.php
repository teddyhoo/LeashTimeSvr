<? // logos.php
foreach(glob('bizfiles/biz_*') as $d)
	foreach(glob("$d/logo.*") as $f)
		echo "<img src='$f'>";
