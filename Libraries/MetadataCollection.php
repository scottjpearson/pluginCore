<?php
namespace Plugin;
global $Core;
$Core->Libraries("Metadata");

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
}
