<?php
namespace Plugin;

use Exception;

class CustomReport
{
	const TYPE_YESNO = 'yesno';
	const TYPE_TEXT = 'text';
	const TYPE_CALC = 'calc';
	const TYPE_SELECT = 'select';
	const TYPE_RADIO = 'radio';

	function __construct($pid)
	{
		$this->pid = $pid;
		$this->project = new Project($pid);
	}

	function getHeaderLabel($fieldName)
	{
		$label = $this->getMetadata($fieldName)->getElementPrecedingHeader();
		$label = str_replace("\n", "", $label);
		$label = str_replace("</", "\n</", $label);
		$label = strip_tags($label);
		$label = str_replace("\n", "<br>", $label);
		$label = str_replace("<br><br>", "<br>", $label);
		$label = preg_replace('/<br>$/', '', $label); # Remove a trailing line break

		return $label;
	}

	function getChoiceLabel($fieldName, $recordId)
	{
		$value = $this->getValue($fieldName, array($recordId));

		if(is_null($value)){
			return 'N/A';
		}

		$choices = $this->getChoices($fieldName);
		$label = @$choices[$value];
		if(!isset($label)){
			# This means a value is stored that does not match a choice for this field.
			# This shouldn't happen, but I have seen on sample data.
			# Behave as if the question was not answered.
			return 'N/A';
		}

		return $label;
	}

	function getChoices($fieldName)
	{
		$metadata = $this->getMetadata($fieldName);
		if($metadata->getElementType() == self::TYPE_YESNO){
			return array(1=>'Yes', 0=>'No');
		}
		else{
			return $metadata->getElementEnumAsArray();
		}
	}

	function countChoices($fieldName, $recordIds)
	{
		$choices = $this->getChoices($fieldName);
		$choiceCounts = array();

		foreach($choices as $number=>$label){
			$count = count($this->getRecordsWithValue($fieldName, $number, $recordIds));
			$choiceCounts[$number] = $count;
		}

		return $choiceCounts;
	}

	function getAverage($fieldName, $recordIds = null)
	{
		$value = $this->format($this->getValue($fieldName, $recordIds, 'avg'));

		if(is_null($value)){
			return 'N/A';
		}

		if( $this->getMetadata($fieldName)->getElementNote() == 'Percentage'){
			$value .= '%';
		}

		return $value;
	}

	function format($value){
		if(is_numeric($value)){
			if($value < 1000){
				$decimalPoints = 1;
			}
			else{
				$decimalPoints = 0;
			}

			$value = number_format($value, $decimalPoints);
		}
		else if($value == 'YES'){
			return 'Yes';
		}
		else if($value == 'NO'){
			return 'No';
		}

		return $value;
	}

	function getChoicePercentages($fieldName, $recordIds)
	{
		$counts = $this->countChoices($fieldName, $recordIds);

		$total = 0;
		if(!empty($recordIds)){
			$fieldAnsweredRecordSet = new RecordSet($this->project, array(
				RecordSet::getKeyComparatorPair($fieldName, "!=") => null,
				RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(), "IN") => $recordIds
			));

			$total = count($fieldAnsweredRecordSet->getRecordIds());
		}

		$percentages = array();
		foreach($counts as $number=>$count){
			if($total == 0){
				$percentages[$number] = 'N/A';
			}
			else{
				$percentages[$number] = $this->format(@$counts[$number]/$total*100) . '%';
			}
		}

		$percentages = $this->sortChoicePercentages($fieldName, $recordIds, $percentages);

		return $percentages;
	}

	function sortChoicePercentages($fieldName, $recordIds, $percentages)
	{
		if($recordIds == $this->yearRecordIds){
			$yearPercentages = $percentages;
		}
		else{
			# This will cause some recalculation that could be prevented via caching.
			# However, the performance gain would likely not be enough to warrant implementing caching.
			$yearPercentages = $this->getChoicePercentages($fieldName, $this->yearRecordIds);
		}

		uksort($percentages, function($key1, $key2) use ($fieldName, $percentages, $yearPercentages){
			$choices = $this->getChoices($fieldName);
			$key1Label = $choices[$key1];
			$key2Label = $choices[$key2];

			# Ensure that "other" percentages always appear at the bottom.
			if($this->isOtherLabel($key1Label)){
				return 1;
			}
			else if($this->isOtherLabel($key2Label)){
				return -1;
			}

			# Use the values from all records for the year to sort similar records as well.
			$value1 = str_replace('%', '', $yearPercentages[$key1]);
			$value2 = str_replace('%', '', $yearPercentages[$key2]);

			return $value1 < $value2;
		});

		return $percentages;
	}

	function isOtherLabel($label)
	{
		return in_array(strtolower($label), array('other', 'none'));
	}

	function getChoicePercentage($fieldName, $value, $recordIds)
	{
		$percentages = $this->getChoicePercentages($fieldName, $recordIds);
		$percentage = @$percentages[$value];

		if(is_null($percentage)){
			$percentage = $this->format(0) . '%';
		}

		return $percentage;
	}

	function getRecordsWithValue($fieldName, $value, $recordIds){

		if(count($recordIds) == 0){
			return array();
		}

		$set = new RecordSet($this->project, array(
			RecordSet::getKeyComparatorPair($fieldName, "=") => $value,
			RecordSet::getKeyComparatorPair($this->project->getFirstFieldName(), "IN") => $recordIds
		));

		return $set->getRecordIds();
	}

	function getValues($fieldName, $recordIds, $aggregateFunction = null)
	{
		if(!is_array($recordIds)){
			$recordIds = array($recordIds);
		}

		if(count($recordIds) == 0){
			return array();
		}

		$fieldName = db_real_escape_string($fieldName);
		$recordIds = db_real_escape_string(implode(",", $recordIds));

		$valueSql = 'value';
		if($aggregateFunction != null){
			$aggregateFunction = db_real_escape_string($aggregateFunction);
			$valueSql = "$aggregateFunction($valueSql)";
		}

		$sql = "SELECT $valueSql as value
			FROM redcap_data d1
			WHERE d1.project_id IN ($this->pid)
			AND d1.field_name  = '$fieldName'
			AND record in ($recordIds)";

		$result = db_query($sql);

		$values = array();
		while($row = db_fetch_assoc($result)) {
			$values[] = $row['value'];
		}

		return $values;
	}

	function getValue($fieldName, $recordIds, $aggregateFunction = null)
	{
		if(!is_array($recordIds)){
			$recordIds = array($recordIds);
		}

		$values = $this->getValues($fieldName, $recordIds, $aggregateFunction);

		$count = count($values);
		if($count == 0){
			return null;
		}
		else if($count == 1){
			$value = $values[0];

			if(is_int($value)){
				$value = intval($value);
			}
			else if(is_float($value)){
				$value = floatval($value);
			}

			return $value;
		}

		throw new Exception("Expected one or zero values, but found $count values instead!");
	}

	function getLabel($fieldName)
	{
		$metadata = $this->getMetadata($fieldName);
		$label = $metadata->getElementLabel();

		return $label;
	}

	function getNote($fieldName)
	{
		return $this->getMetadata($fieldName)->getElementNote();
	}

	function isNumeric($fieldName)
	{
		$metadata = $this->getMetadata($fieldName);
		return $metadata->getElementType() == self::TYPE_CALC || in_array($metadata->getElementValidationType(), array('int', 'float'));
	}

	function getMetadata($fieldName)
	{
		$metadata = $this->project->getMetadata($fieldName);

		$type = $metadata->getElementType();
		if(empty($type)){
			throw new Exception('The specified field does not exist: ' . $fieldName);
		}

		return $metadata;
	}

	function getAnnotations($metadata)
	{
		$misc = explode("\n", $metadata->getMisc());
		$annotationDetails = array();

		foreach($misc as $annotation){
			$parts = explode('=', $annotation);
			$name = trim($parts[0]);
			$value = trim(@$parts[1]);

			if(is_null($value)){
				$value = true;
			}

			$annotationDetails[$name] = $value;
		}

		return $annotationDetails;
	}

	public function getType($field)
	{
		return $this->getMetadata($field)->getElementType();
	}
}