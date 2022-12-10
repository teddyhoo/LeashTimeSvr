<?
//googleMapv3ForVisitSheet.php
?>
<style>
.maplabel {color:black;font-size:12px;}
</style>

<?    
    require_once('GoogleMapAPIv3.php');
tallyPage('googleMapv3ForVisitSheet.php');

    $map = new GoogleMapAPI($mapId);
    // !! $map->setLookupService('YAHOO');
    // setup database for geocode caching
//    echo "BEFORE<P>";
//  $googleAPIKey = $googleMapAPIKey;  //from init_session.php  //"ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBRTd7GOr-Pj_JdPg_LHg_41MAgVahQ0k8jOTF9nSngAVbLuLRvC8HT0ew"; // for iwmr.info
 /*
 CREATE TABLE `geocodes` (
   `address` VARCHAR(255) NOT NULL DEFAULT '',
   `lat` FLOAT NOT NULL DEFAULT 0,
   `lon` FLOAT NOT NULL DEFAULT 0,
   PRIMARY KEY(`address`)
 )
ENGINE = InnoDB;
*/
    $map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
    
    // enter YOUR Google Map Key
    //$map->setAPIKey('ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBRsBIsYDhcC3JmPleuN_fBYmFbolhQLQWhOCpB61BznRvFDWQlAfoGwlQ');
    //$map->setAPIKey($googleAPIKey);
    $map->_db_cache_table = 'geocodes';    

    //$map->disableZoomEncompass();

    /**
     * adds a map marker by address
     *
     * @param string $address the map address to mark (street/city/state/zip)
     * @param string $title the title display in the sidebar
     * @param string $html the HTML block to display in the info bubble (if empty, title is used)
     */
    // create some map markers
		$label = "<span class=maplabel>$googleAddress</span>";
		//$map->sidebar = false;
		$map->addMarkerByAddress($googleAddress,$label,null);
		//$map->addMarkerIcon('cosmic-adventures/star.gif');

    $map->setWidth('350px');
    $map->setHeight('350px');
    $map->setInfoWindowTrigger('click');  //mouseover
    $map->directions = true;
    ?>

    <?php $map->printHeaderJS(); ?>
    <?php $map->printMapJS($index); ?>
<? //</head> ?>

    <?php //$map->printMap(); ?>

