<?php
	function requestWrite($str) {
		$strm = fopen('./cctest/cc.htm','a');
		fwrite($strm,$str);
		fclose($strm);
	}
?>