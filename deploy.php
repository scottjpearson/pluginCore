#!/usr/bin/env php
<?php
global $argv;

if(strpos($argv[0],".json") === false) {
	$deployJson = $argv[0].".json";
}
else {
	$deployJson = $argv[0];
}

$config = json_decode(file_get_contents("Deploy/".$deployJson));

if(isset($argv[1])) {
	$environment = $argv[1];
}
else {
	$environment = "staging";
}

## Run git update on remote server
