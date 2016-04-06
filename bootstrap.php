<?php
$doNotLoad = false;
if(isset($GLOBALS['Core'])) {
	$doNotLoad = true;
}
## Include the Core object
include_once('Plugin_Core.php');

if(!$doNotLoad) {
	$GLOBALS['Core'] = new Plugin_Core();
}




