<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 9/8/2015
 * Time: 12:48 PM
 */

namespace Plugin;

global $Core;
$Core->Libraries(array("Project"));

class DeployProject extends Project {

	/**
	 * @param $newFields\ array of Metadata
	 */
	public function addFields($newFields) {

		/** @var $fieldMetadata \Plugin\Metadata */
		foreach($newFields as $fieldMetadata) {
			if($fieldMetadata->getFieldName() == "") continue;

			$currentMetadata = $this->getMetadata($fieldMetadata->getFieldName());

			$newMetadataUpdate = array();

			if($currentMetadata->getElementEnum() != $fieldMetadata->getElementEnum()) $newMetadataUpdate["element_enum"] = "'".$fieldMetadata->getElementEnum()."'";
			if($currentMetadata->getElementType() != $fieldMetadata->getElementType()) $newMetadataUpdate["element_type"] = "'".$fieldMetadata->getElementType()."'";
			if($currentMetadata->getElementLabel() != $fieldMetadata->getElementLabel()) $newMetadataUpdate["element_label"] = "'".$fieldMetadata->getElementLabel()."'";
			if($currentMetadata->getElementValidationType() != $fieldMetadata->getElementValidationType()) $newMetadataUpdate["element_validation_type"] = "'".$fieldMetadata->getElementValidationType()."'";

			if($currentMetadata->getFormName() != $fieldMetadata->getFormName()) {
				$newMetadataUpdate["form_name"] = "'".$fieldMetadata->getFormName()."'";

				## If field exists and is moving to new form, remove it's place in the field order
				if($currentMetadata->getFieldOrder() != null) {
					$sql = "UPDATE redcap_metadata
							SET field_order = (field_order - 1)
							WHERE project_id = ".$this->getProjectId()."
								AND field_order > ".$currentMetadata->getFieldOrder();
//					echo "$sql <br /><br />";
					db_query($sql);
					if($e = db_error()) echo "Error moving metadata field order $e<br />\n$sql";
				}

				## Get the last field number to appear on the new form
				$sql = "SELECT field_order
						FROM redcap_metadata
						WHERE form_name = '".$fieldMetadata->getFormName()."'
							AND project_id = ".$this->getProjectId()."
						ORDER BY field_order DESC
						LIMIT 1";

//				echo "$sql <br /><br />";
				$newMetadataUpdate["field_order"] = db_result(db_query($sql),0);

				if($newMetadataUpdate["field_order"] <= 0) continue;

				## Push all fields on the project after the new form up by one
				$sql = "UPDATE redcap_metadata
						SET field_order = (field_order + 1)
						WHERE project_id = ".$this->getProjectId()."
							AND field_order > ".$newMetadataUpdate["field_order"];

//				echo "$sql <br /><br />";
				db_query($sql);
				if($e = db_error()) echo "Error moving metadata field order $e<br />\n$sql";

				$newMetadataUpdate["field_order"]++;
			}

			if($currentMetadata->getFieldName() == "") {
				$sql = "INSERT INTO redcap_metadata (project_id, field_name, ".implode(",",array_keys($newMetadataUpdate)).")
						VALUES (".$this->getProjectId().", '".$fieldMetadata->getFieldName()."',".implode(",",$newMetadataUpdate).")";
			}
			else {
				$sql = "UPDATE redcap_metadata d SET ";
				foreach($newMetadataUpdate as $fieldName => $value) {
					$sql .= "$fieldName = $value,";
				}
				$sql = substr($sql,0,strlen($sql) - 1);
				$sql .= " WHERE d.project_id = ".$this->getProjectId()." AND d.field_name = '".$fieldMetadata->getFieldName()."'";
			}

//			echo "$sql <br /><br />";
			db_query($sql);
			if($e = db_error()) echo "Error inserting/updating field $e<Br />\n$sql";
		}
	}
} 