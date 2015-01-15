<?php
/** Author: Jon Scherdin */

class Core_Metadata {
	private static $tableName = "redcap_metadata";

	private $project_id;
	private $field_name;
	private $field_phi;
	private $form_name;
	private $form_menu_description;
	private $field_order;
	private $field_units;
	private $element_preceding_header;
	private $element_type;
	private $element_label;
	private $element_enum;
	private $element_note;
	private $element_validation_type;
	private $element_validation_min;
	private $element_validation_max;
	private $element_validation_checktype;
	private $branching_logic;
	private $field_req;
	private $edoc_id;
	private $edoc_display_img;
	private $custom_alignment;
	private $stop_actions;
	private $question_num;
	private $grid_name;
	private $grid_rank;
	private $misc;

	/**
	 * @return string
	 */
	public static function getTableName() {
		return self::$tableName;
	}

	/**
	 * @return mixed
	 */
	public function getBranchingLogic() {
		return $this->branching_logic;
	}

	/**
	 * @param mixed $branching_logic
	 */
	public function setBranchingLogic($branching_logic) {
		$this->branching_logic = $branching_logic;
	}

	/**
	 * @return mixed
	 */
	public function getCustomAlignment()
	{
		return $this->custom_alignment;
	}

	/**
	 * @param mixed $custom_alignment
	 */
	public function setCustomAlignment($custom_alignment)
	{
		$this->custom_alignment = $custom_alignment;
	}

	/**
	 * @return mixed
	 */
	public function getEdocDisplayImg()
	{
		return $this->edoc_display_img;
	}

	/**
	 * @param mixed $edoc_display_img
	 */
	public function setEdocDisplayImg($edoc_display_img)
	{
		$this->edoc_display_img = $edoc_display_img;
	}

	/**
	 * @return mixed
	 */
	public function getEdocId()
	{
		return $this->edoc_id;
	}

	/**
	 * @param mixed $edoc_id
	 */
	public function setEdocId($edoc_id)
	{
		$this->edoc_id = $edoc_id;
	}

	/**
	 * @return mixed
	 */
	public function getElementEnum()
	{
		return $this->element_enum;
	}

	/**
	 * @param mixed $element_enum
	 */
	public function setElementEnum($element_enum)
	{
		$this->element_enum = $element_enum;
	}

	/**
	 * @return mixed
	 */
	public function getElementLabel()
	{
		return $this->element_label;
	}

	/**
	 * @param mixed $element_label
	 */
	public function setElementLabel($element_label)
	{
		$this->element_label = $element_label;
	}

	/**
	 * @return mixed
	 */
	public function getElementNote()
	{
		return $this->element_note;
	}

	/**
	 * @param mixed $element_note
	 */
	public function setElementNote($element_note)
	{
		$this->element_note = $element_note;
	}

	/**
	 * @return mixed
	 */
	public function getElementPrecedingHeader()
	{
		return $this->element_preceding_header;
	}

	/**
	 * @param mixed $element_preceding_header
	 */
	public function setElementPrecedingHeader($element_preceding_header)
	{
		$this->element_preceding_header = $element_preceding_header;
	}

	/**
	 * @return mixed
	 */
	public function getElementType()
	{
		return $this->element_type;
	}

	/**
	 * @param mixed $element_type
	 */
	public function setElementType($element_type)
	{
		$this->element_type = $element_type;
	}

	/**
	 * @return mixed
	 */
	public function getElementValidationChecktype()
	{
		return $this->element_validation_checktype;
	}

	/**
	 * @param mixed $element_validation_checktype
	 */
	public function setElementValidationChecktype($element_validation_checktype)
	{
		$this->element_validation_checktype = $element_validation_checktype;
	}

	/**
	 * @return mixed
	 */
	public function getElementValidationMax()
	{
		return $this->element_validation_max;
	}

	/**
	 * @param mixed $element_validation_max
	 */
	public function setElementValidationMax($element_validation_max)
	{
		$this->element_validation_max = $element_validation_max;
	}

	/**
	 * @return mixed
	 */
	public function getElementValidationMin()
	{
		return $this->element_validation_min;
	}

	/**
	 * @param mixed $element_validation_min
	 */
	public function setElementValidationMin($element_validation_min)
	{
		$this->element_validation_min = $element_validation_min;
	}

	/**
	 * @return mixed
	 */
	public function getElementValidationType()
	{
		return $this->element_validation_type;
	}

	/**
	 * @param mixed $element_validation_type
	 */
	public function setElementValidationType($element_validation_type)
	{
		$this->element_validation_type = $element_validation_type;
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
	 */
	public function setFieldName($field_name)
	{
		$this->field_name = $field_name;
	}

	/**
	 * @return mixed
	 */
	public function getFieldOrder()
	{
		return $this->field_order;
	}

	/**
	 * @param mixed $field_order
	 */
	public function setFieldOrder($field_order)
	{
		$this->field_order = $field_order;
	}

	/**
	 * @return mixed
	 */
	public function getFieldPhi()
	{
		return $this->field_phi;
	}

	/**
	 * @param mixed $field_phi
	 */
	public function setFieldPhi($field_phi)
	{
		$this->field_phi = $field_phi;
	}

	/**
	 * @return mixed
	 */
	public function getFieldReq()
	{
		return $this->field_req;
	}

	/**
	 * @param mixed $field_req
	 */
	public function setFieldReq($field_req)
	{
		$this->field_req = $field_req;
	}

	/**
	 * @return mixed
	 */
	public function getFieldUnits()
	{
		return $this->field_units;
	}

	/**
	 * @param mixed $field_units
	 */
	public function setFieldUnits($field_units)
	{
		$this->field_units = $field_units;
	}

	/**
	 * @return mixed
	 */
	public function getFormMenuDescription()
	{
		return $this->form_menu_description;
	}

	/**
	 * @param mixed $form_menu_description
	 */
	public function setFormMenuDescription($form_menu_description)
	{
		$this->form_menu_description = $form_menu_description;
	}

	/**
	 * @return mixed
	 */
	public function getFormName()
	{
		return $this->form_name;
	}

	/**
	 * @param mixed $form_name
	 */
	public function setFormName($form_name)
	{
		$this->form_name = $form_name;
	}

	/**
	 * @return mixed
	 */
	public function getGridName()
	{
		return $this->grid_name;
	}

	/**
	 * @param mixed $grid_name
	 */
	public function setGridName($grid_name)
	{
		$this->grid_name = $grid_name;
	}

	/**
	 * @return mixed
	 */
	public function getGridRank()
	{
		return $this->grid_rank;
	}

	/**
	 * @param mixed $grid_rank
	 */
	public function setGridRank($grid_rank)
	{
		$this->grid_rank = $grid_rank;
	}

	/**
	 * @return mixed
	 */
	public function getMisc()
	{
		return $this->misc;
	}

	/**
	 * @param mixed $misc
	 */
	public function setMisc($misc)
	{
		$this->misc = $misc;
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
	 */
	public function setProjectId($project_id)
	{
		$this->project_id = $project_id;
	}

	/**
	 * @return mixed
	 */
	public function getQuestionNum()
	{
		return $this->question_num;
	}

	/**
	 * @param mixed $question_num
	 */
	public function setQuestionNum($question_num)
	{
		$this->question_num = $question_num;
	}

	/**
	 * @return mixed
	 */
	public function getStopActions()
	{
		return $this->stop_actions;
	}

	/**
	 * @param mixed $stop_actions
	 */
	public function setStopActions($stop_actions)
	{
		$this->stop_actions = $stop_actions;
	}

	function __construct() { }

	/**
	 * @return Core_Metadata[]
	 */
	public static function getItemsByProject(Core_Proj $project) {
		$sql = "SELECT *
				FROM ".self::$tableName."
				WHERE project_id = {$project->getProjectId()}
				ORDER BY field_order";
		$result = db_query($sql);
		$col = new Core_Collection();
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