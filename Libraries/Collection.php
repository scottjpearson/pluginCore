<?php
/** Author: Jon Scherdin */
namespace Plugin;

class Collection extends \ArrayObject {
	public function add($obj) {
		parent::append($obj);
	}

	public function getItem($key) {
		return parent::offsetGet($key);
	}

	public function delete($key) {
		parent::offsetUnset($key);
	}
}
