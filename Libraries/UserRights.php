<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 1/15/2015
 * Time: 12:48 PM
 */
namespace Plugin;

include_once("Project.php");

use \Exception;

# Class for looking up and editing a single user's rights on a project
class UserRights {
	public static $SQL_ERROR = 1;
	public static $DEFAULT_VALUES = array("lock_record" => 0,
			"lock_record_multiform" => 0,
			"data_export_tool" => 0,
			"data_import_tool" => 0,
			"data_comparison_tool" => 0,
			"data_logging" => 0,
			"file_repository" => 0,
			"user_rights" => 0,
			"design" => 0,
			"data_access_groups" => 0,
			"reports" => 0,
			"calendar" => 0,
			"data_entry" => "",
			"record_create" => 0,
			"participants" => 0);

	# Columns
	public $lock_record;
	public $lock_record_multiform;
	public $data_export_tool;
	public $data_import_tool;
	public $data_comparison_tool;
	public $data_logging;
	public $file_repository;
	public $user_rights;
    public $has_access;
	public $design;
	public $data_access_groups;
	public $reports;
	public $calendar;
	public $data_entry;
	public $record_create;
	public $participants;
	public $role_name;
	public $dag_name;
	public $role_id;
	public $group_id;
	public $user_email;
	public $user_firstname;
	public $user_lastname;
	public $super_user;
	public $allow_create_db;
	private $project;
	private $username;

	/**
	 * @param Project|NULL $project
	 * @param String $username
	 *
	 * @throws Exception
	 */
	public function __construct($project, $username) {
		if($project) {
			$this->project = $project;
		}
		else {
			$this->project = "";
		}

		if($username) {
			$this->username = $username;
		}
		else {
			$this->username = "";
		}

		## Lookup user's information
		if($this->username != "") {
			$sql = "SELECT i.user_email, i.user_firstname, i.user_lastname, i.super_user, i.allow_create_db
					FROM redcap_user_information i
					WHERE i.username = '{$this->username}'";

			$query = db_query($sql);
			if(!$query) throw new \Exception("Error looking up user information", self::$SQL_ERROR);

			if($row = db_fetch_assoc($query)) {
				## Set all the variables
				$this->user_email = $row["user_email"];
				$this->user_firstname = $row["user_firstname"];
				$this->user_lastname = $row["user_lastname"];
				$this->super_user = $row["super_user"];
				$this->allow_create_db = $row["allow_create_db"];
			}
		}

		## Lookup user's role, role_rights and user_rights
		if($this->project != "" && $this->username != "") {
			$sql = "SELECT u.expiration, u.role_id, u.group_id, d.group_name, r.role_name,
						u.lock_record, u.lock_record_multiform, u.data_export_tool, u.data_import_tool, u.data_comparison_tool,
						u.data_logging, u.file_repository, u.user_rights, u.design, u.data_access_groups, u.reports, u.calendar,
						u.data_entry, u.record_create, u.participants,
						r.lock_record AS role_lock_record,  r.lock_record_multiform AS role_lock_record_multiform,  r.data_export_tool AS role_data_export_tool,
						r.data_import_tool AS role_data_import_tool,  r.data_comparison_tool AS role_data_comparison_tool,
						r.data_logging AS role_data_logging,  r.file_repository AS role_file_repository,  r.user_rights AS role_user_rights,
						r.design AS role_design,  r.data_access_groups AS role_data_access_groups,  r.reports AS role_reports,
						r.calendar AS role_calendar, r.data_entry AS role_data_entry,  r.record_create AS role_record_create,  r.participants AS role_participants
					FROM redcap_user_rights u
					LEFT JOIN redcap_data_access_groups d
						ON (u.group_id = d.group_id
						AND d.project_id = " . $project->getProjectId() . ")
					LEFT JOIN redcap_user_roles r
						ON (u.role_id = r.role_id
						AND r.project_id = " . $project->getProjectId() . ")
					WHERE u.project_id = " . $project->getProjectId() . "
						AND u.username = '$username'";

            //die( $sql );

			$query = db_query($sql);
			if(!$query) throw new \Exception("Error looking up user rights", self::$SQL_ERROR);

			if($row = db_fetch_assoc($query)) {
				## Import all variables that are set by role
				foreach(get_class_vars(get_class($this)) as $varName => $nullVal) {
					## Variables set by role will have by a standard and role_ version from the above query
					if(isset($row[$varName]) && isset($row["role_".$varName])) {
						## Override user_rights table with role version if the person has a role
						$this->$varName = ($row["role_id"] == "" ? $row[$varName] : $row["role_".$varName]);
					}
					else if(isset($row[$varName])) {
						$this->$varName = $row[$varName];
					}
				}

				$this->role_name = $row["role_name"];
				$this->dag_name = $row["group_name"];
				$this->role_id = $row["role_id"];
				$this->group_id = $row["group_id"];
                $this->has_access = 1;
			} else {
                $this->has_access = 0;
            }
		}
	}

	public function updateRights($newRights) {
		if($this->project->getProjectId() != "") {
			$sql = "UPDATE redcap_user_rights
					SET " . self::getSetRightsSql($newRights, $this->project) . "
					WHERE project_id = " . $this->project->getProjectId() . "
						AND username = '{$this->username}'";

			$query = db_query($sql);
			if (!$query) throw new Exception("ERROR - " . db_error(),self::$SQL_ERROR);

			## Need to create rights because none currently exist
			if(db_num_rows($query) == 0) {
				## Set default values is fields aren't specified
				foreach(self::$DEFAULT_VALUES as $fieldName => $defaultValue) {
					if(!isset($newRights[$fieldName])) {
						$newRights[$fieldName] = $defaultValue;
					}
				}

				$sql = "INSERT INTO redcap_user_rights
						(".implode(",",array_keys($newRights)).") VALUES
						('".implode("','",$newRights)."')";

				if (!db_query($sql)) throw new Exception("ERROR - " . db_error()."\n".$sql,self::$SQL_ERROR);
			}
		}
	}

	public static function getCurrentUserRights(Project $project) {
		if(defined("USERID")) {
			return new self($project, USERID);
		}
		else {
			return null;
		}
	}

	public static function getUsersByProject($projects) {
		if(!is_array($projects)) {
			$projects = array($projects);
		}

		$projectIds = array();
		foreach($projects as $singleProject) {
			$projectIds[] = $singleProject->getProjectId();
		}

		$userList = array();

		$sql = "SELECT DISTINCT r.username
				FROM redcap_user_rights r
				WHERE r.project_id IN ('".implode("', '",$projectIds)."')";

		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$userList[] = $row["username"];
		}
		return $userList;
	}

	public static function getSetRightsSql($newRights, Project $project = null) {
		$setSQL = "";

		# If setting all form rights, need to look up the forms to set the rights
		if(isset($newRights['form-all-rights']) && $project != null) {
			$sql = "SELECT DISTINCT m.form_name
					FROM redcap_metadata m
					WHERE m.project_id = ".$project->getProjectId();

			$result = db_query($sql);
			$newRights['data_entry'] = "'";

			while($row = db_fetch_assoc($result)) {
				$newRights['data_entry'] .= "[{$row['form_name']},{$newRights['form-all-rights']}]";
			}
			$newRights['data_entry'] .= "'";
		}

		foreach(self::$DEFAULT_VALUES as $fieldName => $value) {
			if(in_array($fieldName,array("project","username","roleId"))) continue;

			if(!isset($newRights[$fieldName]) || $newRights[$fieldName] == "") {
				$value = 0;
			}
			else {
				$value = $newRights[$fieldName];
			}
			$setSQL .= ($setSQL == "" ? "" : ",\n")."$fieldName = $value";
		}

		if($setSQL == "") {
			$setSQL = "project_id = project_id";
		}
//echo "SQL: $setSQL <br /><br />";
		return $setSQL;
	}

	public static function printRightsPage(Project $project, $resultsUrl) {
		echo "<form action='$resultsUrl' method='post'>";

		echo "<table>
		<tr><td>User Or Role:</td>
		<td><select name='roleOrUser' onchange='$(\"#userSelect\").hide(); $(\"#roleSelect\").hide(); ".
			"if($(this).val() == \"Role\") { $(\"#roleSelect\").show() } else { $(\"#userSelect\").show() }'>
			<option value='Role'>Role</option>
			<option value='User_Rights'>User</option>
		</select></td></tr>
		<tr id='userSelect'><td>User:</td><td>";
		$userList = UserRights::getUsersByProject($project);

		foreach($userList as $username) {
			echo "<input type='checkbox' name='user[]' value='$username' />$username<br />";
		}
		echo "</td></tr>
		<tr id='roleSelect'><td>Role:</td><td><select name='role' >";
		$roleList = Role::getRolesByProject($project);

		foreach($roleList as $rolename) {
			echo "<option value='$rolename'>$rolename</option>";
		}
		echo "</select></td></tr></table>
		<div id='user_rights_form' style='height:500px;overflow:scroll'>";

		$protocol = $_SERVER['HTTPS'] == '' ? 'http://' : 'https://';
		$folder = $protocol . $_SERVER['HTTP_HOST'];
		$url = $folder.APP_PATH_WEBROOT."/UserRights/edit_user.php?pid=".$project->getProjectId()."&" . session_name() . "=" . session_id(); # Path to
		//echo "URL: $url <br />";
		$opts = array('http' => array('header'=> 'Cookie: ' . $_SERVER['HTTP_COOKIE']."\r\n"));
		$context = stream_context_create($opts);
		$data = file_get_contents($url,false,$context);
		$formStart = strpos($data,"<form");
		$formEnd = strpos($data,">",$formStart);
		$data = str_replace(substr($data,$formStart,$formEnd - $formStart + 1),"",$data);
		while($hiddenInputStart = strpos($data,"<input type='hidden'")) {
			$hiddenInputEnd = strpos($data,">",$hiddenInputStart);
			$data = str_replace(substr($data,$hiddenInputStart,$hiddenInputEnd - $hiddenInputStart + 1),"",$data);
		}
		$data = str_replace("</form>","",$data);

		echo $data;
		echo "</div>";
		echo "<input type='submit' value='Submit' /></form>";
		echo "<script type='text/javascript'>
		\$(document).ready(function() {
			\$('select[name=\"roleOrUser\"]').change();
			\$('#user_rights_form').find('div').each(function(num){ if(num < 2) $(this).remove(); });
			\$('#user_rights_form').find('.hidden').hide();
			\$('#user_rights_form').find('input[type=\"checkbox\"], input[type=\"radio\"]').attr('checked', false);
			\$('#form_rights').find('tr').each(function(num) { if(num > 1) $(this).remove(); });
			\$('#form_rights').find('tr').last().after(\"<tr><td>All Forms</td><td valign='middle' style='display:block' class='nobr derights2'>\" +
			\"<input type='radio' name='form-all-rights' value='0'>\" +
			\"<input type='radio' style='width:65px' name='form-all-rights' value='2'>\" +
			\"<input type='radio' name='form-all-rights' value='1'>		</td></tr>\");
		});
		</script>";
	}
} 