<? // googlepins.php

function googlePinFileNames($root='art') {
	static $fnames;
	if($fnames) return $fnames;
	$colors = explode(',', 'red,yellow,orange,paleblue,green,blue,purple,darkgreen,pink,brown');
	$letters = explode(',', 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');
	$colorOffset = $letterOffset = 0;
	while(count($fnames) < 26 * count($colors)) {
		$fnames[] = "$root/googlemapmarkers/{$colors[$colorOffset]}_Marker{$letters[$letterOffset]}.png";
		$colorOffset += 1;
		$letterOffset += 1;
		if($colorOffset == count($colors)) $colorOffset = 0;
		if($letterOffset == 26) {
			$letterOffset = 0;
			$alphabetCycles += 1;
			$colorOffset = $alphabetCycles;
		}
	}
	return $fnames;
}

foreach(googlePinFileNames($root='art') as $f)
	echo "<img src='$f'>";