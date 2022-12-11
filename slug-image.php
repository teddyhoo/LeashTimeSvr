<? // slug-image.php
/* param: slug - an encoded, encrypted URL.  fetch the image
   no param: use makeSlugURL($url)
 */
require_once "encryption.php";


if($slug = $_GET['slug']) {
	require_once "common/init_session.php";
	$url = decodeSlug($slug);
	$fname = strpos($url, '?') ? substr($url, 0,  strpos($url, '?')) : $url;
	
	$ctypes = array('jpg'=>'jpeg', 'png'=>'png', 'jpeg'=>'jpeg', 'gif'=>'gif');
	$extension = strtolower(substr($fname, strrpos($fname, '.')+1));
	} else
	header("Content-Type: image/{$ctypes[$extension]}");
	header("Pragma: public"); // required
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false); // required for certain browsers
	header("Content-Transfer-Encoding: binary");
	
	readfile($url);
}

function makeSlugURL($url) {
	$slug = urlencode(makeSlug($url));
	return globalURL("slug-image.php?slug=$slug");
}

function makeSlug($url) {
	$slug = lt_encrypt(base64_encode(gzdeflate($url)));
//echo "slug size: ".strlen($slug).'<hr>';	
	return $slug;
}

function decodeSlug($slug) {
	require_once "common/init_session.php";
	$url = gzinflate(base64_decode(lt_decrypt($slug)));
	return "$url";
}
