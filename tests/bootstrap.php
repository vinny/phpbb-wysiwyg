<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace {
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
	// Register class loader
	$loader = require __DIR__ . '/../../../../../phpbb/vendor/autoload.php';

	// Add the extension namespace to autoloading
	$loader->addPsr4('vinny\\wysiwyg\\', __DIR__ . '/../');
	$loader->addPsr4('phpbb\\', __DIR__ . '/../../../../../phpbb/phpbb/');

	// If phpbb_test_case is not defined, define it
	if (!class_exists('phpbb_test_case'))
	{
		abstract class phpbb_test_case extends \PHPUnit\Framework\TestCase
		{
			protected function setUp(): void
			{
				parent::setUp();
			}
		}
	}
}

namespace phpbb\event {
	// If phpbb\event\data is not defined, define a dummy for mock tests
	if (!class_exists('phpbb\event\data'))
	{
		class data implements \ArrayAccess
		{
			protected $container;
			public function __construct(array $container = []) { $this->container = $container; }
			#[\ReturnTypeWillChange]
			public function offsetExists($offset) { return isset($this->container[$offset]); }
			#[\ReturnTypeWillChange]
			public function offsetGet($offset) { return $this->container[$offset]; }
			#[\ReturnTypeWillChange]
			public function offsetSet($offset, $value) { $this->container[$offset] = $value; }
			#[\ReturnTypeWillChange]
			public function offsetUnset($offset) { unset($this->container[$offset]); }
			public function update_subarray($key, $subkey, $value) { $this->container[$key][$subkey] = $value; }
		}
	}
}
