<? // report-preference-usage.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
set_time_limit(5 * 60);

$locked = locked('o-');

if(!staffOnlyTEST()) {echo "Staff Only"; exit; }
?>
<h2>Preference Usage</h2>
Preference: <input id='pref' width=30> <input type='button' value='Show' onclick='show()'>
<div id='results'></div>
<script src='ajax_fns.js'></script>
<script>
function show() {
	var pref = document.getElementById('pref').value;
	if(!pref) {alert('Pick a pref first.');return;}
	ajaxGet("optional-business-features.php?checkFeature="+pref, 'results');
}

</script>

<? //optional-business-features.php?checkFeature= ?>