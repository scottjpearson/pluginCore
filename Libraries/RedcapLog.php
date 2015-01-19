<?php
/** Author: Jon Scherdin */

class RedcapLog {
	private static $tableName = "redcap_log_event";

	const UPDATE = 'UPDATE';
	const INSERT = 'INSERT';
	const DELETE = 'DELETE';
	const SELECT = 'SELECT';
	const ERROR = 'ERROR';
	const LOGIN = 'LOGIN';
	const LOGOUT = 'LOGOUT';
	const OTHER = 'OTHER';
	const DATA_EXPORT = 'DATA_EXPORT';
	const DOC_UPLOAD = 'DOC_UPLOAD';
	const DOC_DELETE = 'DOC_DELETE';
	const MANAGE = 'MANAGE';
	const LOCK_RECORD = 'LOCK_RECORD';
	const ESIGNATURE = 'ESIGNATURE';

	private $log_event_id;
	private $project_id;
	private $ts;
	private $user;
	private $ip;
	private $page;
	private $event;
	private $object_type;
	private $sql_log;
	private $pk;
	private $event_id;
	private $data_values;
	private $description;
	private $legacy;
	private $change_reason;

	function __construct() {
	}
} 