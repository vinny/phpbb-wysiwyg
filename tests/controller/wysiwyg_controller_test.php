<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\tests\controller;

class DummyRequest
{
	public $vars = [];
	public function raw_variable($name, $default)
	{
		return isset($this->vars[$name]) ? $this->vars[$name] : $default;
	}
}

class DummyConverter
{
	public $bbcodeToReturn = '';
	public $htmlToReturn = '';
	public function toBBCode($html) { return $this->bbcodeToReturn; }
	public function toHtml($bbcode) { return $this->htmlToReturn; }
}

class DummyAuth
{
	public $acl = [];
	public function acl_get($permission)
	{
		return isset($this->acl[$permission]) ? $this->acl[$permission] : false;
	}
}

class DummyUser
{
	public function lang($key)
	{
		return $key;
	}
}

class DummyConfig implements \ArrayAccess
{
	public $config = [];
	public function offsetExists($offset) { return isset($this->config[$offset]); }
	public function offsetGet($offset) { return isset($this->config[$offset]) ? $this->config[$offset] : null; }
	public function offsetSet($offset, $value) { $this->config[$offset] = $value; }
	public function offsetUnset($offset) { unset($this->config[$offset]); }
}

class wysiwyg_controller_test extends \phpbb_test_case
{
	protected $controller;
	protected $request;
	protected $converter;
	protected $config;
	protected $auth;
	protected $user;

	public function setUp(): void
	{
		parent::setUp();

		$this->request = new DummyRequest();
		$this->converter = new DummyConverter();
		$this->config = new DummyConfig();
		$this->config['wysiwyg_enabled'] = 1;
		$this->auth = new DummyAuth();
		$this->user = new DummyUser();

		$this->controller = new \vinny\wysiwyg\controller\wysiwyg_controller(
			$this->request,
			$this->converter,
			$this->config,
			$this->auth,
			$this->user
		);
	}

	public function test_html_to_bbcode_disabled()
	{
		$this->config['wysiwyg_enabled'] = 0;
		$response = $this->controller->html_to_bbcode();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(403, $response->getStatusCode());
	}

	public function test_html_to_bbcode_no_permission()
	{
		$this->auth->acl['u_wysiwyg_use'] = false;
		$response = $this->controller->html_to_bbcode();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(403, $response->getStatusCode());
	}

	public function test_html_to_bbcode_success()
	{
		$this->auth->acl['u_wysiwyg_use'] = true;
		$this->request->vars['html'] = '<p>test</p>';
		$this->converter->bbcodeToReturn = 'test';

		$response = $this->controller->html_to_bbcode();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals(['bbcode' => 'test'], json_decode($response->getContent(), true));
	}

	public function test_bbcode_to_html_disabled()
	{
		$this->config['wysiwyg_enabled'] = 0;
		$response = $this->controller->bbcode_to_html();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(403, $response->getStatusCode());
	}

	public function test_bbcode_to_html_no_permission()
	{
		$this->auth->acl['u_wysiwyg_use'] = false;
		$response = $this->controller->bbcode_to_html();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(403, $response->getStatusCode());
	}

	public function test_bbcode_to_html_success()
	{
		$this->auth->acl['u_wysiwyg_use'] = true;
		$this->request->vars['bbcode'] = 'test';
		$this->converter->htmlToReturn = '<p>test</p>';

		$response = $this->controller->bbcode_to_html();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals(['html' => '<p>test</p>'], json_decode($response->getContent(), true));
	}
}
