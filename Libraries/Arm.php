<?php
/** Author: Jon Scherdin */

class Arm {
	private static $tableName = "redcap_events_arms";

	private $arm_id;
	private $project_id;
	private $arm_num;
	private $arm_name;

	private $events;

	/**
	 * @return string
	 */
	public static function getTableName() {
		return self::$tableName;
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
	 * @return int
	 */
	public function getProjectId() {
		return $this->project_id;
	}

	/**
	 * @param int $project_id
	 */
	public function setProjectId($project_id) {
		$this->project_id = $project_id;
	}

	/**
	 * @return int
	 */
	public function getArmNum() {
		return $this->arm_num;
	}

	/**
	 * @param int $arm_num
	 */
	public function setArmNum($arm_num) {
		$this->arm_num = $arm_num;
	}

	/**
	 * @return string
	 */
	public function getArmName() {
		return $this->arm_name;
	}

	/**
	 * @param string $arm_name
	 */
	public function setArmName($arm_name) {
		$this->arm_name = $arm_name;
	}

	function __construct($arm_id = null) {
		if ($arm_id != null) {
			$sql = "SELECT * FROM ".self::$tableName." WHERE arm_id = $arm_id";
			$result = db_query($sql);
			if (db_num_rows($result) == 1) {
				$record = db_fetch_assoc($result);
				foreach($record as $column => $value) {
					$this->$column = $value;
				}
			}
		}
	}

	public function getEvents() {

	}

	/**
	 * @return Arm[]
	 */
	public static function getItemsByProject(Proj $project) {
		$sql = "SELECT *
				FROM ".self::$tableName."
				WHERE project_id = {$project->getProjectId()}
				ORDER BY arm_num";
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