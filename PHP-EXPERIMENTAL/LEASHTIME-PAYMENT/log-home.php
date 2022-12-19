<?php
	function requestWrite($str) {
		$strm = fopen('./logtest/home.txt','a');
		fwrite($strm,$str);
		fclose($strm);
	}
?>