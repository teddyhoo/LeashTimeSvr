<? // mobile-device-fns.php

function isMobileUserAgent() {
	// See: http://en.wikipedia.org/wiki/List_of_user_agents_for_mobile_phones
	$agent = $_SERVER["HTTP_USER_AGENT"];
	$tokens = 'Alcatel,iPhone,iPod,SIE-,BlackBerry,Android,IEMobile,Obigo,Windows CE,LG/,LG-,CLDC,Nokia,SymbianOS,PalmSource'
						.',Pre/,Palm webOS,SEC-SGH,SAMSUNG-SGH';
	$maybe = 'iPad';
	$tokens = explode(',',$tokens);
	foreach($tokens as $token) if(strpos($agent, $token) !== FALSE) return true;
}