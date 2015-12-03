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
	const KEY_COMPARATOR_SEPARATOR = "~";

	public $caseSensitive;

	protected $projects;
	protected $records;
	protected $detailsFetched = false;

	private $keyValues;

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

				$recordValue = $potentialRecord->getDetails($key);

				if($potentialRecord->getProjectObject()->isDate($key)) {
					$recordValue = strtotime($recordValue);
					$value = strtotime($value);
				}

				if(!$caseSensitive) {
					$recordValue = strtolower($recordValue);
					$value = strtolower($value);
				}

				switch($keyComparatorPair[1]) {
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

				$fieldNames = explode("|",$key);

				$fromClause .= ($fromClause == "" ? "\nFROM " : ", ") . "redcap_data d" . $tableKey;
				$whereClause .= ($whereClause == "" ? "\nWHERE " : "\nAND ") .
					($tableKey == 1 ? "d$tableKey.project_id IN (" . implode(",",$this->projects->getProjectIds()) . ")\n" : "d$tableKey.project_id = d1.project_id\n") .
					($tableKey == 1 ? "" : "AND d$tableKey.record = d1.record\n") .
					"AND d$tableKey.field_name IN ('".implode("','",$fieldNames)."')\n".
					"AND ".($this->caseSensitive ? "d$tableKey.value $comparator ".(is_array($value) ? "('".implode("','",$value)."')" : "'".$value."'") :
						"LOWER(d$tableKey.value) $comparator ".strtolower(is_array($value) ? "('".implode("','",$value)."')" : "'".$value."'"));

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
		if(count($this->getRecords()) == 0) return NULL;

		$detailsArray = array();

		if(!$this->detailsFetched) {
			$whereString = "";
			foreach($this->getRecords() as $record ) {
				$whereString .= ($whereString == "" ? "" : " OR ")."(d.record = '".$record->getId()."' AND d.project_id = ".
					$record->getProjectObject()->getProjectId().")";
			}

			$sql = "SELECT d.project_id, d.record, d.field_name, d.value
					FROM redcap_data d
					WHERE $whereString";

			if(!$result = db_query($sql)) throw new Exception("Error looking up RecordSet details",self::SQL_ERROR);

			while($row = db_fetch_assoc($result)) {
				$detailsArray[$row['project_id']][$row["record"]][$row["field_name"]] = $row["value"];
			}

			foreach($this->fetchRecords() as $record) {
				$record->setDetails($detailsArray[$record->getProjectObject()->getProjectId()][$record->getId()]);
			}
			$this->detailsFetched = true;
		}
		
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