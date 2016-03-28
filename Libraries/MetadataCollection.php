<?php
namespace Plugin;
global $Core;
$Core->Libraries("Metadata",false);

class MetadataCollection extends \ArrayObject {
	private static $cachedParses = [];
	public static $time1 = 0;
	public static $time2 = 0;
	public static $time3 = 0;
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
	public function isVisible($fieldName, $recordDetails, $exactBranchingLogic = false) {
		$startTime = microtime(true);
		/* @var $metadataRow Metadata */
		if($exactBranchingLogic === false) {
			foreach ($this as $metadataRow) {
				if ($metadataRow->getFieldName() == $fieldName) {
					$branchingLogic = $metadataRow->getBranchingLogic();
					break;
				}
			}
		}
		else {
			$branchingLogic = $exactBranchingLogic;
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

		$endTime = microtime(true);
		self::$time1 += $endTime - $startTime;
		$startTime = $endTime;

		if(!isset(self::$cachedParses[$newValue])) {
			# Create a LogicParser and use it to evaluate our branching logic
			$parser = new \LogicParser();
//			echo "Parsing $newValue<br />";
			list($logicCode) = $parser->parse($newValue);

			$endTime = microtime(true);
			self::$time2 += $endTime - $startTime;
			$startTime = $endTime;
			self::$cachedParses[$newValue] = call_user_func_array($logicCode,array());
		}



		$endTime = microtime(true);
		self::$time3 += $endTime - $startTime;
		$startTime = $endTime;
		return self::$cachedParses[$newValue];
	}
}
