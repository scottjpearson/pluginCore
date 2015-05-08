<?php
/** Author: Kyle McGuffin */
namespace Plugin;

use \Exception;

global $Core;
$Core->Libraries(array("Project"));

# Class for looking up a group of project IDs at once
class ProjectSet {
	protected $projects;
	const SQL_ERROR = 1;
	const INVALID_PROJECT = 2;

	/**
	 * @param Array|string $projectNames array of project names
	 */
	public function __construct($projectNames) {
		if(is_array($projectNames)) {
			$this->projects = array();
			foreach($projectNames as $projectName) {
				$this->projects[] = new Project($projectName);
			}
		}
		else {
			$this->projects = array($projectNames);
		}
	}

	# Publicly access the record ID for the record
	/* @return Project[] */
	public function getProjects() {
		return $this->projects;
	}

	public function getProjectIds() {
		$ids = array();

		foreach($this->projects as $project) {
			/* @var $project Project */
			if($project->getProjectId() == "") throw new Exception("Instrument Project doesn't exist ".$project->getProjectName(), self::INVALID_PROJECT);
			$ids[] = $project->getProjectId();
		}

		return $ids;
	}

	/* @param $project Project */
	public function addProjectToSet($project) {
		$this->projects[] = $project;
	}

	# Publicly access the Project object based on Project ID
	/* @param $projectId int
	 * @return Project */
	public function getProjectById($projectId) {
		foreach($this->getProjects() as $project) {
			if($project->getProjectId() == $projectId) {
				return $project;
			}
		}
		return NULL;
	}

	# Publicly access the Project object based on Project Name
	/* @param $projectName string
	 * @return Project */
	public function getProjectByName($projectName) {
		foreach($this->getProjects() as $project) {
			if($project->getProjectName() == $projectName) {
				return $project;
			}
		}
		return NULL;
	}
}