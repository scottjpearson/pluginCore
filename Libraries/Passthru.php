<?php

namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("Record"));
$Core->Helpers(array("cleanupSurveyParticipantsBySurveyRecord"));

class Passthru {

	public static function passthruToSurvey(\Plugin\Record $record, $surveyFormName = "", $dontCreateForm = false) {
		// Get survey_id, form status field, and save and return setting
		$sql = "SELECT s.survey_id, s.form_name, s.save_and_return
		 		FROM redcap_projects p, redcap_surveys s, redcap_metadata m
					WHERE p.project_id = ".$record->getProjectObject()->getProjectId()."
						AND p.project_id = s.project_id
						AND m.project_id = p.project_id
						AND s.form_name = m.form_name
						".($surveyFormName != "" ? "AND s.form_name = '$surveyFormName'" : "")
					."LIMIT 1";
		
		$q = db_query($sql);
		$surveyFormName = db_result($q, 0, 'form_name');
		$surveyId = db_result($q, 0, 'survey_id');
		$surveyAlreadyStarted = false;
		
		// Set the response as incomplete in the data table
		$sql = "UPDATE redcap_data
				SET value = '0'
				WHERE project_id = ".$record->getProjectObject()->getProjectId()."
					AND record = '".$record->getId()."'
					AND event_id = ".$record->getProjectObject()->getEventId()."
					AND field_name = '{$surveyFormName}_complete'";
					
		$q = db_query($sql);
		// Log the event (if value changed)
		if ($q && db_affected_rows() > 0) {
			$surveyAlreadyStarted = true;
			log_event($sql,"redcap_data","UPDATE",$record,"{$surveyFormName}_complete = '0'","Update record");
		}

		# Check if a participant and response exists for this survey/record combo
		$sql = "SELECT r.response_id, p.participant_id
				FROM redcap_surveys_participants p, redcap_surveys_response r
				WHERE p.survey_id = '$surveyId'
					AND p.participant_id = r.participant_id
					AND r.record = '".$record->getId()."'";

		$result = db_query($sql);

		if(db_num_rows($result) > 1) {
			cleanupSurveyParticipantsBySurveyRecord($record->getProjectObject()->getProjectId(),$record->getId());
		}

		# Create a participant and response for this record
		if(db_num_rows($result) == 0) {
			do {
				$hash = generateRandomHash(10);

				$sql = "SELECT p.hash
						FROM redcap_surveys_participants p
						WHERE p.hash = '$hash'";

				$result = db_query($sql);

				$hashExists = (db_num_rows($result) > 0);
			} while($hashExists);

			# Since participant_id does NOT exist yet, create it.
			$sql = "INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash)
					VALUES ($surveyId, ".$record->getProjectObject()->getEventId().", '', null, '$hash')";
			
			if(!db_query($sql)) echo "Error: ".db_error()." <br />";
			$participantId = db_insert_id();

			# Since response_id does NOT exist yet, create it.
			if(!$dontCreateForm) {
				$returnCode = "'".generateRandomHash()."'";
				$firstSubmitDate = "'".date('Y-m-d h:m:s')."'";
			}
			else {
				$returnCode = "NULL";
				$firstSubmitDate = "NULL";
			}
			
			$sql = "INSERT INTO redcap_surveys_response (participant_id, record, first_submit_time, return_code)
					VALUES ($participantId, ".$record->getId().", $firstSubmitDate,$returnCode)";
			
			if(!db_query($sql)) echo "Error: ".db_error()." <br />";
		}
		# Find the existing participant and response for this record
		else {
			# Check if a participant and response exists for this survey/record combo
			$sql = "SELECT p.hash, r.return_code
				FROM redcap_surveys_participants p, redcap_surveys_response r
				WHERE p.survey_id = '$surveyId'
					AND p.participant_id = r.participant_id
					AND r.record = '".$record->getId()."'";
			
			$queryResults = db_fetch_assoc(db_query($sql));

			$returnCode = $queryResults['return_code'];
			$hash = $queryResults['hash'];
		}

		if(!$dontCreateForm) {
			// Set the response as incomplete in the response table
			$sql = "UPDATE redcap_surveys_participants p, redcap_surveys_response r
					SET r.completion_time = null
					WHERE p.survey_id = $surveyId
						AND p.event_id = ".$record->getProjectObject()->getEventId()."
						AND r.participant_id = p.participant_id
						AND r.record = '".$record->getId()."' ";
			db_query($sql);
		}

		$surveyLink = APP_PATH_SURVEY_FULL . "?s=$hash";
		//echo "$surveyLink ~ $returnCode<br />";

		@db_query("COMMIT");
		
		if($dontCreateForm) {
			return $surveyLink;
		}
		else {
			//echo "Return $returnCode ~ $surveyLink <br />";
			## Build invisible self-submitting HTML form to get the user to the survey
			echo "<html><body>
				<form name='form' action='$surveyLink' method='post' enctype='multipart/form-data'>
				".($returnCode == "NULL" ? "" : "<input type='hidden' value='$returnCode' name='__code'/>")."
				<input type='hidden' value='1' name='__prefill' />
				</form>
				<script type='text/javascript'>
					document.form.submit();
				</script>
				</body>
				</html>";
			exit;
		}
	}
} 