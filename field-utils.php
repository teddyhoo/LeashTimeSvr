<?
// field-utils.php

// phone field format: <*><T>number
// * = primary
// T = text message target


function primaryPhoneNumber($data, $candidateFields=array( 'homephone', 'cellphone', 'workphone', 'cellphone2')) {
	$field = primaryPhoneField($data, $candidateFields);
	$field = isset($data[$field]) ? $data[$field] : '';
	return strippedPhoneNumber($field);
}

function primaryTextPhoneNumber($data, $candidateFields=array( 'homephone', 'cellphone', 'workphone', 'cellphone2')) {
	$field = primaryPhoneField($data, $candidateFields);
	$field = isset($data[$field]) ? $data[$field] : '';
	if(textMessageEnabled($field))
		return strippedPhoneNumber($field);
}

function strippedPhoneNumber($number) {
	if(!$number) return $number;
	$stripped = strpos($number, '*') === 0 ? substr($number, 1) : $number;
//echo "$number => $stripped >>".(strpos($stripped, 'T') === 0);exit;	
	return strpos($stripped, 'T') === 0 ? substr($stripped, 1) : $stripped;
}
	
function usablePhoneNumber($number) {
	$number = strippedPhoneNumber($number);
	if(!$number) return $number;
	// strip everything before the first numeral and everything past the last numeral
	$started = false;
	for($i=0; $i<strlen($number); $i++) {
		if(is_numeric($number[$i])) {
			if(!$started) {
				$firstNumPos = $i;
				$started = true;
			}
			$lastNumPos = $i;
		}
	}
	return !$started ? $number : substr($number, $firstNumPos, $lastNumPos-$firstNumPos+1);
/*	// if there is white space, split on that and use the first token
	$number = trim($number);
	$sepr = strpos($number, ' ');
	if($sepr === FALSE) $sepr = strpos($number, "\t");
	if($sepr) $number = substr($number, 0, $sepr);
	return $number;
	*/
}

function canonicalUSPhoneNumber($value) {
	// iOS app needs a phone number in a particular format, with dashes
	// if number has 7, 10, or 11 digits (11 where there is a "1" prefix)
	// canonicalize the phone number
	// else return the supplied value, trimmed
	$value = trim("$value");
	$phone = usablePhoneNumber($value);
	if($phone) {
		$digits = '';
		for($i=0; $i<strlen($phone); $i++)
			if(is_numeric($phone[$i])) 
				$digits .= $phone[$i];
		if(strlen($digits) == 11 && $digits[0] == '1')
			$digits = substr($digits, 1);
		// if not a real usable US number, return the original value
		if(strlen($digits) != 7 && strlen($digits) != 10)
			return $value;
		$phone = substr($digits, 0, 3).'-';
		if(strlen($digits) == 7)
			$phone = $phone.substr($digits, 3);
		else if(strlen($digits) == 10)
			$phone = $phone.substr($digits, 3, 3).'-'.substr($digits, 6);
		return $phone;
	}
	return $value;
}

function isAPhoneNumber($value) {
	$pattern = "/^(\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/";
	return preg_match($pattern, $value);
}
	
function textMessageTarget($data, $candidateFields=array( 'homephone', 'cellphone', 'workphone', 'cellphone2')) {
	$field = textMessageTargetField($data, $candidateFields);
	return $field ? strippedPhoneNumber($field) : null;
}

function textMessageEnabled($num) {
	return $num && (strpos($num, 'T') === 0 || strpos($num, '*T') === 0);
}

function markedPrimary($num) {
	return $num && $num[0] == '*';
}

function textMessageTargetField($data, $candidateFields=array( 'homephone', 'cellphone', 'workphone', 'cellphone2')) {
  foreach($candidateFields as $key) {
		$num = isset($data[$key]) ? $data[$key] : null;
    if(textMessageEnabled($num))
      return $key;
	}
  return null;
}

function primaryPhoneField($data, $candidateFields=array( 'homephone', 'cellphone', 'workphone', 'cellphone2')) {
  foreach($candidateFields as $key)
    if(isset($data[$key]) && $data[$key] && ($data[$key][0] == '*'))
      return $key;
  // if none marked primary return first non-null
  foreach($candidateFields as $key)
    if(isset($data[$key]) && $data[$key])
      return $key;
  return $candidateFields[0];
}

function dbDate($dateOrNull) {
	if(!$dateOrNull) return null;
	return date('Y-m-d', strtotime($dateOrNull));
}

function phoneNumberAsPrimary($num) {
	if(!($num = trim($num))) return;
	return $num[0] == '*' ? $num : "*$num";
}

function phoneNumberAsTextEnabled($num) {
	if(!($num = trim($num))) return;
	if($num[0] == '*') {
		$primaryFlag =  '*';
		$num = strlen($num) == 1 ? '' : substr($num, 1);
	}
	if($num)
		$num = strlen($num) == 1 ? '' : ($num[0] == 'T' ? $num : "T$num");
	return "$primaryFlag$num";
}

function analyzePhoneNumber($num) {
	if(!$num) return array();
	$primary = $num[0] == '*';
	return array(
		'primary'=>$primary,
		'text'=>($primary && (strlen($num) > 1) ? ($num[1] == 'T') : (
						 !$primary ? ($num[0] == 'T') : false)),
		'number'=>strippedPhoneNumber($num));
}

function cleanseString($str, $dropMap=false) {
	if(!is_string($str)) return $str;
	$map = array(
		'½'=>'1/2',
		'¼'=>'1/4',
		 // '‘'=>"'", // open single quote
		 // '’'=>"'", // close single quote
		 //'“'=>"'", // open double quote
		 //'”'=>"'", // close double quote
	);
	foreach($map as $k => $v)
		if(strpos($str, $k))
			$str = str_replace($k, ($dropMap ? '' : "$v"), $str);
	for($i=0; $i < strlen($str); $i++)
		if(ord($str[$i]) < 128) $out .= $str[$i];
	return $out;
}

function dateFrame($date) {  // today, last, next, this, full
	$date = strtotime(date('Y-m-d', strtotime($date)));
	$today = strtotime(date('Y-m-d'));
	if($date == $today) return 'today';
	$days = 24 * 60 * 60;
	if($date - $today > 0 && $date - $today < (7 * $days)) return 'next';
	else if($today - $date > 0 && $today - $date < (7 * $days)) return 'last';
	else if(date('Y', $date) == date('Y', $today)) return 'this';
	else return 'full';
}


function isEmailValid($email) {
	$emailpat = "/^[a-zA-Z0-9._%+-`'`&]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/";  // checks for ' character
	return preg_match($emailpat, $email)
					&& strpos($email, '^') === FALSE  // included because the ampersand inexplicably allows circumflexes
					&& strpos($email, ',') === FALSE  // included because the ampersand inexplicably allows commas
					&& strpos($email, ':') === FALSE  // included because the ampersand inexplicably allows commas
					&& count(explode('@', $email)) == 2; // deny multple @'s
}

function numeralsOnly($str) {
	$str = "$str";
	for($i=0; $i < strlen($str); $i++)
		if(ord($str[$i]) >= 48 && ord($str[$i]) <= 57)
			$out .= $str[$i];
	return $out;
}

/*
Paul's Simple Diff Algorithm v 0.1
(C) Paul Butler 2007 <http://www.paulbutler.org/>
May be used and distributed under the zlib/libpng license.

This code is intended for learning purposes; it was written with short
code taking priority over performance. It could be used in a practical
application, but there are a few ways it could be optimized.

Given two arrays, the function diff will return an array of the changes.
I won't describe the format of the array, but it will be obvious
if you use print_r() on the result of a diff on some test data.

htmlDiff is a wrapper for the diff command, it takes two strings and
returns the differences in HTML. The tags used are <ins> and <del>,
which can easily be styled with CSS.
*/

function recursiveDiff($old, $new){
$matrix = array();
$maxlen = 0;
foreach($old as $oindex => $ovalue){
$nkeys = array_keys($new, $ovalue);
foreach($nkeys as $nindex){
$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
if($matrix[$oindex][$nindex] > $maxlen){
$maxlen = $matrix[$oindex][$nindex];
$omax = $oindex + 1 - $maxlen;
$nmax = $nindex + 1 - $maxlen;
}
}
}
if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
return array_merge(
recursiveDiff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
array_slice($new, $nmax, $maxlen),
recursiveDiff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}

function htmlDiff($old, $new){
	if($old == $new) return $old;
	$ret = '';
	$diff = recursiveDiff(preg_split("/[\s]+/", $old), preg_split("/[\s]+/", $new));
	foreach($diff as $k){
		if(is_array($k))
			$ret .= (!empty($k['d']) ? "<del>".implode(' ',$k['d'])."</del> " : '')
							.(!empty($k['i']) ? "<ins>".implode(' ',$k['i'])."</ins> " : '');
		else {
			$ret .= $k . ' ';
		}
	}
	return $ret;
}

function ago($when) {
	$ago = time() - strtotime($when);
	if($ago < 60) $ago = "in the last minute";
	else if($ago < 60*60) $ago = round($ago/60)." minute";
	else if($ago < 60*60*24) $ago = round($ago/3600)." hour";
	else if($ago < 60*60*24*2) $ago = "yesterday";
	else $ago = round($ago/3600/24)." day";
	if(is_numeric($ago[0])) {
		if(substr($ago, 0, 2) != "1 ") $ago .= "s";
		$ago .= " ago";
	}
	return $ago;
}

function hoursAndMinutes($seconds) {
	$minutes = (int)((0+$seconds) / 60);
	$result['minutes'] = $minutes % 60;
	$hours = (int)($minutes / 60);
	if($hours) $result['hours'] = $hours;
	return $result;
}

function withHTMLEolns($txt) {
	return str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r\n", "\n", $txt)));
}

function withRawEolns($txt) {
	return str_replace("<br>", "\n", str_replace("<p>", "\n\n", $txt));
}

function dateIntervalFromLabel($intervalLabel) {
	$firstDayThisMonthInt = strtotime(date("Y-m-01"));
	if($intervalLabel == 'Last Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("-1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("-1 month", $firstDayThisMonthInt))));
		$month = date("M", strtotime("-1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'Next Month') {
		$start = shortDate(strtotime(date("Y-m-01", strtotime("+1 month", $firstDayThisMonthInt))));
		$end = shortDate(strtotime(date("Y-m-t", strtotime("+1 month", $firstDayThisMonthInt))));
		$month = date("M", strtotime("+1 month", $firstDayThisMonthInt));
	}
	else if($intervalLabel == 'This Month') {
		$start = shortDate(strtotime(date("Y-m-01")));
		$end = shortDate(strtotime(date("Y-m-t")));
		$month = date("M");
	}
	else if($intervalLabel == 'Last Week') {
		$end = shortDate(strtotime("last Sunday"));
		$start = shortDate(strtotime("last Monday", strtotime($end)));
	}
	else if($intervalLabel == 'Next Week') {
		$start = shortDate(strtotime("next Monday"));
		$end = shortDate(strtotime("next Sunday", strtotime($start)));
	}
	else if($intervalLabel == 'This Week') {
		if(date('l') == "Monday") $start = shortDate();
		else $start = shortDate(strtotime("last Monday"));
		$end = shortDate(strtotime("next Sunday", strtotime($start)));
	}
	else if(strpos($intervalLabel, 'Month') === 0) {
		if($offsetstart = strpos($intervalLabel, '+')) 
			$offset = substr($intervalLabel, $offsetstart+1, strlen($intervalLabel)-$offsetstart+1);
		else if($offsetstart = strpos($intervalLabel, '-')) {
			$offset = substr($intervalLabel, $offsetstart, strlen($intervalLabel)-$offsetstart);
			if($offset) {
				$start = shortDate(strtotime(date("Y-m-01", strtotime("$offset month", $firstDayThisMonthInt))));
				$end = shortDate(strtotime(date("Y-m-t", strtotime("$offset month", $firstDayThisMonthInt))));
				$month = date("M", strtotime("$offset month", $firstDayThisMonthInt));
			}
		}
	}
	return array($start, $end, $month);
}

?>