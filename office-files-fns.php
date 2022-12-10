<? // office-files-fns.php

// OFFICE DOCUMENTS
// Each uploaded file for the business will be represented by a preference
// named officedoc_{remote_file_id}
// the value will be a JSON array: {fileid, label, audience, hidden}

require_once "preference-fns.php";

function getOfficeDocSizeLimit() {
	return 25;
}

function getOfficeOwnerPtr() {
	static $ownerptr;
	if(!$ownerptr) $ownerptr = -99;
	return $ownerptr;
}

function countOfficeDocs($including=null) {
	$filecount = count($files = getOfficeFiles());
	if($including) {
		$baseName = basename($including);
		foreach($files as $file)
			if($baseName == basename($file['remotepath']))
				$filecount += 1;
	}
	return $filecount;
}

function overQuota($including=null) {
	return countOfficeDocs($including) > getOfficeDocSizeLimit();
}

function setOfficeDoc($fileid, $label, $audience, $hidden) {
	require_once "preference-fns.php";
	$property = "officedoc_$fileid";
	$value = array('fileid'=>$fileid, 'label'=>$label, 'audience'=>$audience, 'hidden'=>$hidden);
	return setPreference($property, json_encode($value));
}

function getBlankOfficeDoc($fileid) {
	// TBD: figure out whether to surface officeonly and providerreadonly default values
	return array('fileid'=>$fileid, 'audience'=>'OfficeOnly');
}

function getOfficeDoc($fileid) {
	require_once "preference-fns.php";
	$property = "officedoc_$fileid";
	$doc = fetchPreference($property);	
	return json_decode($doc, 'ASSOC');
}

function dropOfficeDoc($fileid) {
	require_once "preference-fns.php";
	$property = "officedoc_$fileid";
	return setPreference($property, null);
}

function visibleDocumentLinks($audience) {
	$docs = visibleAudienceDocuments($audience);
	$links = array();
	//print_r($docs);
	foreach($docs as $doc) {
		$type = $doc['contentType'];
		$link = array('label'=>"{$doc['label']} ($type)", 'fileid'=>$doc['fileid']);
		if($audience == 'Public') $link['url'] = publicDocumentLink($doc['fileid']);
		else $link['action'] = "fileView({$doc['fileid']})";
		$links[] = $link;
	}
	return $links;
}


function visibleAudienceDocuments($audience) {
	$files = getOfficeFiles($audience, 'visible');
	$docs = array();
	foreach($files as $remotefileid => $file) {
		$doc = getOfficeDoc($remotefileid);
		$doc = $doc ? $doc : getBlankOfficeDoc($remotefileid);
		$doc['label'] = $doc['label'] ? $doc['label'] : basename($file['remotepath']);
		require_once "remote-file-storage-fns.php";
		$obj = remoteObjectDescription(absoluteRemotePath($file['remotepath']));
		$doc['contentType'] = mimeTypeLabel($obj['ContentType']);
		//$doc['contentType'] = enhanced_mime_content_type($file['remotepath']); // <== ONLY WORKS ON LOCAL FILES
		$docs[] = $doc;
	}
	usort($docs, 'cmpLabels');
	return $docs;
}

function cmpLabels($a, $b) {
	return strcmp(strtoupper("{$a['label']}"), strtoupper("{$b['label']}"));
}

function getOfficeFiles($audience=null, $visibility='all') { //visibility=all(or null) | hidden | visible
	$ownerptr = getOfficeOwnerPtr();
	$files = fetchAssociationsKeyedBy(($sql =
		"SELECT remotefileid, remotepath, filesize 
			FROM tblremotefile
			WHERE ownerptr = $ownerptr AND ownertable = 'office'
			ORDER BY remotepath"), 'remotefileid', 1);
			
//echo "getOfficeFiles [$ownerptr]$sql<br>".count($files);
	if(!$audience && ($anyVisibility = !$visibility || $visibility == 'all'))
		return $files;
	// audience: OfficeOnly/Sitters/Clients/Public
	
	$results = array();
	foreach($files as $remotefileid => $file) {
		$doc = getOfficeDoc($remotefileid);
		$doc = $doc ? $doc : getBlankOfficeDoc($remotefileid);

		if($audience && $doc['audience'] != $audience) continue;
		$docVisibility = $doc['hidden'] ? 'hidden' : 'visible';
		if($visibility && !$anyVisibility && $docVisibility != $visibility) continue;
		//if(mattOnlyTEST()) {echo "$visibility == $docVisibility  ".print_r($doc, 1)."<br> ";exit;}
		$results[$remotefileid] = $file;
	}
	//if($visibility=='visible') echo "$sql<hr>[[[[[$visibility<hr>".print_r($results, 1);
	return $results;
}

function officeDocNugget($fileid, $bizptr=null) {
	$bizptr = $bizptr ? $bizptr : $_SESSION["bizptr"];
	require_once "encryption.php";
	return urlencode(lt_encrypt(json_encode(array("bizptr"=>$bizptr, "fileid"=>$fileid))));
}
	
function publicDocumentLink($fileid) {
	return globalURL("public-file-view.php?nugget=".officeDocNugget($fileid));
}

// END OFFICE DOCUMENTS

