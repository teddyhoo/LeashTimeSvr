// email-analyzer.php

$strm = fopen($file, 'r');
$lastpos = ftell($strm);
$lastWasBlank = true;
while(!feof($strm)) {
	$pos = ftell($strm);
	$line = trim(fgets($strm));
	if(strpos($line, 'From - ') === 0) {
		if($lastWasBlank) { // start a new message
			if($msg) { // but first finish the last message
				$msg['length'] = $pos - $msg['startposition'];
				saveMessage($msg);
			}
			$msg = array('fromline' => trim($line), 'startposition'=>$pos);
		}
	}
	else if(strpos($line, 'Date: ') === 0) $msg['date'] = date('Y-m-d H:i:s', strtotime(fieldVal($line, 'Date: ')));
	else if(strpos($line, 'Subject: ') === 0) $msg['subject'] = fieldVal($line, 'Subject: '));
	else if(strpos($line, 'From: ') === 0) $msg['from'] = fieldVal($line, 'From: '));
	else if(strpos($line, 'To: ') === 0) $msg['to'] = fieldVal($line, 'To: '));
	else if(strpos($line, 'CC: ') === 0) $msg['cc'] = fieldVal($line, 'CC: '));
	$lastWasBlank = strlen($line) == 0;
	$msg['body'] .= $line;
}
			
function fieldVal($line, $field) {
	return trim(substr($line, strlen($field)));
}

function saveMessage($msg) {
	$msg['checksum'] = md5($msg['body']);
	unset($msg['body']);
	insertTable('message', $msg);
}