<?php
	function requestWrite($str) {
		$strm = fopen('./logtest/debug.htm','a');
		fwrite($strm,$str);
		fclose($strm);
	}
	function serverlog($str) {
		$date = date('m-d-y h:i:s');
	    $strm = fopen('./logtest/debug.htm','a');
	    fwrite($strm,"--  FROM : " .  $str['from'] . "\n");
	    fwrite($strm, "-- REPLY-TO   :          " . $str['to'] ." \n");
	    fwrite($strm,"--  TO: ".$str['to']. " \n");
	    fwrite($strm,"--  CC:  " .  $str['cc'] . " \n");
	    fwrite($strm,"--  DATE: ". $str['to']. " \n");
	    foreach ($str as $key => $val) {
	    	fwrite($strm, ": ". $key . "  => ". $val."\n");
	    }
	    fwrite($strm, "\n");
	    fclose($strm);
	 }

	 function debugLog($fieldName, $info1, $fromFunction) {

		$dLog = fopen('./logtest/debug.htm','a');
	 
	    if ($info1 == null || $info1 == '') {
	    	$fieldName = $fromFunction . " -> " . $fieldName." --> NO VAL\n";
	    	fwrite($dLog, $fieldName);
	    }
	    else {
	    	if (is_array($info1)) {
	    		fwrite($dLog, "array - ".$fromFunction." - \n");
		    	foreach ($info1 as $info) {
		    		$logEntry = $fieldName. " - " . $info . "\n";
		    		fwrite($dLog, $logEntry);
		    	}
		    }  else {
		    	$logEntry = $fromFunction . " ---> " .$fieldName. " ----> " . $info1 .  "\n";
		    	fwrite($dLog, $logEntry);
		    }
		}
	    fclose($dLog); 	
	 }
?>