<? // mmd-sitters.php
// Mobile Manager Dashboard script to return sitters
/* parameters:
activeOnly - if true, only active sitters are returned
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "mmd-api.php";

if(userRole() == 'p') locked('p-');
else {
	if(locked($requiredRights='o-', $noForward=true, $exitIfLocked=false))
		$sitters = array('failure'=>'locked');
		else {$sitters = getSitters($activeOnly);}
}
header("Content-type: application/json");
if(!$sitters) $sitters = array('sittercount'=>0);
if($sitters['failure']) ; // no-op
else $sitters = array('sittercount'=>count($sitters), 'sitters'=>$sitters);
echo json_encode($sitters);  

//logChange(999, "mmd-sitters", 'L', "TEST returned {$sitters['sittercount']} sitters.");
