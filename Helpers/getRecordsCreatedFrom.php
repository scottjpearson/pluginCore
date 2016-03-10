<?php

function getRecordsCreatedFrom($project, $startDate, $endDate = "") {
	$startDate = is_numeric($startDate) ? $startDate : strtotime($startDate);
	$endDate = is_numeric($endDate) ? $endDate : strtotime($endDate);
	
	# This means we're going to use the "INSERT" log for each record to determine date range
	$sql = "SELECT d.pk, d.max_ts
			FROM (SELECT l.pk, MAX(l.ts) as max_ts
				FROM redcap_log_event l
				WHERE l.project_id = ".$project->getProjectId()."
					AND l.ts >= ".date("Ymd", $startDate)."000000
					AND l.event = 'INSERT'
					AND l.object_type = 'redcap_data'
				GROUP BY l.pk) AS d
			WHERE d.max_ts >= ".date("Ymd", $startDate)."000000
				AND d.max_ts < ".date("Ymd", $endDate)."999999";

	$q = db_query($sql);
	while($row = db_fetch_assoc($q)) {
		$recordsInDateRange[] = $row["pk"];
	}

	return $recordsInDateRange;
}