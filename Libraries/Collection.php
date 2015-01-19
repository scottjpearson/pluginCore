<?php
/** Author: Jon Scherdin */

class Collection extends \ArrayObject {
	public function add($obj) {
		parent::append($obj);
	}

	public function getItem($key) {
		parent::offsetGet($key);
	}

	public function delete($key) {
		parent::offsetUnset($key);
	}
}
