<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("ProjectSet","RecordSet"),false);
$Core->Helpers(array("getRecordsCreatedFrom"));

# Class for looking up and editing a group of records for a given project
class ReportData {
	public $tierGroupings = [];

	private $project;
	private $jsonData;

	const COMPARE_BY_FIELD = "compareBy"; // Field to be used to group sites
	const SITE_IDENTIFIER_FIELD = "identifier"; // Field to be used to identify site requesting data
	const SITE_IDENTIFIER_VALUE = "selectedGroup"; // Field value of site requesting data
	const DENOMINATOR_FIELD = "denominator"; // Field used to divide the value for each site
	const DATA_FIELDS_REQUESTED = "dataGrouping"; // List of fields being reported on, comma-separated
	const TYPE_OF_DATA = "dataSummaryType"; // AVERAGE, TOTAL, COUNT, etc
	const LEVEL_OF_DATA = "dataDepth"; // SITE_LEVEL_DATA, GROUP_LEVEL_DATA, GLOBAL_LEVEL_DATA, etc
	const DATE_RANGE = "dateRange"; //
	const DATE_FIELD = "dateField"; //
    const DATE_GROUPING_TYPE = "dateGroupingType"; // BY_YEAR, BY_MONTH, EXACT_DATES, etc

	const SELF_GET = "Self";
	const TIERED_DEMOGRAPHIC_TAG = "GROUP";

	const AVERAGE = "average";
	const TOTAL = "total";
	const COUNT = "count";

	const SITE_LEVEL_DATA = "in_group"; /** One site's data plus average of that group as grouped by COMPARE_BY  */
	const GROUP_LEVEL_DATA = "in_group_all"; /** All sites' data within the group  as grouped COMPARE_BY */
	const GLOBAL_LEVEL_DATA = "by_group"; /** Summary of site data as grouped by COMPARE_BY */

    const BY_YEAR = "by_year";
    const BY_MONTH = "by_month";
    const EXACT_DATES = "exact_dates";

	/**
	 * @param $project \Plugin\Project
	 * @param $jsonRequest string
	 */
	public function __construct($project, $jsonRequest) {
		$this->project = $project;
		$this->jsonData = json_decode($jsonRequest, true);

		# Specify default values for certain fields
		$this->jsonData[self::DATE_GROUPING_TYPE] = !isset($this->jsonData[self::DATE_GROUPING_TYPE]) ? self::BY_YEAR : $this->jsonData[self::DATE_GROUPING_TYPE];
		$this->jsonData[self::TYPE_OF_DATA] = !isset($this->jsonData[self::TYPE_OF_DATA]) ? self::AVERAGE : $this->jsonData[self::TYPE_OF_DATA];
		$this->jsonData[self::LEVEL_OF_DATA] = !isset($this->jsonData[self::LEVEL_OF_DATA]) ? self::GLOBAL_LEVEL_DATA : $this->jsonData[self::LEVEL_OF_DATA];
	}

	public function getReportData() {
		$compareField = $this->jsonData[self::COMPARE_BY_FIELD];
        $dateField = $this->jsonData[self::DATE_FIELD];
        $dateRange = $this->jsonData[self::DATE_RANGE];

		## If no data is requested, then assume the groupings is all that's wanted
        $reportFields = array_flip(explode(",",$this->jsonData[self::DATA_FIELDS_REQUESTED]));
		if(count($reportFields) == 0) {
			$reportFields = array($compareField => "0");
		}

		## If identifier fields are not well specified, switch to global data mode
		if($this->jsonData[self::SITE_IDENTIFIER_FIELD] == "" || $this->jsonData[self::SITE_IDENTIFIER_VALUE] == "") {
			$this->jsonData[self::DATE_GROUPING_TYPE] = self::GLOBAL_LEVEL_DATA;
		}

		# Get and format start and end dates based on $dateGrouping and $dateRange
		$startDate = $dateRange;
		$endDate = $dateRange;

		if($dateRange == "") {
			$startDate = 0;
			$endDate = strtotime(date("2050-01-01"));
		}
		else if(count($dateRange) > 1) {
			$startDate = strtotime($dateRange[0]);
			$endDate = strtotime($dateRange[1]);
		}

		$startDate = $this->convertDate($startDate, true);
		$endDate = $this->convertDate($endDate, false);

		# Find list of records that fit in the start and end dates
		if($this->jsonData[self::LEVEL_OF_DATA] != self::GLOBAL_LEVEL_DATA) {
			if ($dateField == "") {
				# Get all records created in start/end date range
				$recordsInDateRange = getRecordsCreatedFrom($this->project, $startDate, $endDate);
			}
			else {
				$inRangeRecordSet = new RecordSet($this->project, [RecordSet::getKeyComparatorPair($dateField, ">=") => $startDate,
						RecordSet::getKeyComparatorPair($dateField, "<=") => $endDate]);

				$recordsInDateRange = $inRangeRecordSet->getRecordIds();
			}

			$recordsInDateRange = $recordsInDateRange == "" ? [] : $recordsInDateRange;

			$deptRecords = new RecordSet($this->project, [$this->jsonData[self::SITE_IDENTIFIER_FIELD] => $this->jsonData[self::SITE_IDENTIFIER_VALUE],
					RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(), "IN") => $recordsInDateRange]);


			# If found no records, return a blank json string
			if(count($deptRecords->getRecordIds()) == 0) {
				$returnValues = array("" => array("" => array()));
                $returnArray = array();
				foreach($reportFields as $fieldName => $fieldKey) {
					$returnArray[] = array($fieldName => 0);
				}
                $returnValues[""][""] = $returnArray;
				return json_encode($returnValues);
			}
		}

		# Pull the relevant data for other groups, either within the compare by group of the site or for all groups with records in the date range
		if($dateField == "") {
			# Get all records created in start/end date range
			$allRecordsInDateRange = getRecordsCreatedFrom($this->project, $startDate, $endDate);
		}
		else {

			$inRangeRecordSet = new RecordSet($this->project, [RecordSet::getKeyComparatorPair($dateField,">=") => $startDate,
					RecordSet::getKeyComparatorPair($dateField,"<=") => $endDate]);

			$allRecordsInDateRange = $inRangeRecordSet->getRecordIds();

			$allRecordsInDateRange = $allRecordsInDateRange == "" ? [] : $allRecordsInDateRange;

		}
		$allRecords = new RecordSet($this->project, [RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(), "IN") => $allRecordsInDateRange]);
		## For in group comparisons, filter out records outside of the group
		if(in_array($this->jsonData[self::LEVEL_OF_DATA], array(self::SITE_LEVEL_DATA, self::GROUP_LEVEL_DATA))) {
			# Get converted value if if exists and parse the string to find the range to filter within
			if(isset($this->tierGroupings[$this->jsonData[self::COMPARE_BY_FIELD]])) {
				$convertedValue = $this->getConvertedRecord(reset($deptRecords->getRecords()), $this->jsonData[self::COMPARE_BY_FIELD]);
				if($valuePos = strpos($convertedValue,"< ")) {
					$valueRange[1] = substr($convertedValue,$valuePos);
					$allRecords->filterRecords([RecordSet::getKeyComparatorPair($this->jsonData[self::COMPARE_BY_FIELD],"<") => $valueRange[1]]);
				}
				else if($valuePos = strpos($convertedValue, ">= ")) {
					$valueRange[0] = substr($convertedValue,$valuePos);
					$allRecords->filterRecords([RecordSet::getKeyComparatorPair($this->jsonData[self::COMPARE_BY_FIELD],">=") => $valueRange[0]]);
				}
				else {
					$valueRange = explode(" - ",$convertedValue);
					$allRecords->filterRecords([RecordSet::getKeyComparatorPair($this->jsonData[self::COMPARE_BY_FIELD],">=") => $valueRange[0],
							RecordSet::getKeyComparatorPair($this->jsonData[self::COMPARE_BY_FIELD],"<") => $valueRange[1]]);
				}
			}
			# If no tiered groupings, just need to find all the records that match the site's record
			else {

				$allRecords = $allRecords->filterRecords([$this->jsonData[self::COMPARE_BY_FIELD] => reset($deptRecords->getRecords())->getDetails($this->jsonData[self::COMPARE_BY_FIELD])]);
			}
		}

		if(!isset($deptRecords)) {
			$deptRecords = $allRecords;
		}

		# Group the data together by year and output category(compare by group) while dividing by denominator
		if($this->jsonData[self::DATE_FIELD] == "") {
			$dateList = [];
			foreach($deptRecords->getRecords() as $record) {
				$tempDate = $record->getRecordCreationTs();
				$tempDate = substr($tempDate,0,4)."-".substr($tempDate,4,2)."-".substr($tempDate,6,2);
				$dateList[] = $this->convertDate($tempDate);
			}
		}
		else {
			$dateList = $this->convertDate($deptRecords->getDetails($this->jsonData[self::DATE_FIELD]));
        }
		$dateList = array_unique($dateList);
		asort($deptRecords->getDetails($this->jsonData[self::DATE_FIELD]));

		$recordsByDate = [];
		foreach($dateList as $reportDate) {
			$recordsByDate[$reportDate] = [];
			if($this->jsonData[self::DATE_FIELD] == "") {
				$reportingRecords = getRecordsCreatedFrom($this->project,$this->convertDate($reportDate),$this->convertDate($reportDate,false));

				$filteredRecords = $allRecords->filterRecords([RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(),"IN") => $reportingRecords]);
			}
			else {
				$filteredRecords = $allRecords->filterRecords([RecordSet::getKeyComparatorPair($this->jsonData[self::DATE_FIELD],">=") => $this->convertDate($reportDate),
						RecordSet::getKeyComparatorPair($this->jsonData[self::DATE_FIELD],"<=") => $this->convertDate($reportDate,false)]);
			}

			foreach($filteredRecords->getRecords() as $record) {
                $convertedValue = $this->getConvertedValue($record->getDetails($this->jsonData[self::COMPARE_BY_FIELD]), $this->jsonData[self::COMPARE_BY_FIELD]);


				if(!isset($recordsByDate[$reportDate][$convertedValue])) {
					foreach($reportFields as $thisField => $fieldKey) {
						$recordsByDate[$reportDate][$convertedValue][$thisField] = [];
					}
				}

				foreach($reportFields as $thisField => $fieldKey) {
					$recordsByDate[$reportDate][$convertedValue][$thisField][] = $this->getConvertedValue($record->getDetails($thisField), $thisField);
				}
			}
		}
//        print_r($convertedValue);
		# Summarize the date by year and output category
		$datedSummaries = [];
		foreach($recordsByDate as $reportDate => $groupArray) {
			foreach($groupArray as $groupName => $fieldArray) {
				foreach($fieldArray as $fieldName => $fieldValues) {
					if($this->project->getMetadata($fieldName)->getElementEnum() != "" && ($this->project->getMetadata($fieldName)->getElementType() == "radio" || $this->project->getMetadata($fieldName)->getElementType() == "yesno" ) && $this->project->getMetadata($fieldName)->getElementType() != "calc") {
                        $newValue = array();
                        $counts = array_count_values($fieldValues);
                        foreach (\Plugin\Project::convertEnumToArray($this->project->getMetadata($fieldName)->getElementEnum()) as $key => $enum) {
                            isset($counts[$key]) ? $newValue[$key] = $counts[$key] : $newValue[$key] = 0;
                        }
					}
					else if(isset($this->tierGroupings[$fieldName])) {
                        $newValue = $this->getConvertedValue($fieldValues, $fieldName);
					}
					else if($this->jsonData[self::TYPE_OF_DATA] == self::AVERAGE) {
						$newValue = array_sum($fieldValues)/count($fieldValues);
					}
					else if($this->jsonData[self::TYPE_OF_DATA] == self::TOTAL) {
						$newValue = array_sum($fieldValues);
					}
					else if($this->jsonData[self::TYPE_OF_DATA] == self::COUNT) {
						$newValue = array_count_values($fieldValues);
					}

					$datedSummaries[$reportDate][$groupName][$fieldName] = $newValue;
				}
			}
		}

		return json_encode($datedSummaries);
	}

	public function pullGroupTiersFromMetadata() {
		# Check if compare field has tier information
		$this->tierGroupings = [];

		/** @var \Plugin\Metadata $metadata */
		foreach($this->project->getMetadata() as $metadata) {
			$tiers = [];

			if(preg_match("/".self::TIERED_DEMOGRAPHIC_TAG."\\[([0-9a-zA-Z\\,]+)\\]/",$metadata->getMisc(),$tiers)) {
				$this->tierGroupings[$metadata->getFieldName()] = explode(",",$tiers[1]);
			}
		}
	}

	public function getConvertedValue($value, $fieldName) {
		if(!isset($this->tierGroupings[$fieldName])) {
			return $value;
		}

		foreach($this->tierGroupings[$fieldName] as $groupKey => $maxThreshold) {
			if($value < $maxThreshold) {
				if($groupKey == 0) {
					return "< $maxThreshold";
				}
				else {
					return $this->tierGroupings[$fieldName][$groupKey - 1]." - $maxThreshold";
				}
			}
			return ">= $maxThreshold";
		}
	}

    ## not sure if this function is even needed since its just a call to the above function and if you have the record and field name then you can just use the above.
	public function getConvertedRecord($record, $fieldName) {
		$this->getConvertedValue($record->getDetails($fieldName), $fieldName);
	}

	public function convertDate($date, $startOfRange = true) {
		if(!is_array($date)) {
			$dateArray = array($date);
		}
		else {
			$dateArray = $date;
		}
		
		foreach($dateArray as &$thisDate) {
			if ($this->jsonData[self::DATE_GROUPING_TYPE] == self::BY_MONTH || $this->jsonData[self::DATE_GROUPING_TYPE] == self::BY_YEAR) {
				$thisDate = is_numeric($thisDate) ? $thisDate : strtotime($thisDate);
			}

			if ($this->jsonData[self::DATE_GROUPING_TYPE] == self::BY_MONTH) {
				if ($startOfRange) {
					$thisDate = strtotime(date("Y-m", $thisDate) . "-1");
				} else {
					$thisDate = strtotime(date("Y", $thisDate) . "-" . (date("m", $thisDate) + 1) . "-0");
				}
			}
			else if ($this->jsonData[self::DATE_GROUPING_TYPE] == self::BY_YEAR) {
				if ($startOfRange) {
					$thisDate = strtotime(date("Y", $thisDate) . "-1-1");
				} else {
					$thisDate = strtotime((date("Y", $thisDate) + 1) . "-1-0");
				}
			}
		}

		if(!is_array($date)) {
			return $dateArray[0];
		}
		else {
			return $dateArray;
		}
	}
}