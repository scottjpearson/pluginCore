<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("ProjectSet","Record"),false);

# Class for looking up and editing a group of records for a given project
class RecordSet {
	# Error codes
	const SQL_ERROR = 1;
	const INVALID_PROJECT = 2;
	const MISSING_IDS_ERROR = 3;
	const KEY_COMPARATOR_SEPARATOR = "~";

	public $caseSensitive;

	protected $projects;
	protected $records;
	protected $detailsFetched = false;

	private $keyValues;

	private $currentSortFields = [];
	private $currentSortDirection = false;

	public static $debugSql = false;

	/**
	 * @param ProjectSet|Project|Array $projects ProjectSet, Project objects or array of project names/ids linking to the Redcap projects
	 * @param array $keyValues array containing the actual key values for a particular record format is array($fieldName => $fieldValue),
	 * by default, all fieldName => fieldValue pairs must appear in for the record to be included in the RecordSet. Alternatively,
	 * the getKeyComparatorPair static function can be used to add >,<,!=,IN,NOT IN functionality to the record query. Additionally,
	 * multiple fields can be searched for the needed variables by separating the field names with "|" as in field1|field2|field3
	 */
	public function __construct($projects, $keyValues, $caseSensitive = true) {
		if(get_class($projects) == "Plugin\\ProjectSet") {
			$this->projects = $projects;
		}
		else if(get_class($projects) == "Plugin\\Project") {
			$projectSet = new \Plugin\ProjectSet(array());
			$projectSet->addProjectToSet($projects);
			$this->projects = $projectSet;
		}
		else if(is_array($projects)) {
			$projectSet = new \Plugin\ProjectSet(array());

			foreach($projects as $tempProject) {
				if(is_numeric($tempProject)) {
					$newProject = Project::createProjectFromId($tempProject);
				}
				else {
					$newProject = new Project($tempProject);
				}
				$foundProject = true;

				try {
					if($newProject->getProjectId() == "") {
						$foundProject = false;
					}
				}
				catch(Exception $e) {
					$foundProject = false;
				}

				if($foundProject) {
					$projectSet->addProjectToSet($newProject);
				}
			}
			$this->projects = $projectSet;
		}
		$this->keyValues = $keyValues;

		$this->caseSensitive = $caseSensitive;
	}

	# Publicly access the record ID for the record
	/* @return \Plugin\Record[] */
	public function getRecords() {
		return $this->fetchRecords();
	}

	public function getRecordIds() {
		$ids = array();

		foreach($this->getRecords() as $record) {
			/* @var $record \Plugin\Record */
			$ids[] = $record->getId();
		}

		return $ids;
	}

	# Publicly access the project for the record
	public function getProjectObject() {
		return $this->projects;
	}

	public function getDetails($columnName = "", $getIds = false) {
		return $this->fetchDetails($columnName, $getIds);
	}

	public function addToRecordSet(Record $newRecord) {
		$this->records[] = $newRecord;
	}

	public function filterRecords($keyValues, $caseSensitive = true) {
		/* @var $potentialRecord \Plugin\Record */
		$this->fetchDetails();
		$newRecordSet = new self($this->projects,$this->keyValues);

		foreach($this->records as $potentialRecord) {
			$recordMatches = true;
			foreach ($keyValues as $key => $value) {
				$keyComparatorPair = explode(self::KEY_COMPARATOR_SEPARATOR, $key);
				$key = $keyComparatorPair[0];
				$comparator = strtolower($keyComparatorPair[1]);

				$recordValue = $potentialRecord->getDetails($key);

				if($potentialRecord->getProjectObject()->isDate($key)) {
					$recordValue = strtotime($recordValue);
					$value = strtotime($value);
				}

				if(!$caseSensitive) {
					$recordValue = strtolower($recordValue);
					$value = strtolower($value);
				}

				switch($comparator) {
					case ">":
						$thisKeyMatches = ($recordValue > $value);
						break;
					case "<":
						$thisKeyMatches = ($recordValue < $value);
						break;
					case "<=":
						$thisKeyMatches = ($recordValue <= $value);
						break;
					case ">=":
						$thisKeyMatches = ($recordValue >= $value);
						break;
					case "!=":
						$thisKeyMatches = ($recordValue != $value);
						break;
					case "like":
						$thisKeyMatches = (strpos($recordValue,$value) === false ? 0 : 1);
						break;
					case "in":
						$thisKeyMatches = in_array($recordValue,$value);
						break;
					default:
						$thisKeyMatches = ($recordValue == $value);
				}

				if(!$thisKeyMatches) {
					$recordMatches = false;
					break;
				}
			}

			if($recordMatches) {
				$newRecordSet->addToRecordSet($potentialRecord);
			}
		}

		# If nothing matches the new filters, set records equal to an empty array
		if(!$newRecordSet->records) {
			$newRecordSet->records = array();
		}

		return $newRecordSet;
	}

	/**
	 * @param $project int|string|\Plugin\Project
	 *
	 * @return \Plugin\RecordSet
	 */
	public function filterByProject($project) {
		$projectID = "";
		if ($project == ""){
			return "";
		} else if (get_class($project) == "Plugin\\Project"){
			$projectID = $project->getProjectId();
		} else if (gettype($project) == "string"){
			$projectObj = new \Plugin\Project($project);
			$projectID = $projectObj->getProjectId();
		} else if (is_numeric($project)){
			$projectID = $project;
		}

		$newRecordSet = new self($this->projects,$this->keyValues);

		foreach($this->getRecords() as $record){
			if ($record->getProjectObject()->getProjectId() == $projectID){
				$newRecordSet->addToRecordSet($record);
			}
		}

		if(!$newRecordSet->records){
			$newRecordSet->records = array();
		}

		return $newRecordSet;
	}
	
	public function sortRecords($fieldName, $reverseSort = false) {
		$recordDetails = $this->getDetails($fieldName);
		$currentRecordArray = $this->getRecords();
		$newRecordArray = array();
		
		if($reverseSort) {
			arsort($recordDetails);
		}
		else {
			asort($recordDetails);
		}
		
		foreach($recordDetails as $key => $value) {
			$newRecordArray[] = $currentRecordArray[$key];
		}
		
		$this->records = $newRecordArray;
		
		return $this->records;
	}

	public function mergeSortRecords($fieldName, $reverseSort = false) {
		$this->fetchDetailsIfNot();

		if(count($this->getRecords()) == 0) return;

		if(is_array($fieldName)) {
			$this->currentSortFields = $fieldName;
		}
		else if($fieldName != "") {
			$this->currentSortFields = [$fieldName];
		}
		else {
			$this->currentSortFields = [];
		}
		$this->currentSortDirection = $reverseSort;
		$this->records = $this->splitMerge($this->getRecords());
	}

	private function remergeArray($a,$b) {
		$merged = [];

		while(count($a) > 0 && count($b) > 0) {
			/** @var \Plugin\Record $currentA */
			$currentA = reset($a);
			/** @var \Plugin\Record $currentB */
			$currentB = reset($b);

			$useA = true;

			foreach($this->currentSortFields as $fieldName) {
				$aVal = $currentA->getDetails($fieldName);
				$bVal = $currentB->getDetails($fieldName);
				if($aVal == $bVal) {
					continue;
				}
				$useA = !$this->currentSortDirection;
				if($aVal > $bVal) {
					$useA = $this->currentSortDirection;
				}
				break;
			}

			if($useA) {
				$merged[] = array_shift($a);
			}
			else {
				$merged[] = array_shift($b);
			}
		}

		while(count($a) > 0) {
			$merged[] = array_shift($a);
		}
		while(count($b) > 0) {
			$merged[] = array_shift($b);
		}

		return $merged;
	}

	private function splitMerge($a) {
		$end = count($a);
		if($end == 1) {
			return $a;
		}
		else {
			$mid = $end / 2;
			$left = [];
			$right = [];
			for($i = 0; $i < $end; $i++) {
				if($i < $mid) {
					$left[] = $a[$i];
				}
				else {
					$right[] = $a[$i];
				}
			}
			$left = $this->splitMerge($left);
			$right = $this->splitMerge($right);

			return $this->remergeArray($left,$right);
		}
	}

	/**
	 * @param $newRecords \Plugin\RecordSet|Array
	 */
	public function appendRecords($newRecords) {
		$this->fetchRecords();

		if(is_array($newRecords)) {
			if($this->detailsFetched) {
				/** @var \Plugin\Record $tempRecord */
				foreach($newRecords as $tempRecord) {
					$tempRecord->getDetails();
				}
			}
		}
		else {
			if($this->detailsFetched) {
				$newRecords->getDetails();
			}
			$newRecords = $newRecords->getRecords();
		}

		foreach($newRecords as $tempRecord) {
			### Check if this record already exists; Append it if it doesn't
			foreach($this->getRecords() as $duplicateRecord) {
				if($tempRecord->getProjectObject()->getProjectId() == $duplicateRecord->getProjectObject()->getProjectId() &&
						$tempRecord->getId() == $duplicateRecord->getId()) {
					continue 2;
				}
			}
			$this->records[] = $tempRecord;
		}
	}

	protected function getFetchIdQueryResult() {
		if(!isset($this->records)) {
			$this->records = array();

			$fromClause = "";
			$whereClause = "";
			$baseSql = "SELECT DISTINCT d1.record, d1.project_id";
			$tableKey = 1;

			foreach ($this->keyValues as $key => $value) {
				$keyComparatorPair = explode(self::KEY_COMPARATOR_SEPARATOR,$key);
				$key = $keyComparatorPair[0];

				if(count($keyComparatorPair) == 1) {
					$comparator = "=";
				}
				else {
					$comparator = $keyComparatorPair[1];
				}

				# Check if the field is a date on any project and convert the value if it is
				foreach($this->projects->getProjects() as $tempProject) {
					if($tempProject->isDate($key)) {
						$value = date('Y-m-d',strtotime($value));
						break;
					}
				}

				$fieldNames = explode("|",$key);

				if(is_array($value)){
					$valueSql = "('".implode("','",$value)."')";
				}
				else if(is_numeric($value)){
					$valueSql = $value;
				}
				else{
					$valueSql = "'$value'";
				}

				$fromClause .= ($fromClause == "" ? "\nFROM " : ", ") . "redcap_data d" . $tableKey;
				$whereClause .= ($whereClause == "" ? "\nWHERE " : "\nAND ") .
					($tableKey == 1 ? "d$tableKey.project_id IN (" . implode(",",$this->projects->getProjectIds()) . ")\n" : "d$tableKey.project_id = d1.project_id\n") .
					($tableKey == 1 ? "" : "AND d$tableKey.record = d1.record\n") .
					"AND d$tableKey.field_name IN ('".implode("','",$fieldNames)."')\n".
					"AND ".($this->caseSensitive ? "d$tableKey.value $comparator ".$valueSql :
						"LOWER(d$tableKey.value) $comparator ".strtolower($valueSql));

				$tableKey++;
			}

			$sql = $baseSql . $fromClause . $whereClause;

			if(self::$debugSql) {
				echo "RecordSet SQL: <br />\n$sql<br />";
			}

			if (!($result = db_query($sql))) throw new Exception("Failed to lookup record IDs $sql", self::SQL_ERROR);

			return $result;
		}
		return false;
	}

	# Look up the record ID from the database. Throws an error if no record found or multiple records found
	protected function fetchRecords() {
		if(!isset($this->records) && $this->keyValues != "") {
			$result = $this->getFetchIdQueryResult();
			$this->records = array();

			while($row = db_fetch_assoc($result)) {
				$this->records[] = Record::createRecordFromId($this->projects->getProjectById($row["project_id"]),$row["record"]);
			}
		}

		return $this->records;
	}

    protected function fetchDetails($columnName = "", $getIds = false) {
        /* @var $record \Plugin\Record */

		$this->fetchDetailsIfNot();

        $returnArray = array();

        foreach($this->fetchRecords() as $record) {
            if($getIds) {
                $returnArray[$record->getProjectObject()->getProjectId()][$record->getId()] = $record->getDetails($columnName);
            }
            else {
                $returnArray[] = $record->getDetails($columnName);
            }
        }

        return $returnArray;
    }

	public function fetchDetailsIfNot() {
		if(count($this->getRecords()) == 0) return NULL;

		if(!$this->detailsFetched) {
			$metaData = [];
			$detailsArray = [];

			$whereString = "";
			foreach($this->getRecords() as $record ) {
				$whereString .= ($whereString == "" ? "" : " OR ")."(d.record = '".$record->getId()."' AND d.project_id = ".
						$record->getProjectObject()->getProjectId().")";
				if (!isset($metaData[$record->getProjectObject()->getProjectId()])) {
					foreach ($record->getProjectObject()->getMetadata() as $fieldMeta) {
						$metaData[$record->getProjectObject()->getProjectId()][$fieldMeta->getFieldName()] = $fieldMeta->getElementType();
					}
				}
			}

			$sql = "SELECT d.project_id, d.record, d.field_name, d.value
					FROM redcap_data d
					WHERE $whereString";

			if(!$result = db_query($sql)) throw new Exception("Error looking up RecordSet details". $sql,self::SQL_ERROR);

			while($row = db_fetch_assoc($result)) {
				if (isset($metaData[$row['project_id']]) && $metaData[$row['project_id']][$row['field_name']] == "checkbox") {
					$detailsArray[$row['project_id']][$row["record"]][$row["field_name"]][] = $row["value"];
				}
				else {
					$detailsArray[$row['project_id']][$row["record"]][$row["field_name"]] = $row["value"];
				}
			}

			foreach($this->fetchRecords() as $record) {
				$record->setDetails($detailsArray[$record->getProjectObject()->getProjectId()][$record->getId()]);
			}
			$this->detailsFetched = true;
		}
	}

	/**
	 * @param $keyName string
	 * @param $comparator string
	 *
	 * @return string
	 */
	public static function getKeyComparatorPair($keyName, $comparator) {
		return $keyName.self::KEY_COMPARATOR_SEPARATOR.$comparator;
	}
}