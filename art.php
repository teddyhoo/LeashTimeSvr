<? // art.php

$files = glob('art/*.*');
foreach($files as $f) {
	if(!is_dir($f)) {
		echo "<img src=$f title=".basename($f)."> ";
	}
}