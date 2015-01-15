<?php
/** Author: Jon Scherdin */

class Core_RedcapData {
	private static $tableName = "redcap_data";

	const SQL_ERROR = 3;

	private $project_id;
	private $event_id;
	private $record;
	private $field_name;
	private $value;

	/**
	 * @return mixed
	 */
	public function getEventId()
	{
		return $this->event_id;
	}

	/**
	 * @param mixed $event_id
	 * @return $this
	 */
	public function setEventId($event_id)
	{
		$this->event_id = $event_id;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getFieldName()
	{
		return $this->field_name;
	}

	/**
	 * @param mixed $field_name
	 * @return $this
	 */
	public function setFieldName($field_name)
	{
		$this->field_name = $field_name;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getProjectId()
	{
		return $this->project_id;
	}

	/**
	 * @param mixed $project_id
	 * @return $this
	 */
	public function setProjectId($project_id)
	{
		$this->project_id = $project_id;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getRecord()
	{
		return $this->record;
	}

	/**
	 * @param mixed $record
	 * @return $this
	 */
	public function setRecord($record)
	{
		$this->record = $record;
		return $this;
	}

	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return self::$tableName;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function setValue($value)
	{
		$this->value = $value;
		return $this;
	}

	public function __construct($project_id, $event_id, $record, $field_name, $value) {
		$this->event_id = $event_id;
		$this->field_name = $field_name;
		$this->project_id = $project_id;
		$this->record = $record;
		$this->value = $value;
	}

	public static function create() {
		return new self("","","","","");
	}

	public function save() {
		if ($this->project_id == "" || $this->event_id == "" || $this->record == "" || $this->field_name == "") {
			throw new Exception("Not all items assigned");
		}

		$this->value = db_real_escape_string($this->value);

		$sql = "UPDATE ".self::$tableName."
				SET value = '$this->value'
				WHERE project_id = $this->project_id
					AND record = '$this->record'
					AND field_name = '$this->field_name'";
		if(!db_query($sql)) throw new Exception("Failed to update item ".$sql, self::SQL_ERROR);

		if(db_affected_rows() == 0) {
			if($this->value != "") {
				$sql = "INSERT INTO ".self::$tableName."
						(project_id,
						event_id,
						record,
						field_name,
						value)
					VALUES
						($this->project_id,
						$this->event_id,
						'$this->record',
						'$this->field_name',
						'$this->value')";
				if(!db_query($sql)) throw new Exception("Error Inserting Item ".$sql,self::SQL_ERROR);
			}
		}

		# log
	}

	public static function saveItems($project_id, $record, $items) {
		if ($items->count() == 0) throw new Exception();

		$insertStatements = array();
		$logText = "";
		$logSql = "";
		$logType = "";
		$logDescription = "";
		$updateCount = 0;

		/* @var $item Core_RedcapData */
		foreach($items as $item) {
			$item->value = db_real_escape_string($item->value);

			$sql = "UPDATE ".self::$tableName."
					SET value = '$item->value'
					WHERE project_id = $item->project_id
						AND record = '$item->record'
						AND field_name = '$item->field_name'";
			if(!db_query($sql)) throw new Exception("Failed to update item ".$sql, self::SQL_ERROR);

			if(db_affected_rows() == 0) {
				if($item->value != "") {
					$insertStatements[] =
						"INSERT INTO ".self::$tableName."
							(project_id,
							event_id,
							record,
							field_name,
							value)
						VALUES
							($item->project_id,
							$item->event_id,
							'$item->record',
							'$item->field_name',
							'$item->value')";
					if(!db_query($sql)) throw new Exception("Error Inserting Item ".$sql,self::SQL_ERROR);
				}
			}
			else {
				$updateCount++;
				$logSql .= ($logSql == "" ? "" : ",\n").str_replace("\t","",$sql);
			}
			$logText .= ($logText == "" ? "" : ",\n")."$item->field_name = '$item->value'";
		}

		if(count($insertStatements) > 0) {
			$sql = "INSERT INTO ".self::$tableName." (project_id, event_id, record, field_name, value) VALUES\n".implode(",\n",$insertStatements);
			if(!db_query($sql)) throw new Exception("Couldn't create record ".$sql, self::INSERT_ERROR);
			$logSql .= ($logSql == "" ? "" : ",\n").str_replace("\t","",$sql);
		}

		if($updateCount == 0 && count($insertStatements) > 0) {
			$logType = "INSERT";
			$logDescription = "Creating Record";
		}
		else if($updateCount > 0) {
			$logType = "UPDATE";
			$logDescription = "Updating Record";
		}

		if($logType != "") {
			Core::log_rc_event($project_id, $logSql, "redcap_data", $logType, $record, $logText, $logDescription, "0", "[PLUGIN]");
		}
	}
} 