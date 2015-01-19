<?php
/** Author: Jon Scherdin */

class Core {
	# global error logging function
	public static function logEvent($text, $e) {
		# Configure logging
		$log = KLogger::instance(dirname(dirname(__FILE__)).DS."var", KLogger::DEBUG);

		# log error
		$log->logError($text, $e);
	}

	# rewrite of log_event function (/config/init_functions.php) in redcap so that we can pass in a project id
	public static function log_rc_event($project_id, $sql, $table, $event, $record, $display, $descrip="", $change_reason="", $userid_override="")
	{
		//global $user_firstactivity, $rc_connection;

		// Pages that do not have authentication that should have USERID set to [non-user]
		$nonAuthPages = array("SendIt/download.php", "PubMatch/index.php", "PubMatch/index_ajax.php");

		// Log the event in the redcap_log_event table
		$ts 	 	= str_replace(array("-",":"," "), array("","",""), date('Y-m-d H:i:s'));
		$page 	 	= (defined("PAGE") ? PAGE : (defined("PLUGIN") ? "PLUGIN" : ""));
		$userid		= ($userid_override != "" ? $userid_override : (in_array(PAGE, $nonAuthPages) ? "[non-user]" : (defined("USERID") ? USERID : "")));
		$ip 	 	= (isset($userid) && $userid == "[survey respondent]") ? "" : getIpAddress(); // Don't log IP for survey respondents
		$event	 	= strtoupper($event);
		$event_id	= (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : "NULL";
		//$project_id = defined("PROJECT_ID") ? PROJECT_ID : 0;

		// Query
		$sql = "INSERT INTO redcap_log_event
			(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
			VALUES ($project_id, $ts, '".prep($userid)."', ".checkNull($ip).", '$page', '$event', '$table', ".checkNull($sql).",
			".checkNull($record).", $event_id, ".checkNull($display).", ".checkNull($descrip).", ".checkNull($change_reason).")";
		//debug($sql);
		//echo "$sql<br/>";
		$q = db_query($sql);
		$log_event_id = ($q ? db_insert_id() : false);

		// FIRST/LAST ACTIVITY TIMESTAMP: Set timestamp of last activity (and first, if applicable)
		if (defined("USERID") && strpos(USERID, "[") === false)
		{
			// SET FIRST ACTIVITY TIMESTAMP
			// If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
			/*if ($user_firstactivity == "") {
				$sql = "update redcap_user_information set user_firstactivity = '".NOW."'
					where username = '".prep(USERID)."' and user_firstactivity is null and user_suspended_time is null";
				db_query($sql);
			}*/
			// SET LAST ACTIVITY TIMESTAMP FOR USER
			// (but NOT if they are suspended - could be confusing if last activity occurs AFTER suspension)
			$sql = "update redcap_user_information set user_lastactivity = '".NOW."'
				where username = '".prep(USERID)."' and user_suspended_time is null";
			db_query($sql);
		}
		// SET LAST ACTIVITY TIMESTAMP FOR PROJECT
		if ($project_id)
		{
			$sql = "update redcap_projects set last_logged_event = '".NOW."' where project_id = $project_id";
			db_query($sql);
		}

		// Return log_event_id PK if true or false if failed
		return $log_event_id;
	}
}