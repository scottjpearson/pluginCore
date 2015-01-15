<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 1/15/2015
 * Time: 12:47 PM
 */

include_once("Core_Project.php");
include_once("Core_User_Rights.php");

# Class for looking up and editing a single role on a project
class Core_Role {
	public function __construct(Core_Project $project, $roleId) {

	}

	public static function constructByRoleName(Core_Project $project, $roleName) {
		$roleId = self::getRoleId($project, $roleName);
	}

	public static function getRoleId(Core_Project $project, $roleName) {

	}
} 