<?php
namespace Plugin;

include_once("../bootstrap.php");
$GLOBALS['Core']->Libraries("Project",false);

class ProjectTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @test
	 */
	public function testProjectInitialization() {
		$this->assertTrue(true);
	}
} 