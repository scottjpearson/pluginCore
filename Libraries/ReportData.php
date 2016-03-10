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
	}

	public function getReportData() {
		$compareField = $this->jsonData[self::COMPARE_BY_FIELD];
		$denominatorField = $this->jsonData[self::DENOMINATOR_FIELD];
		$dataToDisplay = $this->jsonData[self::DATA_FIELDS_REQUESTED];
        $dateField = $this->jsonData[self::DATE_FIELD];
		$typeOfData = isset($this->jsonData[self::TYPE_OF_DATA]) ? $this->jsonData[self::TYPE_OF_DATA] : "average";
		$levelOfData = isset($this->jsonData[self::LEVEL_OF_DATA]) ? $this->jsonData[self::LEVEL_OF_DATA] : "by_group";
        $dateGrouping = isset($this->jsonData[self::DATE_GROUPING_TYPE]) ? $this->jsonData[self::DATE_GROUPING_TYPE] : "by_year";
        $dateRange = $this->jsonData[self::DATE_RANGE];

		## If no data is requested, then assume the groupings is all that's wanted
        $reportFields = array_flip(explode(",",$dataToDisplay));
		if(count($reportFields) == 0) {
			$reportFields = array($compareField => "0");
		}

		## Find the identifier in the JSON data
		$identifier = $this->jsonData[self::SITE_IDENTIFIER_VALUE];
		$identifierField = $this->jsonData[self::SITE_IDENTIFIER_FIELD];

		if($identifier == "") {
			die("Please select a group to view the report");
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

		if($dateGrouping == self::BY_MONTH || $dateGrouping == self::BY_YEAR) {
			$startDate = is_numeric($startDate) ? $startDate : strtotime($startDate);
			$endDate = is_numeric($startDate) ? $startDate : strtotime($endDate);
		}

		if($dateGrouping == self::BY_MONTH) {
			$startDate = strtotime(date("Y-m-",$startDate)."1");
			$endDate = strtotime(date("Y-",$startDate).(date("m",$startDate) + 1)."-0");
		}
		else if($dateGrouping == self::BY_YEAR) {
			$startDate = strtotime(date("Y-",$startDate)."01-01");
			$endDate = strtotime((date("Y",$startDate) + 1)."-01-00");
		}

		# Find list of records that fit in the start and end dates
		$recordsInDateRange = array();
		if($dateField == "") {
			# This means we're going to use the "INSERT" log for each record to determine date range
			$sql = "SELECT d.pk, d.max_ts
					FROM (SELECT l.pk, MAX(l.ts) as max_ts
						FROM redcap_log_event l
						WHERE l.project_id = ".$this->project->getProjectId()."
							AND l.ts >= ".date("Ymd", $startDate)."000000
							AND l.event = 'INSERT'
							AND l.object_type = 'redcap_data'
						GROUP BY l.pk) AS d
					WHERE d.max_ts >= ".date("Ymd", $startDate)."000000
						AND d.max_ts < ".date("Ymd", $endDate)."999999";

			$q = db_query($sql);
			while($row = db_fetch_assoc($q)) {
				$recordsInDateRange[] = $row["pk"];
			}
		}
		else {
			$inRangeRecordSet = new RecordSet($this->project, [RecordSet::getKeyComparatorPair($dateField,">=") => $startDate,
					RecordSet::getKeyComparatorPair($dateField,"<=") => $endDate]);

			$recordsInDateRange = $inRangeRecordSet->getRecordIds();
		}

		$deptRecords = new RecordSet($this->project, [$identifierField => $identifier, RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(),"IN") => $recordsInDateRange]);

		# If found no records, return a blank json string
		if(count($deptRecords->getRecordIds()) == 0) {
			$returnValues = array("" => array());
			foreach($reportFields as $fieldName => $fieldKey) {
				$returnValues[""][] = array($fieldName => 0);
			}
			return json_encode($returnValues);
		}

		# Pull the relevant data for other groups, either within the compare by group of the site or for all groups with records in the date range
## TODO, start back here
		$allRecords = new RecordSet($this->project, [RecordSet::getKeyComparatorPair($dateField,">=") => $startDate,
			 	RecordSet::getKeyComparatorPair($dateField,"<=") => $endDate,
				])

		# Group the data together by year and output category(compare by group) while dividing by denominator


		# Summarize the date by year and output category

        $dateValues = array();
        $dates = array();
        if ($dateRange != ""){
            $dateArray = array();
            foreach($deptRecords->getRecords() as $record){
                $filteredDate = "";
                if ($record->getDetails($dateField) == "") {
                    continue;
                }

                if($dateGrouping == 'by_month'){
                    $explodedDate = explode("-", $record->getDetails($dateField));

                    $filteredDate = $explodedDate[1]."-".$explodedDate[0];
                }
                else if ($dateGrouping == 'exact_dates'){
                    $filteredDate = $record->getDetails($dateField);
                }
                else {
                    $explodedDate = explode("-", $record->getDetails($dateField));
                    $filteredDate = $explodedDate[0];
                }
                $dateArray[] = $filteredDate;
            }


            if($dateGrouping == 'by_month'){
                $convertedDate = strtotime($dateRange);
                $start = date('Y-m-d', strtotime(date('Y',$convertedDate)."-".date("m",$convertedDate)."-01"));
                $end = date('Y-m-d', strtotime(date('Y',$convertedDate)."-".(date("m",$convertedDate)+1)."-00"));
                $dates = array('start' => $start, 'end' => $end);
            }
            else if ($dateGrouping == 'exact_dates'){
                $start = $dateRange;
                $end = $dateRange;
                $dates = array('start' => $start, 'end' => $end);
            }
            else {
                $convertedDate = strtotime($dateRange);
                $start = strtotime("1/1/".date("Y",$convertedDate));
                $end = strtotime("12/31/".date("Y",$convertedDate));
                $dates = array('start' => $start, 'end' => $end);
            }



            $dateValues = array_unique($dateArray);
            sort($dateValues);

        } else if (is_array($dateRange)){
            ## filter between 2 dates
            $dateValues = array_unique($dateArray);
            sort($dateValues);
        } else {
            $dateValues = array_unique($deptRecords->getDetails($dateField));
            sort($dateValues);
        }

//        var_dump($dateValues);

		$filterArray = [];

//		if($year != "") {
//			$dateValues = [$year];
//
//			$filterArray[self::DATE_FIELD] = $year;
//
//			$deptRecords = $deptRecords->filterRecords([self::DATE_FIELD => $year]);
//
//			$recordsByDate = [$year => new RecordSet($this->project, $filterArray)];
//		}
//        else if ($month != ""){
//
//        }
//		else {
        $recordsByDate = [];

        foreach($dateValues as $date) {
            $finalFilter = $filterArray;
            if ($dateGrouping == 'exact_dates'){
                $finalFilter[$dateField] = $date;
            }
            else {
                $finalFilter = array(\Plugin\RecordSet::getKeyComparatorPair($dateField, 'LIKE') => '%' . $date . '%');
            }
//            var_dump($finalFilter);

            ## Add filter to only include records in the same $compareField as the user's dept
            if($levelOfData == "inGroup") {
                $thisYearsRecord = reset($deptRecords->filterRecords([\Plugin\RecordSet::getKeyComparatorPair($dateField ,'>=') => $dates['start'], \Plugin\RecordSet::getKeyComparatorPair($dateField ,'<=') => $dates['end']])->getRecords());

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

            //var_dump($finalFilter);echo "\n\n";
            $recordsByDate[$date] = new RecordSet($this->project, $finalFilter);
            $recordsByDate[$date]->getDetails();
//            var_dump($recordsByDate);
        }
//		}

		## Currently have $deptRecords with all the user's department's information that is relevant to this search
		## Also have $recordsByDate for every valid date for the query
		$annualSummaries = [];
		//echo "Report Fields: \n";
		//var_dump($reportFields);echo "\n\n";
		//var_dump($recordsByDate);echo "\n\n";

		if($levelOfData == "by_group") {
			## Need to summarize data for each group (using $typeOfData)
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
		else if($levelOfData == "inGroup") {
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
					else if ($typeOfData == "average") {
						$annualSummaries[$year][$groupName][$field] = array_sum($fieldData) / count($fieldData);
					}
                    else if ($typeOfData == "total") {
                        $annualSummaries[$year][$groupName][$field] = array_sum($fieldData);
                    }
                    else if ($typeOfData == "count") {
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
}