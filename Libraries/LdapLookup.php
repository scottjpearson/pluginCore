<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 10/6/2016
 * Time: 4:54 PM
 */

namespace Plugin;

class LdapLookup {
	const VUNET_KEY = "uid";
	const EMAIL_KEY = "mail";
	const FIRST_NAME_KEY = "givenname";
	const LAST_NAME_KEY = "sn";
	const FULL_NAME_KEY = "cn";
	const PERSON_ID_KEY = "vanderbiltpersonemployeeid";
	const PHONE_NUMBER_KEY = "telephonenumber";
	const DEPT_NUMBER_KEY = "vanderbiltpersonhrdeptnumber";
	const DEPT_NAME_KEY = "vanderbiltpersonhrdeptname";

	private static $ldapConn;
	private static $ldapBind;

	public static function lookupUserDetailsByKey($value,$key) {
		self::initialize();

		## Search LDAP for any user matching the vunet ID
		$sr = ldap_search(self::$ldapConn, "ou=people,dc=vanderbilt,dc=edu", "(".$key."=".$value.")");

		if ($sr) {
			$data = ldap_get_entries(self::$ldapConn, $sr);
			foreach($data as $dataDetails) {
				return $dataDetails;
			}
		}
		return false;
	}

	public static function lookupUserDetailsByVunet($vunet) {
		return self::lookupUserDetailsByKey($vunet,self::VUNET_KEY);
	}

	public static function lookupUserDetailsByPersonId($personId) {
		return self::lookupUserDetailsByKey($personId,self::PERSON_ID_KEY);
	}

	public static function lookupUsersByNameFragment($nameFragment) {
		self::initialize();

		## Search LDAP for any user matching the $nameFragment on vunet, surname or givenname
		$sr = ldap_search(self::$ldapConn, "ou=people,dc=vanderbilt,dc=edu", "(|(uid=$nameFragment*)(sn=$nameFragment*)(givenname=$nameFragment*))");

		if ($sr) {
			$data = ldap_get_entries(self::$ldapConn, $sr);

			return $data;
		}
		return false;
	}


	public static function initialize() {
		if(!self::$ldapBind) {
			if(ENVIRONMENT != "DEV") {
				include "/app001/credentials/con_redcap_ldap_user.php";

				self::$ldapConn = ldap_connect("ldaps://ldap.vunetid.vanderbilt.edu","636");

				// Bind to LDAP server
				self::$ldapBind = ldap_bind(self::$ldapConn, "uid=".$ldapuser.",ou=special users,dc=vanderbilt,dc=edu", $ldappass);

				unset($ldapuser);
				unset($ldappass);
			}
		}
	}
}