<?php
$doNotLoad = false;
if(class_exists("Plugin_Core",false)) {
	$doNotLoad = true;
}
## Include the Core object
include_once('Plugin_Core.php');

if(!$doNotLoad) {
	$GLOBALS['Core'] = new Plugin_Core();
}




