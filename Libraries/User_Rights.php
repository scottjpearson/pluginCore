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
	public $design;
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

			//echo $sql;

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
			$newRights['data_entry'] = "'";

			while($row = db_fetch_assoc($result)) {
				$newRights['data_entry'] .= "[{$row['form_name']},{$newRights['form-all-rights']}]";
			}
			$newRights['data_entry'] .= "'";
		}

		foreach(get_object_vars(new User_Rights(null,null)) as $fieldName => $value) {
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
		$userList = User_Rights::getUsersByProject($project);

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