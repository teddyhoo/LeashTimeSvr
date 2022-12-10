<? // biz-customization-fns.php

function fetchGenericBusinessSubstitutions() {
	global $genericTermSubstitutions;
  $file = 'generic-terminology-substitutions.txt';
  return parse_ini_file($file, true);
}

function ensureCustomSubstitutionsAreSet(&$prefs) {
  // Call this from fetchPreferences
  global $genericTermSubstitutions;
	if(!$genericTermSubstitutions) fetchGenericBusinessSubstitutions();
	$genericTermSubstitutions = fetchGenericBusinessSubstitutions();
	foreach($genericTermSubstitutions as $key=>$val) {
		if(!array_key_exists($key, $prefs)) {
			$prefs[$key] = $val;
		}
	}
}

function bizCustomize($str) {
	global $scriptPrefs;
	$prefs = $_SESSION['preferences'] ? $_SESSION['preferences'] :  $scriptPrefs;
	foreach($prefs as $key => $val) {
		if(strpos($key, '@@') == 0)
			if(strpos($str, $key) !== FALSE)
				$str = str_replace($key, $val, $str);
	}
	return $str;
}


