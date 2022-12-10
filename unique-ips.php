<? // unique-ips.php
require_once "common/init_session.php";
require_once "login-fns.php";

locked('z-');

$f = fopen("output/{$_REQUEST['f']}", 'r');

$dontshow = likelyHackerIPs();
$dontshow[] = '68.225.89.173';
$dontshow[] = '69.250.230.16';

$PATTERN = strtoupper('SELECT');
$ips = array();
while($s = fgets($f)) { //echo "BANG! $s";
	if($PATTERN && !strpos(strtoupper($s), $PATTERN)) continue;
	$ips[$ip = trim(substr($s, 0, strpos($s, ' ')))] += 1;
	if($_REQUEST['all'] || !in_array($ip, $dontshow)) echo urldecode("$s<p>"); //echo "$s<p>";
	//if(in_array($ip, $dontshow)) echo urldecode("$s<p>");
}
echo "<hr>";

foreach($ips as $ip => $matches) {
	$bold = in_array($ip, $dontshow) ? '<span>' : '<span style="font-weight:bold;">';
	echo "$bold$ip</span> ($matches)<br>";
}
	
/*
196.196.217.44
68.225.89.173
199.79.62.15
196.196.217.52
174.244.244.114
121.89.204.243
196.196.217.44
68.225.89.173
199.79.62.15
81.14.208.66
40.77.167.111
98.102.204.206
77.87.199.45
62.234.183.175
217.116.232.202
54.241.225.127
114.67.237.246
68.225.89.173
205.189.35.2
217.116.232.202
54.241.225.127
193.27.229.247
68.225.89.173




*/