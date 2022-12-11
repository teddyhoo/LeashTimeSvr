<? // screen-notice-fns.php
// functions to allow managers to specify messages to be shown in light boxes
// which can be formatted in a limited way without risking HTML problems

function composeTimelyScreenNotice($notice) {
	$today = strtotime(date('Y-m-d'));
	$first = strtotime($notice['first']);
	$last = strtotime($notice['last']);
	if($first && $today < $first) return;
	if($last && $today > $last) return;
	return composeMessage($notice['message'], $notice['title'], $notice['props']);
}

function composeMessage($body, $heading=null, $props=null) {
	// managers are allowed to specify plain text messages but choose
	// some constrained formatting
	
	$heading = $heading ? $heading : '';
	require_once "gui-fns.php";
	$swaps = "<p>|<P>||<P>|\n\n||<br>|<BR>||<BR>|\n||'|&apos;";
	foreach(explodePairsLine($swaps) as $pat => $repl) {
		$heading = str_replace($pat, $repl, $heading);
		$body = str_replace($pat, $repl, $body);
	}
		
	$heading = screenNoticeWhiteList(strip_tags(screenNoticeWhiteList($heading)), 'restore');
	$body = screenNoticeWhiteList(strip_tags(screenNoticeWhiteList($body)), 'restore');
}	
	foreach((array)$props as $prop) {
		$prop = strtoupper($prop);
		if($prop == 'HEADER1') $headerTag = 'h1';
		else if($prop == 'HEADER2') $headerTag = 'h2';
		else if($prop == 'HEADER3') $headerTag = 'h3';
		else if($prop == 'CENTERHEADER') $centerHeader = "class='center'";
		else if($prop == 'BODY1') $bodyClass[] = 'fontSize1_1em';
		else if($prop == 'BODY2') $bodyClass[] = 'fontSize1_2em';
		else if($prop == 'BODY3') $bodyClass[] = 'fontSize1_3em';
		else if($prop == 'BODY4') $bodyClass[] = 'fontSize1_4em';
		else if($prop == 'BODY5') $bodyClass[] = 'fontSize1_5em';
		else if($prop == 'BODY6') $bodyClass[] = 'fontSize1_6em';
		else if($prop == 'BODY7') $bodyClass[] = 'fontSize1_7em';
		else if($prop == 'BODY8') $bodyClass[] = 'fontSize1_8em';
		else if($prop == 'BODY9') $bodyClass[] = 'fontSize1_9em';
		else if($prop == 'BODY10') $bodyClass[] = 'fontSize2_0em';
		else if($prop == 'CENTERBODY') $bodyClass[] = 'center';
	}
	$bodyClass = $bodyClass ? "class = '".join(" ", $bodyClass)."'" : '';
	$headerTag = $headerTag ? $headerTag : 'h2';
	
	$heading = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $heading));
	$body = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $body));
	// ...
	
	if($heading) $message = "<$headerTag $centerHeader>$heading</$headerTag>";
	$message .= "<div $bodyClass>$body</div>";
	$message = str_replace("\r", "", $message);
		
	return $message;
}

function screenNoticeWhiteList($str, $restore=false) {
	// allow some HTML to escape tag stripping
	require_once "gui-fns.php";
	$pairs = explodePairsLine("<br>|#BR#||<hr>|#HR#");
	if($restore) $pairs = array_flip($pairs);
	foreach($pairs as $k=>$v) {
		$str = str_replace($k, $v, $str);
		if(!$restore)
			$str = str_replace(strtoupper($k), $v, $str);
	}
	return $str;
}

function replaceEOLsWithSpaces($str) {
	$str = str_replace("<br>", " ", str_replace("<p>", " ", 	str_replace("\r", "", $str)));
	$str = str_replace("<BR>", " ", str_replace("<P>", " ", 	str_replace("\r", "", $str)));
	return $str;
}
