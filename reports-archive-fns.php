<? // reports-archive-fns.php

function ensureReportsArchiveExists() {
	doQuery(
	"CREATE TABLE IF NOT EXISTS `tblreportsarchive` (
		`reportid` int(11) NOT NULL AUTO_INCREMENT,
		`type` varchar(100) NOT NULL,
		`label` varchar(256) NOT NULL,
		`parameters` text,
		`hide` tinyint(4) DEFAULT '0',
		`body` mediumtext,
		`created` datetime NOT NULL,
		`createdby` int(11) NOT NULL,
		PRIMARY KEY (`reportid`),
		KEY `type` (`type`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='first created for tax reports' AUTO_INCREMENT=1 ;
	");
}

function reportsArchiveExists() {
	global $db;
	return fetchRow0Col0(
		"SELECT 1 
			FROM information_schema.tables
			WHERE table_schema = '$db' 
					AND table_name = 'tblreportsarchive'
			LIMIT 1;", 1);
}

function archiveReport($type, $label, $parameters, $body) {
	ensureReportsArchiveExists();
	if($parameters && is_array($parameters)) $parameters = json_encode($parameters);
	$record = array(
			'type'=>$type,
			'label'=>$label,
			'parameters'=>$parameters,
			'body'=>$body,
			'created'=>date('Y-m-d H:i:s'),
			'createdby'=>$_SESSION['auth_user_id']);
	return insertTable('tblreportsarchive', $record);
}
	
function getArchivedReportsOfType($type) {
	if(!reportsArchiveExists()) return array();
	return fetchAssociations("SELECT * FROM tblreportsarchive WHERE type = '$type' ORDER BY created ASC");
}

function getArchivedReportsOfTypeSummaries($type, $includeHidden=true) {
	if(!reportsArchiveExists()) return array();
	$hiddenClause = $includeHidden ? '' : "AND hide= 0";
	return fetchAssociationsKeyedBy(
		"SELECT reportid, type, label, created, createdby 
			FROM tblreportsarchive 
			WHERE type = '$type' $hiddenClause ORDER BY created ASC", 'reportid');
}

function getArchivedReport($id) {
	if(!reportsArchiveExists()) return array();
	return fetchFirstAssoc(
		"SELECT *
			FROM tblreportsarchive 
			WHERE reportid = '$id' ORDER BY created ASC", 1);
}

function hideArchivedReport($id) {
	if(!reportsArchiveExists()) return array();
	return doQuery(
		"UPDATE tblreportsarchive  SET hide=1
			WHERE reportid = '$id'", 1);
}

