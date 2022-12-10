<?
// search-fns.php -- for getSearchMatches.php
function shortCutEditorWakeWord() { return 'shortcuts'; }

function getShortCuts() {
	$shortcuts = $_SESSION['searchshortcuts'];
	if(!$shortcuts) { // never set
		require_once "preference-fns.php";
		if($_SESSION['staffuser']) {
			$userid = $_SESSION['staffuser'];
			list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
			require "common/init_db_common.php";
			$shortcuts = getUserPreference($userid, 'searchshortcuts', $decrypted=false, $skipDefault=false);
			reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		}
		else {
			$shortcuts = getUserPreference($_SESSION['auth_user_id'], 'searchshortcuts', $decrypted=false, $skipDefault=false);
		}
		
		$_SESSION['searchshortcuts'] = $shortcuts ? json_decode($shortcuts, 'assoc') : 'none';
		//if(!$shortcuts) $_SESSION['searchshortcuts'] = 'none';
		//else $_SESSION['searchshortcuts'] = json_decode($shortcuts, 'assoc');
		$shortcuts = $_SESSION['searchshortcuts'];
	}
	return $shortcuts == 'none' ? array() : $shortcuts;
}

function editShortCuts() {
	// for use in a lightbox or popup
	require_once "frame-bannerless.php";
	require_once "gui-fns.php";
	$shortcuts = getShortCuts();
	echoButton('', 'Save', 'saveShortCuts()');
	echo " - ";
	echoButton('', 'Close', 'closeEditor()');
	$rows = array();
//print_r($shortcuts);
	foreach($shortcuts as $key => $url) {
		$n += 1;
		$row['key'] = 
			labeledInput('', "key_$n", $key, $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=7, $noEcho=true);
		$row['url'] = 
			labeledInput('', "url_$n", $url, $labelClass=null, $inputClass='VeryLongInput', $onBlur=null, $maxlength=null, $noEcho=true);
			$rows[] = $row;
	}
	$n += 1;
	$row['key'] = 
		labeledInput('', "key_$n", '', $labelClass=null, $inputClass=null, $onBlur=null, $maxlength=7, $noEcho=true);
	$row['url'] = 
		labeledInput('', "url_$n", '', $labelClass=null, $inputClass='VeryLongInput', $onBlur=null, $maxlength=null, $noEcho=true);
		$rows[] = $row;
	
	$columns = explodePairsLine("kill| ||key|Pattern||url|URL");
	echo "<form name='shortcutform' id='shortcutform' method='POST' action='getSearchMatches.php'>";
	hiddenElement('shortcuteditor', 1);
	tableFrom($columns, $rows, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
	echo "</form>";
	
	$wakeword = shortCutEditorWakeWord();
	echo <<<JS
<script language='javascript'>
function saveShortCuts() {
	var els = document.getElementsByTagName('input');
	for(var i=0; i < els.length; i+=1) {
		if(els[i].id.indexOf('key_') == 0 && els[i].value == '$wakeword') {
			alert('cannot define ['+els[i].value+']');
			return;
		}
	}
	document.shortcutform.submit();
}

function closeEditor(key) {
	window.close();
}

</script>
JS;
}

function postSearchShortCuts($request) {
	$shortcuts = array();
	foreach($request as $k => $key) {
		if(strpos($k, 'key_') !== 0) continue;
		$n = substr($k, strlen('key_'));
		if($key && $request["url_$n"]) 
			$shortcuts[$key] = $request["url_$n"];
	}
	$_SESSION['searchshortcuts'] = $shortcuts ? $shortcuts : 'none';
	$shortcuts = $shortcuts ? json_encode($shortcuts) : 'none';
	require_once "preference-fns.php";
	if($_SESSION['staffuser']) {
		$userid = $_SESSION['staffuser'];
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		setUserPreference($userid, 'searchshortcuts', $shortcuts);
//print_r(getUserPreference($userid, 'searchshortcuts'));	
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	}
	else
		setUserPreference($_SESSION['auth_user_id'], 'searchshortcuts', $shortcuts);
}

