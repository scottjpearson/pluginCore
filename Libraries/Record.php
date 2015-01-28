<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

include_once("Project.php");
include_once("Core.php");

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

	private $keys;

	/**
	 * @param Project $project Project object linking to the Redcap project
	 * @param array $keys 2-dimensional array containing all the unique keys of the project ex: array(array("participant_id"),array("study_id","event"))
	 * @param array $keyValues array containing the actual key values for a particular record
	 */
	public function __construct(Project $project, $keys, $keyValues) {
		$this->project = $project;
		$this->keys = $keys;
		$this->keyValues = $keyValues;

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
	public function getDetails($columnName = "", $forceRefresh = false) {
		$this->fetchDetails($forceRefresh);

		if($columnName == "") return $this->details;

		return $this->details[$columnName];
	}

	# Make changes to the record's values. Safely inserts values and logs any changes
	# Checkboxes will need to be inserted as an array containing the new list of checked values
	public function updateDetails($changes, $skipUnchangedValues = true) {
		if(!isset($this->details)) return false;

		$insertStatements = array();
		$logText = "";
		$logSql = "";
		$logType = "";
		$updateCount = 0;

		foreach($changes as $fieldName => $value) {
			# Skip values that already match $this->details
			if($skipUnchangedValues && $this->details[$fieldName] === $value) continue;

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
			}
			else {
				$sql = "UPDATE redcap_data
						SET value = '" . db_real_escape_string($value) . "'
						WHERE project_id = {$this->project->getProjectId()}
							AND record = '{$this->id}'
							AND field_name = '" . db_real_escape_string($fieldName) . "'";

				if (!db_query($sql)) throw new Exception("Failed to update record details " . $sql, self::SQL_ERROR);

				if (db_affected_rows() == 0) {
					if ($value != "") {
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

		if($changes[$this->project->getFirstFieldName()] != "" && $updateCount == 0 && count($insertStatements) > 0) {
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

	# Stub function for handling data triggers on particular projects
	public function trigger() {

	}

	# Loops through the 2-dimensional $keys array to determine if $this->keyValues is a valid key for this project
	protected function verifyKeyValues() {
		if(count($this->keyValues) == 0) return false;

		$validKeys = array();

		for($i = 0; $i < count($this->keys); $i++) {
			$validKeys[] = true;
		}

		$column = 0;
		foreach($this->keyValues as $key => $value) {
			foreach($this->keys as $keyId => $keyColumns) {
				if($keyColumns[$column] != $key || $value == "") {
					$validKeys[$keyId] = false;
				}
			}
			$column++;
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
						AND d.record = '{$this->id}'";

			if(!($result = db_query($sql))) throw new Exception("Failed to lookup instrument details");

			if(db_num_rows($result) == 0) throw new Exception("Instrument data missing ".$sql);

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

	# Alternate constructor that passes ID in manually
	# TODO: Determine whether all sub-classes should override this function (and therefore whether we should pass keys into this parent function)
	# TODO: Or whether we should make this a generic constructor callable from all sub-classes
	public static function createRecordFromId(Project $project, $id) {
		$newRecord = new Record($project,"","");
		// $newRecord = new self($project,"","");
		$newRecord->id = $id;

		# Get first metadata field and use that to set the recordId in the details of this record
		$newRecord->details = array($project->getFirstFieldName() => $id);

		return $newRecord;
	}
}