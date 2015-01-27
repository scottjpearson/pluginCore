<?php
/**
 * Created by PhpStorm.
 * User: phelpsbk
 * Date: 1/27/2015
 * Time: 11:04 AM
 */

function decrypt( $value )
{
    ## Decrypt the value
    $iv = base64_decode('JSznCIFWj2E=');
    $key = md5('NcsAllDayEveryDay');
    return trim(mcrypt_decrypt(MCRYPT_BLOWFISH, $key, base64_decode($value), MCRYPT_MODE_CBC, $iv));
}

function encrypt( $value )
{
    ## Encrypt the value
    $iv = base64_decode('JSznCIFWj2E=');
    $key = md5('NcsAllDayEveryDay');
    return urlencode(base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $key, $value, MCRYPT_MODE_CBC, $iv)));
}