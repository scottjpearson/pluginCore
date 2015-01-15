<?php
/** Author: Kyle McGuffin */

# Class for looking up REDCap project IDs and metadata
class Core_Project {
	# Error Codes
	const NO_RECORD_ERROR = 1;
	const MULTIPLE_RECORD_ERROR = 2;
	const SQL_ERROR = 3;
	const INSERT_ERROR = 4;

	private $projectName;
	private $projectId;
	private $eventId;
	protected $metadata;
	protected $recordList;

	public function __construct($projectName) {
		$this->projectName = $projectName;

		$this->initializeProjectIds();
	}

	# Publicly access the project name
	public function getProjectName() {
		return $this->projectName;
	}

	# Publicly access the project ID
	public function getProjectId() {
		return $this->projectId;
	}

	# Publicly access the event ID
	public function getEventId() {
		return $this->eventId;
	}

	# Publicly access the project metadata
	public function getMetadata($columnName = "") {
		$this->fetchMetadata();

		if($columnName == "") return $this->metadata;

		return $this->metadata[$columnName];
	}

	# Check the metadata to determine if a field is a checkbox
	public function isCheckbox($columnName) {
		$this->fetchMetadata();

		return ($this->metadata[$columnName]["element_type"] == "checkbox");
	}

	# Get first timestamp from the instrumentProjects array
	public function getFirstTimestampName() {
		return self::$instrumentProjects[$this->projectName][0];
	}

	# Get last timestamp from the instrumentProjects array
	public function getLastTimestampName() {
		return self::$instrumentProjects[$this->projectName][1];
	}

	# Lookup project metadata from the database
	protected function fetchMetadata() {
		if(!isset($this->metadata)) {
			$sql = "SELECT m.*
					FROM redcap_metadata m
					WHERE m.project_id = {$this->projectId}
					ORDER BY m.field_order";

			if(!($result = db_query($sql))) throw new Exception("Failed to lookup metadata");

			$this->metadata = array();

			while($row = db_fetch_assoc($result)) {
				$this->metadata[$row["field_name"]] = $row;
			}
		}

		return $this->metadata;
	}

	# Look up the instrument table's project ID if that hasn't been done already
	private final function initializeProjectIds() {
		if(!isset($this->projectId) && $this->projectName != "") {
			list($this->projectId, $this->eventId) = self::getProjectAndEvent($this->projectName);
		}
	}

	# Pull the next auto ID for the project and save it
	public final function getAutoId() {

		@db_query("BEGIN");
		### Get a new Auto ID for the given project ###
		$sql = "SELECT DISTINCT d.record, m.field_name
				FROM redcap_metadata m
				LEFT JOIN redcap_data d
				ON (m.project_id = d.project_id
					AND m.field_name = d.field_name)
				WHERE m.project_id = ".$this->projectId."
					AND m.field_order = 1
				ORDER BY abs(record) DESC
				LIMIT 1";

		$newIdDetails = db_fetch_assoc(db_query($sql),0);
		if ($newIdDetails["record"] == "") $newIdDetails["record"] = 0;
		$newIdDetails["record"]++;

		$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
				({$this->projectId},{$this->eventId},'{$newIdDetails["record"]}','{$newIdDetails["field_name"]}','{$newIdDetails["record"]}')";

		@db_query("COMMIT");

		if(!db_query($sql)) throw new Exception("Error Inserting Auto ID ".$sql,self::SQL_ERROR);
		$logSql = $sql;

		# Verify the new auto ID hasn't been duplicated
		$sql = "SELECT d.field_name
				FROM redcap_data d
				WHERE d.project_id = {$this->projectId}
					AND d.record = '{$newIdDetails["record"]}'";

		$result = db_query($sql);

		while(db_num_rows($result) > 1) {
			# Delete, increment by a random integer and attempt to re-create the record
			$sql = "DELETE FROM redcap_data
					WHERE d.project_id = {$this->projectId}
						AND d.record = '{$newIdDetails["record"]}
						AND d.field_name = '{$newIdDetails["field_name"]}'
					LIMIT 1";

			if(!db_query($sql)) throw new Exception("Error Deleting Duplicate Auto ID ".$sql,self::SQL_ERROR);

			$newIdDetails["record"] += rand(1,10);

			@db_query("BEGIN");
			$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
					({$this->projectId},{$this->eventId},'{$newIdDetails["record"]}','{$newIdDetails["field_name"]}','{$newIdDetails["record"]}')";

			if(!db_query($sql)) throw new Exception("Error Inserting Auto ID ".$sql,self::SQL_ERROR);
			$logSql = $sql;

			@db_query("COMMIT");

			$sql = "SELECT d.field_name
					FROM redcap_data d
					WHERE d.project_id = {$this->projectId}
						AND d.record = '{$newIdDetails["record"]}'";

			$result = db_query($sql);
		}

		Core_Core::log_rc_event($this->projectId,$logSql,"redcap_data","INSERT",$newIdDetails["record"],
				"{$newIdDetails["field_name"]} = '{$newIdDetails["record"]}'","Creating Record","0","[PLUGIN]");

		// Return new auto id value
		return $newIdDetails["record"];
	}

	public final function getUniqueRecordList() {
		if(!isset($this->recordList)) {
			$sql = "SELECT DISTINCT d.record
					FROM redcap_data d
					WHERE d.project_id = {$this->projectId}
					ORDER BY d.record";

			if(!($result = db_query($sql))) throw new Exception("Failed to lookup record list\n".db_error());

			$this->recordList = array();

			while($row = db_fetch_assoc($result)) {
				$this->recordList[] = $row["record"];
			}
		}

		return $this->recordList;
	}

	# Public function for fetching project ID and event ID from the database for a given project short code
	public final static function getProjectAndEvent($projectShortCode) {
		while(strlen($projectShortCode) > 0) {
			$projectShortCode = strtolower($projectShortCode);

			$sql = "SELECT p.project_id, e.event_id
					FROM redcap_projects p, redcap_events_metadata e, redcap_events_arms a
					WHERE LOWER(p.app_title) LIKE '%($projectShortCode)'
						AND p.project_id = a.project_id
						AND e.arm_id = a.arm_id
					ORDER BY p.project_id, e.event_id";

			if ($row = db_fetch_assoc(db_query($sql))) {
				return array($row["project_id"], $row["event_id"]);
			}
			$projectShortCode = substr($projectShortCode, (strpos($projectShortCode, "_") ?: strlen($projectShortCode)) + 1);
		}

		return array(NULL, NULL);
	}

	# Public function for getting the operational project array
	public final static function getOperationalProjects() {
		return self::$operationalProjects;
	}

	# Public function for getting the instrument project array
	public final static function getInstrumentProjects() {
		return self::$instrumentProjects;
	}

	# Public function for getting the cron project array
	public final static function getCronProjects() {
		return self::$cronProjects;
	}

	# Public function for getting a list of all NCS projects
	public final static function getProjectList() {
		# merge all above arrays into one massive array
		return array_merge(self::$operationalProjects, array_keys(self::$instrumentProjects), self::$cronProjects);
	}

	# Public function for converting enum field in metadata into raw or label values
	public final static function renderEnumData($data, $enum, $rawOrLabel = "label") {
		// make sure that the \n's are also treated as line breaks
		if (strpos($enum, "\\n")) {
			$enum = str_replace("\\n", "\n", $enum);
		}

		$select_array = explode("\n", $enum);
		$newValue = "";

		foreach ($select_array as $key => $value) {
			if (strpos($value, ",")) {
				$pos = strpos($value, ",");
				$this_value = trim(substr($value, 0, $pos));
				$this_text = trim(substr($value, $pos+1));

				if ($data == $this_value || $data == $this_text) {
					if ($rawOrLabel == 'raw')
						$newValue = $this_value;
					else if ($rawOrLabel == 'label')
						$newValue = $this_text;
					else
						$newValue = "$this_value,$this_text";
					break;
				}
			}
			else {
				$value = trim($value);

				if ($data == $value) {
					$newValue = $value;
					break;
				}
			}
		}

		return $newValue;
	}

	public final static function convertEnumToArray($enum, $rawOrLabelKey = "raw") {
		$enumArray = array();

		foreach(explode("\\n",$enum) as $enumRow) {
			list($enumValue, $enumString) = explode(",",$enumRow);
			$enumValue = trim($enumValue);
			$enumString = trim($enumString);

			if($rawOrLabelKey == "raw") {
				$enumArray[$enumValue] = $enumString;
			}
			else if($rawOrLabelKey == "label") {
				$enumArray[$enumString] = $enumValue;
			}
		}

		return $enumArray;
	}

	# Get first timestamp from the instrumentProjects array
	public static function getFirstTimestampByProject($projectName) {
		return self::$instrumentProjects[$projectName][0];
	}

	# Get last timestamp from the instrumentProjects array
	public static function getLastTimestampByProject($projectName) {
		return self::$instrumentProjects[$projectName][1];
	}

} 