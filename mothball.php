<? // mothball.php
// report on storage usage
// see https://leashtime.com/reports-deadwood.php
if($bizid = $_REQUEST['bizid']) {
	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1");
	$bizname = $biz['bizname'];
	$bizdb = $biz['db'];
	if(!$biz || !$bizname || !$bizdb) $error = "Business not found for $bizid ($bizname/$bizdb)";
	if(!$error && !in_array($bizdb, fetchCol0("SHOW databases"))) $error = "Database not found: bizdb";
	if($error) {
		echo "<h1>$error</h1>";
		exit;
	}
	$client = fetchFirstAssoc(
		"SELECT * FROM leashtimecustomers.tblclient
			WHERE garagegatecode = $bizid LIMIT 1",1);
?>
	<h2>Mothballing business #<?= $bizid ?> <?= $bizname ?> (<?= $bizdb ?>) [LT client @<?= $client['clientid'] ?>]</h2>
	<ol>
	<li>Create directory [<?= $bizdb ?>] at <a href='https://console.aws.amazon.com/s3/home?region=us-west-2#&bucket=leashtime&prefix=mothball/' target='aws'>AWS Mothball</a>
	<li>mv /var/spool/holland/default/newest/backup_data/<?= $bizdb ?>.sql.gz /var/data/mothball
	<li>chown matt /var/data/mothball/*<!-- */ -->
	<li>Upload x:\mothball\<?= $bizdb ?>.sql.gz to AWS (mothball><?= $bizdb ?>)
	<li>cd /var/data/mothball
	<li>zip -r /var/data/mothball/<?= $bizdb ?>-<?= $bizid ?>.zip /var/www/prod/bizfiles/biz_<?= $bizid ?>
	<li>In phpMyAdmin, export SELECT * FROM tbluser WHERE bizptr = <?= $bizid ?> to x:\mothball\
	<li>In phpMyAdmin, export SELECT * FROM tblpetbiz WHERE bizid = <?= $bizid ?>
	<li>chown matt /var/data/mothball/*<!-- */ -->
	<li>Upload from x:\mothball: <?= $bizdb ?>-<?= $bizid ?>.zip, <?= $bizdb ?>-tbluser.sql, <?= $bizdb ?>-tblpetbiz.sql, 
	</ol>
<hr>
	<ol>
	<li>DROP database <?= $bizdb ?>;
	<li>DELETE FROM tbluser WHERE bizptr = <?= $bizid ?>;
	<li>DELETE FROM tblpetbiz WHERE bizid = <?= $bizid ?>;
	<li>UPDATE leashtimecustomers.tblclient 
				SET garagegatecode=NULL, officenotes=CONCAT_WS('\n', officenotes, 'Mothballed biz #<?= $bizid." on ".date('Y-m-d') ?>')
				WHERE garagegatecode = <?= $bizid ?>;
	<li>rm /var/data/mothball/*<!-- */ -->
	<li>rm -Rf /var/www/prod/bizfiles/biz_<?= $bizid ?>
	</ol>

<? } ?>
<!-- 
Steps:

1. create an AWS directory: leashtime/mothball/dbname
2. upload latest backup gzip of database there
3. zip bizfiles/biz_N in bizfiles directory
4. upload backup gzip and bizfiles zip
5. in tblusers, export SELECT * FROM tbluser WHERE bizptr = {BIZID}
6. in tblpetbiz, export SELECT * FROM tblpetbiz WHERE bizid = {BIZID}
7, upload users and biz

	su
	cd /var/data/mothball
	mv /var/spool/holland/default/newest/backup_data/GZIP .
	zip -r ZIP.zip /var/www/prod/bizfiles/biz_BIZID
	SELECT * FROM tbluser WHERE bizptr = {BIZID}
	SELECT * FROM tblpetbiz WHERE bizid = {BIZID}
	chown matt *
	
	
UPLOAD mothball

DELETION

1. rm /var/data/mothball/*
3. rm -Rf /var/www/prod/bizfiles/biz_BIZID
4. DELETE FROM tbluser WHERE bizptr = BIZID
5. DELETE FROM tblpetbiz WHERE bizid = BIZID
6. DROP database DBNAME

laughingpetsatlanta 384

-->