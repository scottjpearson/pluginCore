<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 1/15/2015
 * Time: 12:47 PM
 */
namespace Plugin;

include_once("Project.php");
include_once("User_Rights.php");

# Class for looking up and editing a single role on a project
class Role {
	# Columns
	public $lock_record;
	public $lock_record_multiform;
	public $data_export_tool;
	public $data_import_tool;
	public $data_comparison_tool;
	public $data_logging;
	public $file_repository;
	public $user_rights;
	public $data_access_groups;
	public $reports;
	public $calendar;
	public $data_entry;
	public $record_create;
	public $participants;
	private $project;
	private $roleId;

	public function __construct(Project $project, $roleId) {
		$this->project = $project;
		$this->roleId = $roleId;
	}

	public static function constructByRoleName(Project $project, $roleName) {
		$roleId = self::getRoleId($project, $roleName);

		return new self($project, $roleId);
	}

	public function updateRights($newRights) {
		$setSQL = "";
		foreach(get_object_vars($this) as $fieldName => $value) {
			if(in_array($fieldName,array("project","username"))) continue;

			if(!isset($newRights[$fieldName]) || $newRights[$fieldName] == "") {
				$this->$fieldName = 0;
			}
			else {
				$this->$fieldName = $newRights[$fieldName];
			}
			$setSQL .= ($setSQL == "" ? "" : ",\n")."$fieldName = {$this->$fieldName}";
		}

		$sql = "UPDATE redcap_user_roles
				SET $setSQL
				WHERE project_id = ".$this->project->getProjectId()."
					AND role_id = '{$this->roleId}'";

		echo $sql;

		if(!db_query($sql)) throw new Exception("ERROR - ".db_error());
	}

	public static function getRoleId(Project $project, $roleName) {
		$sql = "SELECT DISTINCT r.role_id
				FROM redcap_user_roles r
				WHERE r.project_id = ".$project->getProjectId()."
					AND r.rolename = '$roleName'";

		return db_result(db_query($sql),0);
	}

	public static function getRolesByProject($projects) {
		if(!is_array($projects)) {
			$projects = array($projects);
		}

		$projectIds = array();
		foreach($projects as $singleProject) {
			$projectIds[] = $singleProject->getProjectId();
		}

		$roleList = array();

		$sql = "SELECT DISTINCT r.role_name
				FROM redcap_user_roles r
				WHERE r.project_id IN ('".implode("', '",$projectIds)."')";

		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$roleList[] = $row["role_name"];
		}
		return $roleList;
	}

}