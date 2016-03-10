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
				$returnValues = array("" => array());
				foreach($reportFields as $fieldName => $fieldKey) {
					$returnValues[""][] = array($fieldName => 0);
				}
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
		## TODO Need to correct this if the COMPARE_BY_FIELD is also a tiered field
		if(in_array($this->jsonData[self::LEVEL_OF_DATA], array(self::SITE_LEVEL_DATA, self::GROUP_LEVEL_DATA))) {
			# Get converted value if if exists and parse the string to find the range to filter within
			if(isset($this->tierGroupings[$this->jsonData[self::COMPARE_BY_FIELD]])) {
				$convertedValue = reset($deptRecords->getRecords())->getDetails($this->jsonData[self::COMPARE_BY_FIELD]);
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
		sort($dateList);

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
				$convertedValue = $this->getConvertedValue($record, $this->jsonData[self::COMPARE_BY_FIELD]);

				if(!isset($recordsByDate[$reportDate][$convertedValue])) {
					foreach($reportFields as $thisField) {
						$recordsByDate[$reportDate][$convertedValue][$thisField] = [];
					}
				}

				foreach($reportFields as $thisField) {
					$recordsByDate[$reportDate][$convertedValue][$thisField][] = $this->getConvertedValue($record, $thisField);
				}
			}
		}
		var_dump($recordsByDate);
die("");
		# Summarize the date by year and output category


		## Currently have $deptRecords with all the user's department's information that is relevant to this search
		## Also have $recordsByDate for every valid date for the query
		$annualSummaries = [];
		//echo "Report Fields: \n";
		//var_dump($reportFields);echo "\n\n";
		//var_dump($recordsByDate);echo "\n\n";

		if($this->jsonData[self::LEVEL_OF_DATA] == "by_group") {
			## Need to summarize data for each group (using $this->jsonData[self::TYPE_OF_DATA])
			foreach($dateValues as $date) {
				/** @var RecordSet $yearlyData */
				$yearlyData = $recordsByDate[$date];

//				echo "Year: $year\n\n";
//				var_dump($yearlyData->getRecordIds());echo "\n\n";

				if($this->tierGroupings == "") {
					$groupList = array_unique($yearlyData->getDetails($compareField, false));
				}
				else {
					$groupList = array_merge([0],$this->tierGroupings);
				}

				foreach($groupList as $groupKey => $groupName) {
					if($this->tierGroupings == "") {
						$groupData = $yearlyData->filterRecords([$compareField => $groupName]);
					}
					else {
						if($groupKey == (count($groupList) - 1)) {
							$groupData = $yearlyData->filterRecords([RecordSet::getKeyComparatorPair($compareField,">=") => $groupName]);
						}
						else {
							$groupData = $yearlyData->filterRecords([RecordSet::getKeyComparatorPair($compareField,">=") => $groupName,
																	RecordSet::getKeyComparatorPair($compareField,"<") => $groupList[$groupKey + 1]]);
						}
					}

					if($this->tierGroupings != "") {
						$groupName = ($groupKey < (count($groupList) - 1) ? $groupName . " - " . $groupList[$groupKey + 1] : ">" . $groupName);
					}
					else if($this->project->getMetadata($compareField)->getElementEnum() != "") {
						$enum = Project::convertEnumToArray($this->project->getMetadata($compareField)->getElementEnum());

						$groupName = $enum[$groupName];
					}

					foreach($reportFields as $field => $number) {
						$annualSummaries[$date][$groupName][$field] = [];

						foreach($groupData->getRecords() as $newRecord) {
							$annualSummaries[$date][$groupName][$field][] = $newRecord->getDetails($field);
						}
					}
				}
			}
		}
		else if($this->jsonData[self::LEVEL_OF_DATA] == "inGroup") {
			## TODO: Do we display a summary of the non-user hospitals or each of the non-user hospitals?
			foreach($dateValues as $date) {
				$thisYearsRecord = reset($deptRecords->filterRecords([$dateField => $date])->getRecords());

				foreach($reportFields as $field => $number) {
					$annualSummaries[$date][self::SELF_GET][$field] = $thisYearsRecord->getDetails($field);
				}

				foreach($reportFields as $field => $number) {
					$annualSummaries[$date]["Similar EDs"][$field] = [];
				}

				foreach($reportFields as $field => $number) {
					foreach($recordsByDate[$date]->getRecords() as $newRecord) {
						if($newRecord->getId() == $thisYearsRecord->getId()) { continue; }

						$annualSummaries[$date]["Similar EDs"][$field][] = $newRecord->getDetails($field);
					}
				}
			}
		}

//		var_dump($annualSummaries);echo "<br /><br />\n\n";

		# Go through the data and make the summary information TODO: count and total data
		foreach($annualSummaries as $year => $yearData) {
			foreach($yearData as $groupName => $groupData) {
				if($groupName == self::SELF_GET) continue;

				foreach ($groupData as $field => $fieldData) {
					if($this->project->getMetadata($field)->getElementEnum() != "") {
						$enum = Project::convertEnumToArray($this->project->getMetadata($field)->getElementEnum());
						$counts = array_count_values($fieldData);
						$combinedData = [];

						foreach($counts as $value => $count) {
							$combinedData[] = round($count / count($fieldData) * 100)."% {$enum[$value]}";
						}

						$annualSummaries[$year][$groupName][$field] = implode("\n",$combinedData);
					}
					else if ($this->jsonData[self::TYPE_OF_DATA] == "average") {
						$annualSummaries[$year][$groupName][$field] = array_sum($fieldData) / count($fieldData);
					}
                    else if ($this->jsonData[self::TYPE_OF_DATA] == "total") {
                        $annualSummaries[$year][$groupName][$field] = array_sum($fieldData);
                    }
                    else if ($this->jsonData[self::TYPE_OF_DATA] == "count") {
                        $annualSummaries[$year][$groupName][$field] = count($fieldData);
                    }
				}
			}
		}

		return json_encode($annualSummaries);
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

	public function getConvertedValue($record, $fieldName) {
		if(!isset($this->tierGroupings[$fieldName])) {
			return $record->getDetails($fieldName);
		}

		foreach($this->tierGroupings[$fieldName] as $groupKey => $maxThreshold) {
			if($record->getDetails($fieldName) < $maxThreshold) {
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