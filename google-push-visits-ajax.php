<? // google-push-visits-ajax.php
// Edit email prefs for logged in provider
// params: 
// prov (opt)
// unassigned (opt)
// start
// end
// 
set_time_limit(5 * 60);

set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "google-cal-fns.php";

// Determine access privs -- look into this
if(userRole() == 'o') $locked = locked('o-');
if(userRole() == 'd') $locked = locked('d-');
/*
$_REQUEST['prov']  $prov
0									 0          All
-1								 0					Unassigned
N									 N					One provider
N,N,..						 Array			Many providers
*/
$messages = updateProviderCalendarsForDates($_REQUEST['prov'], $_REQUEST['start'], $_REQUEST['end'], $_REQUEST['unassigned']);
echo join("\n", $messages);

