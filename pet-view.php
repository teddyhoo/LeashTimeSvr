<?
// pet-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "pet-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_GET);

$version = $version == 'fullsize' ? '' : 'display/';
if(!$id || !file_exists($file = "{$_SESSION['bizfiledirectory']}photos/pets/$version$id.jpg"))
	$file = 'art/nopetphoto.jpg';
$pet = getPet($id);

echo "<center><img src='pet-photo.php?id=$id&version=fullsize'><p>{$pet['name']}";