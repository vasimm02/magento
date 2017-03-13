<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of mysql4-install-1
 *
 * @author Ea Design
 */
$installer = $this;

$installer->startSetup();

$installer->run("
 DROP TABLE IF EXISTS {$this->getTable('romcity/romcity')};
CREATE TABLE {$this->getTable('romcity/romcity')} (
 `city_id` mediumint(8) unsigned NOT NULL auto_increment,
 `country_id` varchar(4) NOT NULL default '0',
 `region_id` varchar(4) NOT NULL default '0',
 `cityname` varchar(255) default NULL,
 PRIMARY KEY (`city_id`),
 KEY `FK_CITY_REGION` (`region_id`))
 ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Region Cities';
 ");


$write = Mage::getSingleton("core/resource")->getConnection("core_write");
// Now you can run ANY Magento code you want



//echo '<pre>';print_r($regions_array);

//  Initiate curl
$url="http://api.rajaongkir.com/starter/city/?key=1dea532801d380fbde5bf6de9fa6845b";
$ch = curl_init();
// Disable SSL verification
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Will return the response, if false it print the response
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Set the url
curl_setopt($ch, CURLOPT_URL,$url);
// Execute
$result=curl_exec($ch);
// Closing
curl_close($ch);

// Will dump a beauty json :3
$result=  json_decode($result,true);

/*add Region*/

$query='INSERT INTO `directory_country_region` (`country_id`, `code`, `default_name`) VALUES '
        . '(:country_id,:code,:name)';
foreach($result['rajaongkir']['results'] as $res)
{
   $region_array[]=$res['province'];
    
}
$region_array=  array_unique($region_array);
foreach($region_array as $res1)
{
   
    //$query .= '("ID","'.$res1.'","'.$res1.'"),';
    $binds=array("country_id"=>"ID","code"=>$res1,"name"=>$res1);
    $write->query($query, $binds);
}

/*add City */
// Change 12 to the ID of the product you want to load
$regionCollection = Mage::getModel('directory/region_api')->items("ID");

foreach($regionCollection as $regions)
{
    $regions_array[$regions['region_id']]=$regions['code'];
}
$query='INSERT INTO `directory_country_region_city` (`country_id`, `region_id`, `cityname`) VALUES (:country_id,:region_id,:cityname)';
foreach($result['rajaongkir']['results'] as $res)
{
  // $region_array[]=$res['province'];
    $region_id=array_search($res['province'],$regions_array);
   // $query .= '("ID","'.$region_id.'","'.$res['city_name'].'"),';
    $binds=array("country_id"=>"ID","region_id"=>$region_id,"cityname"=>$res['city_name']);
    $write->query($query, $binds);
}
$installer->endSetup();