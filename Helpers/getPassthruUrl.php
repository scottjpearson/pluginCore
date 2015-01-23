<?php
function getPassthruUrl($projectId, $participantId) {
	$iv = base64_decode('nPPXunwAj9E*'); /* our initialization vector */
	$key = md5('ClickityClackity');      /* our lovely hard-wired key */
	$input = $projectId."|".$participantId;
	$code = urlencode(base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $key, $input, MCRYPT_MODE_CBC, $iv)));
	$url = "passthru.php?code=$code";

	return $url;
}