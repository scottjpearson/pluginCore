<?php
function cleanupSurveyParticipantsBySurveyRecord($projectId, $surveyRecord) {
	$sql = "SELECT p.participant_id AS public_survey, p.hash AS public_hash, p2.hash, p2.participant_id, p2.participant_email,
				r.response_id, r.first_submit_time, r.completion_time, r.return_code
			FROM redcap_surveys s, redcap_surveys_participants p, redcap_surveys_participants p2, redcap_surveys_response r
			WHERE s.project_id = $projectId
				AND s.survey_id = p.survey_id
				AND p.participant_email IS NULL
				AND s.survey_id = p2.survey_id
				AND p2.participant_id = r.participant_id
				AND r.record = '$surveyRecord'";

	$result = db_query($sql);
	$publicSurvey = "";
	$otherSurveys = array();

	while($row = db_fetch_assoc($result)) {
		if($row["public_hash"] == $row["hash"]) {
			$publicSurvey = $row;
		}
		else {
			$otherSurveys[] = $row;
		}
	}

	$privateSurvey = reset($otherSurveys);

	if($privateSurvey != "" && $publicSurvey != "") {
		# Update a non-public survey with return code
		$sql = "UPDATE redcap_surveys_response r
				SET return_code = '{$publicSurvey["return_code"]}'
				WHERE response_id = '{$privateSurvey["response_id"]}'";
		if (!db_query($sql)) echo "ERROR: couldn't update return code";

		# Update a non-public survey with a first_submit_time if needed
		if($privateSurvey["first_submit_time"] == "") {
			$sql = "UPDATE redcap_surveys_response r
					SET first_submit_time = '{$publicSurvey["first_submit_time"]}'
					WHERE response_id = '{$privateSurvey["response_id"]}'";
			if (!db_query($sql)) echo "ERROR: couldn't update return code";
		}

		# Delete public survey participant and responses
		$sql = "DELETE FROM redcap_surveys_response
				WHERE response_id = '{$publicSurvey["response_id"]}'";
		if (!db_query($sql)) echo "ERROR: couldn't delete response";

		# Seems like the participant is the same for all public surveys, so we don't want to delete this
//		$sql = "DELETE FROM redcap_surveys_participants
//				WHERE participant_id = '{$publicSurvey["participant_id"]}'";
//		if (!db_query($sql)) echo "ERROR: couldn't delete participant";
	}
}