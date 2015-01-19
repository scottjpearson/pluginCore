<?php
/** Author: Jon Scherdin */

class Event {
	private static $tableName = "redcap_events_metadata";

	private $event_id;
	private $arm_id;
	private $day_offset;
	private $offset_min;
	private $offset_max;
	private $descrip;
	private $unique_key;
	private $external_id;

	/**
	 * @return string
	 */
	public static function getTableName() {
		return self::$tableName;
	}

	/**
	 * @return int
	 */
	public function getEventId() {
		return $this->event_id;
	}

	/**
	 * @param int $event_id
	 */
	public function setEventId($event_id) {
		$this->event_id = $event_id;
	}

	/**
	 * @return int
	 */
	public function getArmId() {
		return $this->arm_id;
	}

	/**
	 * @param int $arm_id
	 */
	public function setArmId($arm_id) {
		$this->arm_id = $arm_id;
	}

	/**
	 * @return float
	 */
	public function getDayOffset() {
		return $this->day_offset;
	}

	/**
	 * @param float $day_offset
	 */
	public function setDayOffset($day_offset) {
		$this->day_offset = $day_offset;
	}

	/**
	 * @return float
	 */
	public function getOffsetMin() {
		return $this->offset_min;
	}

	/**
	 * @param float $offset_min
	 */
	public function setOffsetMin($offset_min) {
		$this->offset_min = $offset_min;
	}

	/**
	 * @return float
	 */
	public function getOffsetMax() {
		return $this->offset_max;
	}

	/**
	 * @param float $offset_max
	 */
	public function setOffsetMax($offset_max) {
		$this->offset_max = $offset_max;
	}

	/**
	 * @return string
	 */
	public function getDescrip() {
		return $this->descrip;
	}

	/**
	 * @param string $descrip
	 */
	public function setDescrip($descrip) {
		$this->descrip = $descrip;
	}

	/**
	 * @return string
	 */
	public function getUniqueKey() {
		return $this->unique_key;
	}

	/**
	 * @param string $unique_key
	 */
	public function setUniqueKey($unique_key) {
		$this->unique_key = $unique_key;
	}

	/**
	 * @return string
	 */
	public function getExternalId() {
		return $this->external_id;
	}

	/**
	 * @param string $external_id
	 */
	public function setExternalId($external_id) {
		$this->external_id = $external_id;
	}

	function __construct($event_id = null) {
		if ($event_id != null) {
			$sql = "SELECT * FROM ".self::$tableName." WHERE event_id = $event_id";
			$result = db_query($sql);
			if (db_num_rows($result) == 1) {
				$record = db_fetch_assoc($result);
				foreach($record as $column => $value) {
					$this->$column = $value;
				}
			}
		}
	}

	/**
	 * @return Event[]
	 */
	public static function getItemsByProject(Proj $project) {
		$sql = "SELECT e.*
				FROM ".self::$tableName." e, ".Arm::getTableName()." a
				WHERE a.project_id = {$project->getProjectId()}
					AND a.arm_id = e.arm_id
				ORDER BY a.arm_num, e.day_offset";
		$result = db_query($sql);
		$col = new Collection();
		while ($row = db_fetch_assoc($result)) {
			$item = new self();
			foreach($row as $column => $value) {
				$item->$column = $value;
			}
			$col->add($item);
		}
		return $col;
	}
} 