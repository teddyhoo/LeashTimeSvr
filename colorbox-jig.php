<? // colorbox-jig.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

locked('o-');

include "frame.html";

echoButton('', 'Open Lightbox', 
							'$.fn.colorbox({href:"schedule-plan-edit.php?client=47", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});',
							'BigButton', 'BigButtonDown', null, 'Open the lightbox.');
