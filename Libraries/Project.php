<?php
/** Author: Kyle McGuffin */
namespace Plugin;

$GLOBALS["Core"]->Libraries(array("Record","Core","MetadataCollection","Database"),false);

use \Exception;

# Class for looking up REDCap project IDs and metadata
class Project {
	# Error Codes
	const NO_RECORD_ERROR = 1;
	const MULTIPLE_RECORD_ERROR = 2;
	const SQL_ERROR = 3;
	const INSERT_ERROR = 4;

	private $projectName;
	private $projectId;
	private $eventId;
	private $dagList;
	protected $metadata;
	protected $fieldList;
	protected $formList;
	protected $recordList;
	protected $projectDetails;

	/**
	 * @param $projectName string|integer
	 * @param $eventId integer
	 */
	public function __construct($projectName,$eventId = "") {
		## Can't be a short code, so must be a project ID
		if(is_numeric($projectName)) {
			$this->projectName = "";
			$this->projectId = $projectName;
			$this->eventId = $eventId == "" ? self::getEventFromId($projectName) : $eventId;
		}
		else {
			$this->projectName = $projectName;
		}
		$this->metadata = new MetadataCollection();

		if($projectName != "") {
			$this->initializeProjectIds($eventId);
		}
		else {
			$this->projectId = "";
			$this->eventId = "";
		}
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
	public function getMetadata($fieldName = "") {
		$this->fetchMetadata();

		if($fieldName == "") return $this->metadata;

		return $this->metadata->getField($fieldName);
	}

	# Get list of fields existing on this project
	public function getFieldList($formName = "") {
		$this->fetchFieldsAndForms();

		if($formName != "") return array_keys(array_intersect($this->fieldList, array($formName)));

		return array_keys($this->fieldList);
	}

	# Get list of forms existing on this project
	public function getFormList() {
		$this->fetchFieldsAndForms();

		return $this->formList;
	}

	public function getSurveyList() {
        $projectId = db_real_escape_string(self::getProjectId());
        $formNamesSql = Database::arrayToValueListSQL(self::getFormList());

        $sql = "SELECT form_name
                FROM redcap_surveys
                WHERE
                  project_id = '$projectId'
                  AND form_name in $formNamesSql";

        $result = Database::query($sql);

        $surveyNames = array();
        while($row = db_fetch_assoc($result)){
            $surveyNames[] = $row['form_name'];
        }

        return $surveyNames;
    }

	# Check the metadata to determine if a field is a checkbox
	public function isCheckbox($fieldName) {
		$this->fetchMetadata();

		return ($this->metadata->getField($fieldName)->getElementType() == "checkbox");
	}

	# Check the metadata to determine if a field is a checkbox
	public function isDate($fieldName) {
		$this->fetchMetadata();

		return (strpos($this->metadata->getField($fieldName)->getElementValidationType(), "date_") !== false);
	}

	# Get the first field name from the metadata table
	public function getFirstFieldName() {
		$this->fetchMetadata();

		/* @var $metadataRow Metadata */
		foreach($this->metadata as $metadataRow) {
			if($metadataRow->getFieldOrder() == 1) {
				return $metadataRow->getFieldName();
			}
		}
		return false;
	}

	public function createNewAutoIdRecord() {
		# TODO should check project to see if auto-numbering is enabled first
		$recordFieldName = $this->getFirstFieldName();
		$newRecordId = $this->getAutoId();
		$newRecord = new Record($this,array(array($recordFieldName)),array($recordFieldName => $newRecordId));
		$newRecord->getDetails();

		return $newRecord;
	}

	# Lookup project metadata from the database
	protected function fetchMetadata() {
		if(count($this->metadata) == 0) {
			$this->metadata = Metadata::getItemsByProject($this);
		}

		return $this->metadata;
	}

	# Lookup field list if metadata hasn't been pulled
	protected function fetchFieldsAndForms() {
		if(count($this->fieldList) == 0) {
			$this->fieldList = array();
			$this->formList = array();

			if(count($this->metadata) == 0) {
				## Pull form and field list from redcap DB
				$sql = "SELECT field_name, form_name
						FROM redcap_metadata
						WHERE project_id = ".$this->getProjectId();

				$q = db_query($sql);

				if(!$q) echo "Error looking up fields<br />".db_error()."<br />".$sql."<br />";

				$this->fieldList = array();
				$this->formList = array();

				## Fill in fieldList and formList from query results
				while($row = db_fetch_assoc($q)) {
					$this->fieldList[$row["field_name"]] = $row["form_name"];
					if(!isset($this->formList[$row["form_name"]])) {
						$this->formList[$row["form_name"]] = count($this->formList);
					}
				}
			}
			else {
				## Fill in fieldList and formList from metadata
				foreach($this->metadata as $metadataRow) {
					$this->fieldList[$metadataRow->getFieldName()] = $metadataRow->getFormName();

					if(!isset($this->formList[$metadataRow->getFormName()])) {
						$this->formList[$metadataRow->getFormName()] = 1;
					}
				}
			}

			## Faster to do isset above (over in_array) for large arrays, so we flip the array once the array is filled
			$this->formList = array_keys($this->formList);
		}
	}

	# Look up this project's project ID if that hasn't been done already
	private final function initializeProjectIds($eventId = "") {
		if(!isset($this->projectId) && $this->projectName != "") {
			list($this->projectId, $this->eventId) = self::getProjectAndEvent($this->projectName);

			if($eventId != "") {
				$this->eventId = $eventId;
			}
		}
	}

    # Determine if a record exists on the current project
    public final function recordExists( $record_id = NULL )
    {
        if( $record_id === NULL ) return false;

        $sql = "SELECT record FROM redcap_data WHERE project_id = {$this->projectId} AND record = '{$record_id}' LIMIT 1";
        $result = db_query($sql);
        return ( $result->num_rows ) ? TRUE : FALSE;
    }

    ## Allows the user to lock a form or (forms) on the project for a given record
    public final function lockRecordForForm( $form = NULL, $record = NULL )
    {
        ## Get out of here
        if( $record === NULL ) return false;

        if(is_array($form)) {
            foreach($form as $formName)
            {
                $this->lockRecordForForm($formName, $record);
            }
        }
        else if( is_string($form) )
        {
			## Clear locking from the form ahead of time to prevent duplicates
			$sql = "DELETE FROM redcap_locking_data
					WHERE project_id = ".$this->getProjectId()."
					AND record = '".$record."'
					AND form_name = '".$form."'
					AND event_id = ".$this->getEventId();

			if(!($result = db_query($sql))) throw new Exception("Failed to unlock form\n".db_error());


            $sql = "INSERT INTO redcap_locking_data (project_id, record, event_id, form_name, timestamp)";
            $sql .= "VALUES ";
            $sql .= "('".$this->getProjectId()."'";
            $sql .= ", '".$record."'";
            $sql .= ", '".$this->getEventId()."'";
            $sql .= ", '".$form."'";
            $sql .= ", NOW()";
            $sql .= ")";

            if(!($result = db_query($sql))) throw new Exception("Failed to lock form\n".db_error());

            return true;
        }

        return false;
    }
    
    ## Determine if a form is locked for a given record
    public final function isRecordFormLocked( $form = NULL, $record = NULL )
    {
	## Get out of here
        if( $record === NULL || $form === NULL ) return false;
	
	## Is the form in the proper format (stirng)
	if( is_string( $form ) )
	{
		$sql = "SELECT record 
			FROM redcap_locking_data
			WHERE project_id = '".$this->getProjectId()."'
			AND record = '".$record."'
			AND form_name = '".$form."'
			AND event_id = '".$this->getEventId()."'";
			
		//die( $sql );	
		$results = db_query( $sql );
		
		if( db_num_rows($results) > 0 ) return true;
	}
	
	return false;
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
					WHERE project_id = {$this->projectId}
						AND record = '{$newIdDetails["record"]}'
						AND field_name = '{$newIdDetails["field_name"]}'
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

		Core::log_rc_event($this->projectId,$logSql,"redcap_data","INSERT",$newIdDetails["record"],
				"{$newIdDetails["field_name"]} = '{$newIdDetails["record"]}'","Creating Record","0","[PLUGIN]");

		// Return new auto id value
		return $newIdDetails["record"];
	}

	public final function getUniqueRecordList() {
		if(!isset($this->recordList)) {
			$sql = "SELECT DISTINCT d.record
					FROM redcap_data d
					WHERE d.project_id = {$this->getProjectId()}
					ORDER BY d.record";

			if(!($result = db_query($sql))) throw new Exception("Failed to lookup record list\n".db_error());

			$this->recordList = array();

			while($row = db_fetch_assoc($result)) {
				$this->recordList[] = $row["record"];
			}
		}

		return $this->recordList;
	}

	public function getProjectDetails() {
		if(!isset($this->projectDetails)) {
			$sql = "SELECT *
					FROM redcap_projects
					WHERE project_id = ".$this->getProjectId();

			$q = db_query($sql);
			if(!$q) echo "Error: ".db_error()."<br />\n".$sql."<br />\n";

			$this->projectDetails = db_fetch_assoc($q);
		}
		return $this->projectDetails;
	}

	public function getGroupName($groupId)
	{
		return $this->getDagList()[$groupId];
	}

	public function getDagList()
	{
		if(!isset($this->dagList)) {
			$pid = $this->projectId;
			$sql = "SELECT group_name, group_id
					FROM redcap_data_access_groups
					WHERE project_id = $pid";

			$result = db_query($sql);

			$this->dagList = [];

			while($row = db_fetch_assoc($result)) {
				$this->dagList[$row['group_id']] = $row['group_name'];
			}
		}
		return $this->dagList;
	}

	/*
	 * @return \Plugin\Project
	 * @param $projectId Int
	 */
	public final static function createProjectFromId($projectId) {
		$eventId = self::getEventFromId($projectId);

		if($eventId != "") {
			$projectObject = new \Plugin\Project(NULL);
			$projectObject->projectId = $projectId;
			$projectObject->eventId = $eventId;

			return $projectObject;
		}

		return NULL;
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

	public final static function getEventFromId($projectId) {
		$sql = "SELECT e.event_id
					FROM redcap_projects p, redcap_events_metadata e, redcap_events_arms a
					WHERE p.project_id = '$projectId'
						AND p.project_id = a.project_id
						AND e.arm_id = a.arm_id
					ORDER BY p.project_id, e.event_id";

		if ($row = db_fetch_assoc(db_query($sql))) {
			return $row["event_id"];
		}

		return NULL;
	}

	# Public function for converting enum field in metadata into raw or label values
	public final static function renderEnumData($value, $enum, $rawOrLabel = "label") {
		// make sure that the \n's are also treated as line breaks
		if (strpos($enum, "\\n")) {
			$enum = str_replace("\\n", "\n", $enum);
		}

		$select_array = explode("\n", $enum);
		$newValue = "";

		foreach ($select_array as $key => $enumValue) {
			if (strpos($enumValue, ",")) {
				$pos = strpos($enumValue, ",");
				$this_value = trim(substr($enumValue, 0, $pos));
				$this_text = trim(substr($enumValue, $pos+1));

				if ($value == $this_value || $value == $this_text) {
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
				$enumValue = trim($enumValue);

				if ($value == $enumValue) {
					$newValue = $enumValue;
					break;
				}
			}
		}

		return $newValue;
	}

	public final static function convertEnumToArray($enum, $rawOrLabelKey = "raw") {
		$enumArray = array();

		foreach(explode("\\n",$enum) as $enumRow) {
			list($enumValue) = explode(",",$enumRow);
			$enumString = trim(substr($enumRow,strlen($enumValue)+1));
			$enumValue = trim($enumValue);

			if($rawOrLabelKey == "raw") {
				$enumArray[$enumValue] = $enumString;
			}
			else if($rawOrLabelKey == "label") {
				$enumArray[$enumString] = $enumValue;
			}
		}

		return $enumArray;
	}
} 