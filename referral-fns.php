<? // referral-fns.php

function getReferralCategories($masterSettingsKey=null, $showAll=false) {
	$where = $showAll ? '' : 'WHERE active = 1';
	$cats = fetchAssociationsKeyedBy("SELECT * FROM tblreferralcat $where ORDER BY sequence", 'referralid');
	if(!$settings && !$_SESSION['orgptr'] && $masterSettingsKey) {
		$settings = getSettings($masterSettingsKey);
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		$dbhost = $settings['dbhost'];
		$db = $settings['db'];
		$dbuser = $settings['dbuser'];
		$dbpass = $settings['dbpass'];
		$cats = fetchAssociationsKeyedBy("SELECT * FROM tblreferralcat $where ORDER BY sequence", 'referralid');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	}
	return $cats;
}

function getOrganizationReferralCategories($org, $showAll=false) {
	global $dbhost, $db, $dbuser, $dbpass;
	$where = $showAll ? '' : 'WHERE active = 1';
	require_once "org-fns.php";
	$org = getOrganization();
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
	$cats = fetchAssociationsKeyedBy("SELECT * FROM tblreferralcat $where ORDER BY sequence", 'referralid');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $cats;
}

function referralCategoryTree(&$cats, $rootid=0) {
	$tree = array();
	foreach($cats as $cat)
		if($cat['parentcatptr'] == $rootid) {
			//echo $cat['label'].'<p>';
			$tree[$cat['referralid'].'|'.$cat['label']] = referralCategoryTree($cats, $cat['referralid']);
		}
	return $tree;
}

function referralCategoryDisplayTable($cats, $level=0) {
	if($level == 0) $cats = referralCategoryTree($cats);
	//print_r($cats);
	foreach($cats as $cat => $subcats) {
		$label = explode('|', $cat);
		$label = $label[1];
		echo "<img src='art/spacer.gif' height=1 width=".($level * 10).">$label<br>";
		referralCategoryDisplayTable($subcats, $level+1);
	}
}

function getReferralCategoryPaths($categories) {
	$paths = array();
	foreach($categories as $cat) {
		$path = array();
		for($stage = $cat; $stage; $stage = $categories[$stage['parentcatptr']]) {
			$path[] = $stage['referralid'];
		}
		$paths[$cat['referralid']] = array_reverse($path);
	}
	return $paths;
}
		
function getReferralCategoryPathLabels($categories) {
	$labels = array(0=>'--Unspecified--');
	foreach(getReferralCategoryPaths($categories) as $cat => $path) {
		$label = array();
		foreach($path as $stage)
			$label[] = $categories[$stage]['label'];
		$labels[$stage] = join(' > ', $label);
	}
	return $labels;
}
		
	
function saveReferralCategories($cats) {
	$newCatIds = array();
if($cats)
	if($cats) foreach(explode("\n",trim($cats)) as $sequence => $cat) {
		$cat = explode("|", trim($cat));
		$record = array('parentcatptr'=>($cat[0] == 'top' ? 0 : $newCatIds[$cat[0]]),
										'label'=>$cat[2],
										'sequence'=>$sequence,
										'branch'=>($cat[3] == 'branch' ? 1 : 0),
										'active'=>1);
		if(strpos($cat[1], 'NEW') === 0) {
			$newCatIds[$cat[1]] = insertTable('tblreferralcat', $record, 1);
		}
		else {
			updateTable('tblreferralcat', $record, "referralid = {$cat[1]}", 1);
			$newCatIds[$cat[1]] = $cat[1];
		}
		$deactivate = array();
	}
	$oldCats = getReferralCategories($_SESSION['preferences']['masterPreferencesKey']);
	if($oldCats) foreach(array_keys($oldCats) as $id)
		if(!in_array($id, $newCatIds))
			$deactivate[] = $id;
	if($deactivate)
		updateTable('tblreferralcat', array('active'=>0), "referralid IN (".join(',', $deactivate).")", 1);
}

function externalReferralCategory($cat) {
	$cat['referralid'] = 0-$cat['referralid'];
	$cat['parentcatptr'] = 0-$cat['parentcatptr'];
	return $cat;
}
	

function getReferralPath($cat) {
	$cats = getReferralCategories($_SESSION['preferences']['masterPreferencesKey'], 1);
	if($_SESSION['orgptr'])
		foreach(getOrganizationReferralCategories($org, 1) as $orgcat)
			$cats[0-$orgcat['referralid']] = externalReferralCategory($orgcat);
//foreach($cats as $i => $c) echo "$i: ".print_r($c,1).'<br>';			
	$path = array();
	for($id = $cat; $id != 0; $id = $cats[$id]['parentcatptr'])
		$path[$id]  = $cats[$id]['label'];
	if(!$cats[$cat]['active']) $path[] = '*inactive*';
	return array_reverse($path);
}
	
/*
"top|55|Petco|branch\n"+
"55|87|Marketing|branch\n"+
"87|12|flier|leaf\n"+
"87|19|circular|leaf\n"+
"87|4|Website|leaf\n"+
"55|8|Store|branch\n"+
"8|45|Employee|leaf\n"+
"8|65|Sign|leaf\n"
+"top|999|Friend";

	foreach(explode("\n", trim($referralCats)) as $line)
*/

function referralCategoriesDescription($referralCats) {  // for use by dumpCategories in referral-categories.js
//print_r($referralCats);
	if(!$referralCats) return '""';
	$lines = array();
	foreach($referralCats as $cat)
		$lines[] = '"'.join('|', array(($cat['parentcatptr'] ? "{$cat['parentcatptr']}" : 'top'),
											$cat['referralid'],
											addslashes($cat['label']),
											($cat['branch'] ? "branch" : 'leaf'),
											$cat['active'].'\n'
											
										)).'"';
	return join("+\n", $lines);
}