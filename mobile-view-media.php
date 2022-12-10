<? // mobile-view-media.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "postcard-fns.php";
require_once "gui-fns.php";



dumpVideoToStdOut($_SESSION['videofile']);