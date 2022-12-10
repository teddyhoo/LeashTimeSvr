<?
// label-print.php
set_time_limit(5 * 60);

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "key-fns.php";

locked('+ka');

if(0 && mattOnlyTEST() && dbTEST('careypet')) include "label-print-fnsCAREYPET.php";
else include "label-print-fns.php";

extract($_REQUEST);

if(isset($keys)) {
	$pairs = array();
	$ids = array();
	foreach(explode(',', $keys) as $label) {
		$pair = explode('-', $label);
		if(count($pair) != 2) {
			echo "Bad print labels: $keys";
			exit;
		}
		$ids[] = $pair[0];
		$pairs[] = array((int)$pair[0], (int)$pair[1]);
	}
	$keysById = getKeysById($ids);
	$badKeys = array();
	foreach($pairs as $pair) {
		if(!isset($keysById[$pair[0]]) ||
		   !$keysById[$pair[0]]["possessor{$pair[1]}"])
			$badKeys[] = print_r($pair, 1);
		else {
			$key = $keysById[$pair[0]];
			$key['copyNumber'] = $pair[1];
			$labels[] = $key;
		}
	}
	if($badKeys) {
		echo "Unknown keys: <br>".join('<br>', $badKeys);
		exit;
	}
	$bin = isset($_REQUEST['bin']) ? $_REQUEST['bin'] : '';
	if($bin && $labels) foreach($labels as $n => $label) $labels[$n]['bin'] = $bin;
	printKeyLabels($labels);
}