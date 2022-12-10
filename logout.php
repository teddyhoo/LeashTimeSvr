<? // logout.php -- simply logs out and returns status
require_once "common/init_session.php";
require_once "gui-fns.php";

if(userRole() == 'c' && $_SESSION['preferences']['bizHomePage']) $goto = $_SESSION['preferences']['bizHomePage'];
if($_SESSION['trainingMode']) {
	require_once "training-fns.php";
	require_once "common/init_db_petbiz.php";
	turnOffTrainingMode();
}
session_unset();
session_destroy();


header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
header('Access-Control-Allow-Methods: GET, OPTIONS'); // GET, PUT, POST, DELETE, OPTIONS'
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');	

echo json_encode(array('status'=>'ok'));
