// mm-db-fns.js
// database functions form mobile manager

var db = window.openDatabase("mobilemanager", "", "LeashTime Mobile Manager", 1024*1000);

function init() {
	flushDB();
}

function flushDB() {
	db.transaction(function(tx) {
	  tx.executeSql(' TABLE IF NOT EXISTS `tblclientrequest`');
});

/*
CREATE TABLE IF NOT EXISTS `tblclientrequest` (
  `requestid` int(10) unsigned NOT NULL auto_increment,
  `clientptr` int(10) unsigned default NULL,
  `providerptr` int(11) unsigned default NULL COMMENT 'When supplied, indicates provider who submitted request',
  `fname` varchar(45) default NULL,
  `lname` varchar(45) default NULL,
  `address` text,
  `street1` varchar(45) default NULL,
  `street2` varchar(45) default NULL,
  `city` varchar(45) default NULL,
  `state` varchar(45) default NULL,
  `zip` varchar(12) default NULL,
  `phone` varchar(45) default NULL,
  `email` varchar(60) default NULL,
  `pets` text,
  `note` text,
  `officenotes` text,
  `whentocall` varchar(45) default NULL,
  `extrafields` text,
  `resolved` tinyint(1) NOT NULL default '0',
  `received` datetime NOT NULL default '0000-00-00 00:00:00',
  `requesttype` varchar(20) NOT NULL default '',
  `scope` varchar(45) default NULL,
  `resolution` varchar(45) default NULL,
  PRIMARY KEY  (`requestid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1004 ;

*/