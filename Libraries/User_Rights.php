<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 1/15/2015
 * Time: 12:48 PM
 */

include_once("Project.php");

# Class for looking up and editing a single user's rights on a project
class User_Rights {
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

	public function __construct(Project $project, $username) {
		$this->project = $project;
		$this->username = $username;
	}

	public function updateRights($newRights) {
		if($this->project->getProjectId() != "") {
			$sql = "UPDATE redcap_user_rights
					SET " . self::getSetRightsSql($newRights, $this->project) . "
					WHERE project_id = " . $this->project->getProjectId() . "
						AND username = '{$this->username}'";

			echo $sql;

			if (!db_query($sql)) throw new Exception("ERROR - " . db_error());
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
			$setSQL = "data_entry = '";

			while($row = db_fetch_assoc($result)) {
				$setSQL .= "[{$row['form_name']},{$newRights['form-all-rights']}]";
			}
			$setSQL .= "'";
		}

		foreach(get_object_vars(new User_Rights(null,null)) as $fieldName => $value) {
			if(in_array($fieldName,array("project","username"))) continue;

			if(!isset($newRights[$fieldName]) || $newRights[$fieldName] == "") {
				$value = 0;
			}
			else {
				$value = $newRights[$fieldName];
			}
			$setSQL .= ($setSQL == "" ? "" : ",\n")."$fieldName = $value";
		}

		return $setSQL;
	}
	public static function printRightsPage(Project $project) {
		echo "User Or Role:
		<select name='roleOrUser' onchange='$(\"#userSelect\").hide(); $(\"#roleSelect\").hide(); ".
			"if($(this).val() == \"Role\") { $(\"#roleSelect\").show() } else { $(\"#userSelect\").show() }'>
			<option value='Role'>Role</option>
			<option value='User_Rights'>User</option>
		</select><br />
		<select name='user' style='display:none' id='userSelect' >";
		$userList = Core::User_Rights->getUsersByProject($project);

		foreach($userList as $username) {
			echo "<option value='$username'>$username</option>";
		}
		echo "</select>
		<select name='role' id='roleSelect' >";
		$roleList = Core::Role->getRolesByProject($project);

		foreach($roleList as $rolename) {
			echo "<option value='$rolename'>$rolename</option>";
		}
		echo "</select>
		<div style='height:500px;overflow:scroll'>";

		$protocol = $_SERVER['HTTPS'] == '' ? 'http://' : 'https://';
		$folder = $protocol . $_SERVER['HTTP_HOST'];
		$url = $folder.APP_PATH_WEBROOT."/UserRights/edit_user.php?pid=".$project->getProjectId();
		$data = file_get_contents($url);
		$formStart = strpos($data,"<form");
		$formEnd = strpos($data,">",$formStart);
		$data = str_replace(substr($data,$formStart,$formEnd - $formStart + 1),"",$data);
		$data = str_replace("</form>","",$data);
		echo "<form id='user_rights_form' action='user_rights_save.php' method='post'>";
		echo $data;
		echo "<input type='submit' value='Submit' /></form>";
		echo "</div>";
		echo "<script type='text/javascript'>
		\$(document).ready(function() {
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