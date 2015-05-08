<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("ProjectSet","Record"));

# Class for looking up and editing a group of records for a given project
class RecordSet {
	# Error codes
	const SQL_ERROR = 1;
	const INVALID_PROJECT = 2;
	const KEY_COMPARATOR_SEPARATOR = "~";

	protected $projects;
	protected $records;
	protected $detailsFetched = false;

	private $keyValues;

	/**
	 * @param ProjectSet|Project $projects ProjectSet or Project object linking to the Redcap projects
	 * @param array $keyValues array containing the actual key values for a particular record
	 */
	public function __construct($projects, $keyValues) {
		//debug_print_backtrace();
		//echo get_class($projects). "<br /><br /><br />";
		if(get_class($projects) == "Plugin\\ProjectSet") {
			$this->projects = $projects;
		}
		else if(get_class($projects) == "Plugin\\Project") {
			$projectSet = new \Plugin\ProjectSet(array());
			$projectSet->addProjectToSet($projects);
			$this->projects = $projectSet;
			echo "Used Project Versiom <br />";
		}
		$this->keyValues = $keyValues;
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

	public function getDetails($columnName = "") {
		return $this->fetchDetails($columnName);
	}

	public function addToRecordSet(Record $newRecord) {
		$this->records[] = $newRecord;
	}

	public function filterRecords($keyValues) {
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
			$newRecordArray[] = $currentRecordArray[$key-1];
		}
		
		$this->records = $newRecordArray;
		
		return $this->records;
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

				$fromClause .= ($fromClause == "" ? "\nFROM " : ", ") . "redcap_data d" . $tableKey;
				$whereClause .= ($whereClause == "" ? "\nWHERE " : "\nAND ") .
					"d$tableKey.project_id IN (" . implode(",",$this->projects->getProjectIds()) . ")\n" .
					($tableKey == 0 ? "" : "AND d$tableKey.record = d1.record\n") .
					"AND d$tableKey.field_name = '$key'\n" .
					"AND d$tableKey.value $comparator ".(is_array($value) ? "('".implode("','",$value)."')" : "'".$value."'");

				$tableKey++;
			}

			$sql = $baseSql . $fromClause . $whereClause;

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

	protected function fetchDetails($columnName = "") {
		/* @var $record \Plugin\Record */
		if(count($this->getRecords()) == 0) return NULL;

		$detailsArray = array();

		if(!$this->detailsFetched) {
			$whereString = "";
			foreach($this->getRecords() as $record ) {
				$whereString .= ($whereString == "" ? "" : " OR ")."(d.record = ".$record->getId()." AND d.project_id = ".
					$record->getProjectObject()->getProjectId().")";
			}

			$sql = "SELECT d.record, d.field_name, d.value
					FROM redcap_data d
					WHERE $whereString";

			if(!$result = db_query($sql)) throw new Exception("Error looking up RecordSet details",self::SQL_ERROR);

			while($row = db_fetch_assoc($result)) {
				$detailsArray[$row["record"]][$row["field_name"]] = $row["value"];
			}

			foreach($this->fetchRecords() as $record) {
				$record->setDetails($detailsArray[$record->getId()]);
			}
			$this->detailsFetched = true;
		}
		
		$returnArray = array();
		
		foreach($this->fetchRecords() as $record) {
			$returnArray[$record->getId()] = $record->getDetails($columnName);
		}
		
		return $returnArray;
	}

	public static function getKeyComparatorPair($keyName, $comparator) {
		return $keyName.self::KEY_COMPARATOR_SEPARATOR.$comparator;
	}
}