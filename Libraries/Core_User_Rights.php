<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 1/15/2015
 * Time: 12:48 PM
 */

include_once("Core_Project.php");

# Class for looking up and editing a single user's rights on a project
class Core_User_Rights {
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
	private $username;

	public function __construct(Core_Project $project, $username) {
		$this->project = $project;
		$this->username = $username;
	}

	public function updateRights($newRights) {
		$setSQL = "";
		foreach($this as $fieldName => &$value) {
			if(in_array($fieldName,array("project","username"))) continue;

			if(!isset($newRights[$fieldName]) || $newRights[$fieldName] == "") {
				$value = 0;
			}
			else {
				$value = $newRights[$fieldName];
			}
			$setSQL = ($setSQL == "" ? "" : ",\n")."$fieldName = $value";
		}

		$sql = "UPDATE redcap_user_rights
				SET $setSQL
				WHERE project_id = ".$this->project->getProjectId()."
					AND username = '{$this->username}'";

		echo $sql;

		//if(!db_query($sql)) echo "ERROR";
	}
} 