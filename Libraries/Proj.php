<?php
/** Author: Jon Scherdin */

class Proj {
	# name of db table
	private static $tableName = "redcap_projects";

	# Error Codes
	const NO_RECORD_ERROR = 1;
	const MULTIPLE_RECORD_ERROR = 2;
	const SQL_ERROR = 3;
	const INSERT_ERROR = 4;

	# columns
	private $project_id;
	private $project_name;
	private $app_title;
	private $status;
	private $survey_enabled;
	private $auto_inc_set;

	# non-table variables
	private $shortName;
	private $firstEventId;
	private $longitudinal;
	private $primaryKey;

	# collections
	/* @var Metadata[] */
	private $metadata;

	/* @var Event[] */
	private $events;

	/* @var Arm[] */
	private $arms;

	/* @var Record[] */
	private $records;

	private $forms;

	private $surveys;

	/**
	 * @return string
	 */
	public function getTableName() {
		return self::$tableName;
	}

	/**
	 * @return string
	 */
	public function getAppTitle() {
		return $this->app_title;
	}

	/**
	 * @return int
	 */
	public function getProjectId() {
		return $this->project_id;
	}

	/**
	 * @param int $projectId
	 */
	public function setProjectId($projectId) {
		$this->projectId = $projectId;
	}

	/**
	 * @return string
	 */
	public function getProjectName() {
		return $this->project_name;
	}

	/**
	 * @param string $projectName
	 */
	public function setProjectName($projectName) {
		$this->project_name = $projectName;
	}

	/**
	 * @return int
	 */
	public function getAutoIncSet() {
		return $this->auto_inc_set;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return int
	 */
	public function getSurveysEnabled() {
		return $this->survey_enabled;
	}

	public function __construct() { }

	public static function findById($id) {
		$instance = new self();
		$instance->project_id = $id;
		$instance->load();
		return $instance;
	}

	public static function findByName($name) {
		$instance = new self();
		$instance->project_id = $instance->getProjectIdByName($name);
		$instance->load();
		return $instance;
	}

	public static function findByShortName($shortName) {
		$instance = new self();
		$instance->project_id = $instance->getProjectIdByShortName($shortName);
		$instance->shortName = $shortName;
		$instance->load();
		return $instance;
	}

	private function getProjectIdByName($name) {
		$sql = "SELECT project_id FROM ".self::$tableName." WHERE project_name = '$name'";
		return db_result(db_query($sql), 0);
	}

	private function getProjectIdByShortName($title) {
		$sql = "SELECT project_id FROM ".self::$tableName." WHERE app_title LIKE '%($title)'";
		return db_result(db_query($sql), 0);
	}

	private function load() {
		$sql = "SELECT * FROM ".self::$tableName." WHERE project_id = $this->project_id";
		$result = db_query($sql);
		$columns = $result->fetch_fields();
		$row = $result->fetch_assoc();
		foreach($columns as $column) {
			//$field = str_replace(" ", "", lcfirst(ucwords(str_replace("_", " ", $column->name))));
			$field = $column->name;
			if (property_exists("\\NCS\\Proj", $field)) {
				$this->$field = $row[$field];
			}
		}
	}

	public function getFirstEventId() {
		if ($this->events == null) $this->getEvents();
		$this->firstEventId = $this->events[0]->getEventId();
		return $this->firstEventId;
	}

	public function getPrimaryKey() {
		if ($this->metadata == null) { $this->getMetadata(); }
		$this->primaryKey = $this->metadata[0]->getFieldName();
		return $this->primaryKey;
	}

	public function isLongitudinal() {
		if ($this->events == null) $this->getEvents();
		if ($this->events->count() > 1)
			return true;
		else
			return false;
	}

	/**
	 * @return Arm[]
	 */
	public function getArms() {
		if ($this->arms == null) { $this->arms = Arm::getItemsByProject($this); }
		return $this->arms;
	}

	/**
	 * @return Event[]
	 */
	public function getEvents() {
		if ($this->events == null) { $this->events = Event::getItemsByProject($this); }
		return $this->events;
	}

	/**
	 * @return Metadata[]
	 */
	public function getMetadata() {
		if ($this->metadata == null) { $this->metadata = Metadata::getItemsByProject($this); }
		return $this->metadata;
	}

	public final function getUniqueRecordList() {
		if(!isset($this->recordList)) {
			$sql = "SELECT DISTINCT d.record
					FROM redcap_data d
					WHERE d.project_id = {$this->projectId}
					ORDER BY d.record";
			if(!($result = db_query($sql))) throw new Exception("Failed to lookup record list\n".db_error());

			$this->recordList = array();
			while($row = db_fetch_assoc($result)) {
				$this->recordList[] = $row["record"];
			}
		}
		return $this->recordList;
	}

	# Pull the next auto ID for the project and save it
	public final function createAutoId() {
		# get the latest id
		$sql = "SELECT DISTINCT d.record, m.field_name
				FROM redcap_metadata m
				LEFT JOIN redcap_data d
				ON (m.project_id = d.project_id
					AND m.field_name = d.field_name)
				WHERE m.project_id = ".$this->project_id."
					AND m.field_order = 1
				ORDER BY abs(record) DESC
				LIMIT 1";
		$result = db_fetch_assoc(db_query($sql),0);

		$id = $result['record'];
		$fieldName = $result['field_name'];

		# if there are no records, set id = 1, otherwise increment id
		$newId = ($id == "") ? 1 : $id++;

		# insert new id into table
		$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
				({$this->project_id},{$this->firstEventId},'$newId','$fieldName','$newId')";
		if(!db_query($sql)) throw new Exception("Error Inserting Auto ID ".$sql,self::SQL_ERROR);
		$logSql = $sql;

		Core::log_rc_event($this->project_id,$logSql,"redcap_data","INSERT",$newId,"$fieldName = '$newId'","Create Record","0","[PLUGIN]");

		# Return new auto id value
		return $newId;
	}
} 