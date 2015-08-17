<?php
namespace Plugin;
global $Core;
$Core->Libraries("Metadata",false);

class MetadataCollection extends \ArrayObject {

	/*
	 * @return Metadata
	 * @param $fieldName string
	 */
	public function getField($fieldName) {
		/* @var $metadataRow Metadata */
		foreach($this as $metadataRow) {
			if($metadataRow->getFieldName() == $fieldName) {
				return $metadataRow;
			}
		}
		return new Metadata();
	}

	/*
	 * @return bool
	 * @param $fieldName string
	 * @param $recordDetails array
	 */
	public function isVisible($fieldName, $recordDetails) {
		/* @var $metadataRow Metadata */
		foreach($this as $metadataRow) {
			if($metadataRow->getFieldName() == $fieldName) {
				$branchingLogic = $metadataRow->getBranchingLogic();
				break;
			}
		}

		if($branchingLogic == "") {
			return true;
		}
		
		$newValue = preg_replace_callback("/(\\[)([a-z][a-z|_|0-9]*?)(\\])/",function($matches) use($recordDetails) {
			return ($recordDetails[$matches[2]] == "" ? "''" : $recordDetails[$matches[2]]);
		},$branchingLogic);
		//$newValue = str_replace(" = "," == ",$newValue);
		$newValue = str_replace(" or ",") || (",$newValue);
		$newValue = str_replace(" and ",") && (",$newValue);
		$newValue = "(".$newValue.")";

		# Create a LogicParser and use it to evaluate our branching logic
		$parser = new \LogicParser();
		list($logicCode) = $parser->parse($newValue);
		$logicCheckResult = call_user_func_array($logicCode,array());

		return $logicCheckResult;
	}
}
