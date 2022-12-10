<? // tempBuildAuth.php
buildArray();


function buildArray() {
	$str = fopen('authmsgs.txt', 'r');
	$state = 0;
	while($line = fgets($str)) {
		$line = trim($line);
		if($state > 2 && is_numeric($line)) {
			echo "\t{$next['code']} => array('".addslashes($next['label'])."', ".$next['status']."),";
			if($next['comment']) echo "  // {$next['comment']},<br>";
			else echo "<br>";
			$state = 0;
		}
		$state++;
		if($state == 1) $next = array('status'=>$line);
		else if($state == 2) $next['code']=$line;
		else if($state == 3) $next['label']=$line;
		else if($state == 4) $next['comment']=$line;
	}
}

