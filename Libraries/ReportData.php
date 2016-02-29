<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("ProjectSet","RecordSet"),false);

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

	const SELF_GET = "Self";
	const TIERED_DEMOGRAPHIC_TAG = "GROUP";

	const AVERAGE = "average";
	const TOTAL = "total";
	const COUNT = "count";

	const SITE_LEVEL_DATA = "in_group"; /** One site's data plus average of that group as grouped by COMPARE_BY  */
	const GROUP_LEVEL_DATA = "in_group_all"; /** All sites' data within the group  as grouped COMPARE_BY */
	const GLOBAL_LEVEL_DATA = "by_group"; /** Summary of site data as grouped by COMPARE_BY */

	/**
	 * @param $project \Plugin\Project
	 * @param $jsonRequest string
	 */
	public function __construct($project, $jsonRequest) {
		$this->project = $project;
		$this->jsonData = json_decode($jsonRequest, true);
	}

	public function getReportData() {

		$compareField = $this->jsonData[self::COMPARE_BY];
		$denominatorField = $this->jsonData[self::DENOMINATOR_FIELD];
		$dataToDisplay = $this->jsonData[self::DATA_GROUPING];
		$specificField = $this->jsonData[self::DATA_ROW];
		$typeOfData = isset($this->jsonData[self::TYPE_OF_DATA]) ? $this->jsonData[self::TYPE_OF_DATA] : "average";
		$levelOfData = isset($this->jsonData[self::LEVEL_OF_DATA]) ? $this->jsonData[self::LEVEL_OF_DATA] : "byGroup";

		## If no data is requested, then assume the groupings is all that's wanted
		if($specificField == "") {
			$reportFields = array_flip(explode(",",$dataToDisplay));
		}
		else {
			$reportFields = [$specificField => 1];
		}

		## Find the identifier in the JSON data
		$identifier = $this->jsonData[self::GROUP_ID];

		if($identifier == "") {
			die("Please select a group to view the report");
		}

		## TODO Pass __GROUPID__ in for identifier for test query
		$deptRecords = new RecordSet($this->project, [$this->jsonData[self::IDENTIFIER_FIELD] => $identifier]);

		if(count($deptRecords->getRecordIds()) == 0) die("Invalid DAG");

		var_dump($deptRecords->getRecordIds());

		echo "\n\n";

		$dateValues = array_unique($deptRecords->getDetails(self::DATE_FIELD));
		sort($dateValues);

		$filterArray = [];

		if($year != "") {
			$dateValues = [$year];

			$filterArray[self::DATE_FIELD] = $year;

			$deptRecords = $deptRecords->filterRecords([self::DATE_FIELD => $year]);

			$recordsByDate = [$year => new RecordSet($this->project, $filterArray)];
		}
		else {
			$recordsByDate = [];

			foreach($dateValues as $year) {
				$finalFilter = $filterArray;
				$finalFilter[self::DATE_FIELD] = $year;

				## Add filter to only include records in the same $compareField as the user's dept
				if($levelOfData == "inGroup") {
					$thisYearsRecord = reset($deptRecords->filterRecords([self::DATE_FIELD => $year])->getRecords());

					if($this->tierGroupings == "") {
						$finalFilter[$compareField] = $thisYearsRecord->getDetails($compareField);
					}
					else {
						foreach($this->tierGroupings as $groupKey => $maxThreshold) {
							if($groupKey == (count($this->tierGroupings) - 1)) {
								$finalFilter[RecordSet::getKeyComparatorPair($compareField, ">=")] = $maxThreshold;
								break;
							}
							if($thisYearsRecord->getDetails($compareField) < $this->tierGroupings[$groupKey + 1]) {
								$finalFilter[RecordSet::getKeyComparatorPair($compareField, ">=")] = $maxThreshold;
								$finalFilter[RecordSet::getKeyComparatorPair($compareField, "<")] = $this->tierGroupings[$groupKey + 1];
								break;
							}
						}
					}
				}

				var_dump($finalFilter);echo "\n\n";
				$recordsByDate[$year] = new RecordSet($this->project, $finalFilter);
				$recordsByDate[$year]->getDetails();
			}
		}

		## Currently have $deptRecords with all the user's department's information that is relevant to this search
		## Also have $recordsByDate for every valid date for the query
		$annualSummaries = [];
		//echo "Report Fields: \n";
		//var_dump($reportFields);echo "\n\n";
		//var_dump($recordsByDate[$year]->getRecordIds());echo "\n\n";

		if($levelOfData == "byGroup") {
			## Need to summarize data for each group (using $typeOfData)
			foreach($dateValues as $year) {
				/** @var RecordSet $yearlyData */
				$yearlyData = $recordsByDate[$year];

				echo "Year: $year\n\n";
				var_dump($yearlyData->getRecordIds());echo "\n\n";

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
						$annualSummaries[$year][$groupName][$field] = [];

						foreach($groupData->getRecords() as $newRecord) {
							$annualSummaries[$year][$groupName][$field][] = $newRecord->getDetails($field);
						}
					}
				}
			}
		}
		else if($levelOfData == "inGroup") {
			## TODO: Do we display a summary of the non-user hospitals or each of the non-user hospitals?
			foreach($dateValues as $year) {
				$thisYearsRecord = reset($deptRecords->filterRecords([self::DATE_FIELD => $year])->getRecords());

				foreach($reportFields as $field => $number) {
					$annualSummaries[$year][self::SELF_GET][$field] = $thisYearsRecord->getDetails($field);
				}

				foreach($reportFields as $field => $number) {
					$annualSummaries[$year]["Similar EDs"][$field] = [];
				}

				foreach($reportFields as $field => $number) {
					foreach($recordsByDate[$year]->getRecords() as $newRecord) {
						if($newRecord->getId() == $thisYearsRecord->getId()) { continue; }

						$annualSummaries[$year]["Similar EDs"][$field][] = $newRecord->getDetails($field);
					}
				}
			}
		}

		var_dump($annualSummaries);echo "<br /><br />\n\n";

		# Go through the data and make the summary information
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
					else if ($typeOfData == "average") {
						$annualSummaries[$year][$groupName][$field] = array_sum($fieldData) / count($fieldData);
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
}