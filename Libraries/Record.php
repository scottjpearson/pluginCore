<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

$GLOBALS["Core"]->Libraries(array("Project","Core","UserRights"),false);

# Class for looking up and editing a single record on a given project
class Record {
	# Error codes
	const NO_RECORD_ERROR = 1;
	const MULTIPLE_RECORD_ERROR = 2;
	const SQL_ERROR = 3;
	const INSERT_ERROR = 4;
	const INVALID_PROJECT = 5;

	protected $project;
	protected $id;
	protected $details;
	protected $keyValues;
	protected $creationTs;
	protected $tempDetails = [];

	private $keys;

	/**
	 * @param Project $project Project object linking to the Redcap project
	 * @param array $keys 2-dimensional array containing all the unique keys of the project ex: array(array("participant_id"),array("study_id","event"))
	 * @param array $keyValues array containing the actual key values for a particular record
	 */
	public function __construct($project, $keys, $keyValues) {
		$this->project = ($project == NULL ? "" : $project);
		$this->keys = ($keys == NULL ? "" : $keys);
		$this->keyValues = ($keyValues == NULL ? "" : $keyValues);

		if(!$this->verifyKeyValues()) {
			unset($this->keyValues);
		}
	}

	# Publicly access the project keys
	public function getKeys() {
		return $this->keys;
	}

	# Publicly access the record ID for the record
	public function getId() {
		return $this->fetchId();
	}

	# Publicly access the project for the record
	public function getProjectObject() {
		return $this->project;
	}

	# Publicly access the record details (or a particular column) for the record
	# $forceRefresh causes the class to lookup the details from the database
	/**
	 * @param string $columnName
	 * @param bool $forceRefresh
	 * @return string|array
	 * @throws Exception
	 */
	public function getDetails($columnName = "", $forceRefresh = false) {
		$this->fetchDetails($forceRefresh);

		### Allow array_search results to directly be input into getDetails
		if($columnName === false) return "";

		if($columnName == "") {
			return array_merge($this->tempDetails,$this->details);
		}

		if(array_key_exists($columnName,$this->details)) {
			return $this->details[$columnName];
		}
		return $this->tempDetails[$columnName];
	}

	# Make changes to the record's values. Safely inserts values and logs any changes
	# Checkboxes will need to be inserted as an array containing the new list of checked values
	/**
	 * @param $changes
	 * @param bool $skipUnchangedValues
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function updateDetails($changes, $skipUnchangedValues = true) {
		if(!isset($this->details)) $this->fetchDetails();

		$insertStatements = array();
		$logText = "";
		$logSql = "";
		$logType = "";
		$updateCount = 0;
		$newRecord = false;

		foreach($changes as $fieldName => $value) {
			# Skip values that already match $this->details
			if($skipUnchangedValues && ($this->details[$fieldName] === $value || ($this->details[$fieldName] == $value && is_numeric($this->details[$fieldName]) && is_numeric($value)))) continue;

			# Special === handling for bool(false) and "" values
			if($this->details[$fieldName] == "" && $value === false) continue;

			# For checkboxes, review the list of values before determining what to save
			if($this->project->isCheckbox($fieldName)) {
				$oldValues = $this->getDetails($fieldName, true);
				$insertValues = array();
				$deleteValues = array();
				$deleteSql = "";
				$insertSql = "";

				foreach($value as $newValue) {
					if(!in_array($newValue,$oldValues)) {
						$insertValues[] = $newValue;
					}
				}

				foreach($oldValues as $oldValue) {
					if(!in_array($oldValue,$value)) {
						$deleteValues[] = $oldValue;
					}
				}

				if(count($deleteValues) > 0) {
					$deleteSql = "DELETE FROM redcap_data
									WHERE project_id = {$this->project->getProjectId()}
										AND event_id = {$this->project->getEventId()}
										AND record = '{$this->id}'
										AND field_name = '$fieldName'
										AND value IN ('" .implode("','",$deleteValues)."')";
					if (!db_query($deleteSql)) throw new Exception("Failed to update record details " . $deleteSql, self::SQL_ERROR);
					$logSql .= $deleteSql;
					$logText .= "{$fieldName}(".implode(") = unchecked\n{$fieldName}(",$deleteValues).") = unchecked\n";
				}

				if(count($insertValues) > 0) {
					$statements = array();
					foreach($insertValues as $insertValue) {
						$statements[] = "({$this->project->getProjectId()},{$this->project->getEventId()},".
										"'{$this->id}','$fieldName','$insertValue')";
					}

					$insertSql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
									".implode(",\n",$statements);
					if (!db_query($insertSql)) throw new Exception("Failed to update record details " . $insertSql, self::SQL_ERROR);
					$logSql .= $insertSql;
					$logText .= "{$fieldName}(".implode(") = checked\n{$fieldName}(",$deleteValues).") = checked\n";
				}

				if(count($insertValues) > 0 || count($deleteValues) > 0) {
					$updateCount++;
				}
			}
			else {
				$sql = "UPDATE redcap_data
						SET value = '" . db_real_escape_string($value) . "'
						WHERE project_id = {$this->project->getProjectId()}
							AND event_id = {$this->project->getEventId()}
							AND record = '{$this->id}'
							AND field_name = '" . db_real_escape_string($fieldName) . "'";

				if (!db_query($sql)) throw new Exception("Failed to update record details " . $sql, self::SQL_ERROR);

				if (db_affected_rows() == 0) {
					if ($value !== "" && $value !== NULL) {
						# Check if inserting first field, to know if this is a new record
						if($fieldName == $this->project->getFirstFieldName()) $newRecord = true;

						$insertStatements[] = "({$this->project->getProjectId()},{$this->project->getEventId()}," .
							"'{$this->id}','$fieldName','" . db_real_escape_string($value) . "')";
					}
				} else {
					$updateCount++;
					$logSql .= ($logSql == "" ? "" : ",\n") . str_replace("\t", "", $sql);
				}
				$logText .= ($logText == "" ? "" : ",\n") . "$fieldName = '$value'";
				$this->details[$fieldName] = $value;
			}
		}

		if(count($insertStatements) > 0) {
			$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES\n".implode(",\n",$insertStatements);
			$logSql .= ($logSql == "" ? "" : ",\n").str_replace("\t","",$sql);

			if(!db_query($sql)) throw new Exception("Couldn't create Instrument record ".$sql, self::INSERT_ERROR);
		}

		if($newRecord && $updateCount == 0 && count($insertStatements) > 0) {
			$logType = "INSERT";
			$logDescription = "Creating Record";
		}
		else if($updateCount > 0 || count($insertStatements) > 0) {
			$logType = "UPDATE";
			$logDescription = "Updating Record";
		}

		if($logType != "") {
			//echo "<pre>$logSql</pre><br />";
//			echo "<pre>" . get_class($this) . "\n";
//			foreach ($changes as $fieldName => $value) {
//				echo "$fieldName => $value \n";
//			}
//			echo "</pre><br /><br />";
			Core::log_rc_event($this->project->getProjectId(), $logSql, "redcap_data", $logType, $this->id, $logText, $logDescription, "0", "[PLUGIN]");
		}

		return $this;
	}

	public function addTempDetail($fieldName,$value) {
		$this->tempDetails[$fieldName] = $value;
	}

	# Stub function for handling data triggers on particular projects
	public function trigger() {

	}

	public function setDetails($newDetails) {
		if(!isset($this->details)) {
			$this->details = $newDetails;
		}
	}

	public function checkCurrentRole() {
		return $this->getUserRights()->role_name;
	}

	public function getRecordCreationTs() {
		if(!isset($this->creationTs)) {
			$sql = "SELECT l.ts
					FROM redcap_log_event l
					WHERE l.project_id = ".$this->getProjectObject()->getProjectId()."
						AND l.pk = '".$this->getId()."'
						AND l.event = 'INSERT'
						AND l.object_type = 'redcap_data'
					ORDER BY l.ts DESC
					LIMIT 1";

			$this->creationTs = db_result(db_query($sql),0);
		}

		return $this->creationTs;
	}

	# Loops through the 2-dimensional $keys array to determine if $this->keyValues is a valid key for this project
	protected function verifyKeyValues() {
		if(count($this->keyValues) == 0) return false;

		$validKeys = array();

		for($i = 0; $i < count($this->keys); $i++) {
			$validKeys[] = true;
		}

		$column = 0;
		if($this->keyValues != ""){
			foreach($this->keyValues as $key => $value) {
				foreach($this->keys as $keyId => $keyColumns) {
					if($keyColumns[$column] != $key || $value == "") {
						$validKeys[$keyId] = false;
					}
				}
				$column++;
			}
		}

		if(array_search(true,$validKeys) !== false) return true;

		return false;
	}

	protected function getFetchIdQueryResult() {
		if(!isset($this->id) && $this->isInitialized()) {
			# Throw error if projectID isn't found
			if($this->project->getProjectId() == "") {
				throw new Exception("Instrument Project doesn't exist ".$this->project->getProjectName(), self::INVALID_PROJECT);
			}

			$fromClause = "";
			$whereClause = "";
			$baseSql = "SELECT d1.record AS id";
			$tableKey = 1;

			foreach ($this->keyValues as $key => $value) {
				$fromClause .= ($fromClause == "" ? "\nFROM " : ", ") . "redcap_data d" . $tableKey;
				$whereClause .= ($whereClause == "" ? "\nWHERE " : "\nAND ") .
					"d$tableKey.project_id = " . $this->project->getProjectId() . "\n" .
					($tableKey == 0 ? "" : "AND d$tableKey.record = d1.record\n") .
					"AND d$tableKey.field_name = '$key'\n" .
					"AND d$tableKey.event_id = ".$this->project->getEventId()."\n" .
					"AND d$tableKey.value = '$value'";

				$tableKey++;
			}

			$sql = $baseSql . $fromClause . $whereClause;

			if (!($result = db_query($sql))) throw new Exception("Failed to lookup record ID $sql", self::SQL_ERROR);

			return $result;
		}
		return false;
	}

	# Look up the record ID from the database. Throws an error if no record found or multiple records found
	protected function fetchId() {
		if(!isset($this->id) && $this->isInitialized()) {
			$result = $this->getFetchIdQueryResult();

			if(db_num_rows($result) > 1) throw new Exception("Multiple instrument IDs exist",self::MULTIPLE_RECORD_ERROR);

			if(db_num_rows($result) < 1) throw new Exception("Instrument ID not found",self::NO_RECORD_ERROR);

			$this->id = db_result($result, 0);
		}

		return $this->id;
	}

	# Look up the details for the linked record
	protected function fetchDetails($forceRefresh = false) {
		if(!isset($this->details) || $forceRefresh) {
			$this->fetchId();

			# Check to make sure fetching the ID didn't generate a new instrument and return if it did
			if(isset($this->details) && !$forceRefresh) return $this->details;

			$sql = "SELECT d.field_name, d.value
					FROM redcap_data d
					WHERE d.project_id = ".$this->project->getProjectId()."
						AND d.event_id = ".$this->project->getEventId()."
						AND d.record = '{$this->id}'";

			if(!($result = db_query($sql))) throw new Exception("Failed to lookup instrument details");

			//if(db_num_rows($result) == 0) throw new Exception("Instrument data missing ".$sql);

			$this->details = array();

			while($row = db_fetch_assoc($result)) {
				# For checkboxes create an array to store all the checked values, else, set details = value
				if($this->project->isCheckbox($row["field_name"])) {
					if(!isset($this->details[$row["field_name"]])) $this->details[$row["field_name"]] = array();

					$this->details[$row["field_name"]][] = $row["value"];
				}
				else {
					$this->details[$row["field_name"]] = $row["value"];
				}
			}
		}

		return $this->details;
	}

	# Stub function for handling new records on a particular project
	protected function generateNewRecord() {

	}

	# Verifies that the key values were passed in correctly
	protected function isInitialized() {
		if(isset($this->keyValues)) {
			return true;
		}

		return false;
	}

	protected function getUserRights() {
		if(!isset($this->userRights)) {
			$this->userRights = new UserRights($this->getProjectObject(),USERID);
		}

		return $this->userRights;
	}

	# Alternate constructor that passes ID in manually
	# TODO: Determine whether all sub-classes should override this function (and therefore whether we should pass keys into this parent function)
	# TODO: Or whether we should make this a generic constructor callable from all sub-classes
	public static function createRecordFromId(Project $project, $id) {
		$newRecord = new Record($project,"","");
		// $newRecord = new self($project,"","");
		$newRecord->id = $id;

		# Don't do this, added it for new records, but existing records would never be able to retrieve their
		# details if this was done. Correct record creation process for auto ID projects is to run
		# $project->getAutoId() and then create a Record using the normal Record constructor.
//		# Get first metadata field and use that to set the recordId in the details of this record
//		$newRecord->details = array($project->getFirstFieldName() => $id);

		return $newRecord;
	}

	# For the given fieldName, returns the label from the element_enum metadata that corresponds to the value stored in the record.
	# If the fieldName has no element_enum, or the stored value doesn't exist within it, the fieldName's value is returned instead.
	public function getLabelData($fieldName) {
		if($this->project->getMetadata($fieldName)->getElementType() == "yesno") {
			return ($this->getDetails($fieldName) == 1 ? "Yes" : ($this->getDetails($fieldName) === "0" || $this->getDetails($fieldName) === 0 ? "No" : ""));
		}
		$labelData = $this->project->renderEnumData($this->getDetails($fieldName),$this->project->getMetadata($fieldName)->getElementEnum());
		if ($labelData == "") {
			return $this->getDetails($fieldName);
		}
		else {
			return $labelData;
		}
	}

	# Determines whether or not the survey is complete (currently only for projects that only have one survey).
	public function isSurveyComplete() {
        $project = self::getProjectObject();
        $surveyNames = $project->getSurveyList();

        if(count($surveyNames) == 0){
            throw new Exception("There are no surveys on project {$project->getProjectId()}!" );
        }
        else if(count($surveyNames) > 1){
            throw new Exception("We do not currently support projects with more than one survey because"
                . " it is difficult to determine when the sequence of surveys is complete in those cases."
                . "  We could implement this, but we would likely have to take into account the survey queue, arms,"
                . " the 'Auto-continue to next survey' checkbox, and possibly other features that affect survey order.");
        }

        return self::isFormComplete($surveyNames[0]);
    }

    public function isFormComplete($formName) {
        return self::getDetails("{$formName}_complete") == 2;
    }

    /**
     * Function that checks if a record exists in any of the other projects (PRE, BRIDGE, POST) and returns the page and projectID in a string
     * @param $recordID, the ID of the current record
     * @param $projectRecords, array: page => projectID
     * @return string, returns the projectID and the Page
     */
    public static function recordExistsInProjects($recordID,$projectRecords){
        $projectRecord = "";
        $projectRecordPage = "";
        foreach ($projectRecords as $key=>$value){
            if(Record::recordExistsInProject($recordID,$value) != false){
                $projectRecord = $value;
                $projectRecordPage = $key;
            }
        }
        return "pid=".$projectRecord."&page=".$projectRecordPage;
    }

    /**
     * Function that checks is a record exists in a certain project and returns a boolean
     * @param $recordID, the ID of the current record
     * @param $projectId,the ID of the current project
     * @return bool, boolean that tells if the record exists in the project
     */
    public static function recordExistsInProject($recordID,$projectId){
        $recordExists = true;
        $Project = new \Plugin\Project($projectId);
        $record = new \Plugin\Record($Project, array(array($Project->getFirstFieldName())), array($Project->getFirstFieldName() => $recordID));

        try {
            $record->getId();
        } catch(Exception $e) {
            $recordExists = false;
        }

        return $recordExists;
    }
}