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
	public $ajax = true;

	public function raw_variable($name, $default)
	{
		return isset($this->vars[$name]) ? $this->vars[$name] : $default;
	}

	public function variable($name, $default)
	{
		return isset($this->vars[$name]) ? $this->vars[$name] : $default;
	}

	public function is_ajax()
	{
		return $this->ajax;
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
	public $aclf_global = [];

	public function acl_get($permission)
	{
		return !empty($this->acl[$permission]);
	}

	public function acl_getf_global($permission)
	{
		return !empty($this->aclf_global[$permission]);
	}
}

class DummyLanguage
{
	public function lang($key)
	{
		return $key;
	}
}

class DummyConfig implements \ArrayAccess
{
	public $config = [];

	#[\ReturnTypeWillChange]
	public function offsetExists($offset) { return isset($this->config[$offset]); }

	#[\ReturnTypeWillChange]
	public function offsetGet($offset) { return isset($this->config[$offset]) ? $this->config[$offset] : null; }

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) { $this->config[$offset] = $value; }

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) { unset($this->config[$offset]); }
}

class wysiwyg_controller_test extends \phpbb_test_case
{
	protected $controller;
	protected $request;
	protected $converter;
	protected $config;
	protected $auth;
	protected $language;

	public function setUp(): void
	{
		parent::setUp();

		$this->request = new DummyRequest();
		$this->converter = new DummyConverter();
		$this->config = new DummyConfig();
		$this->config['wysiwyg_enabled'] = 1;
		$this->auth = new DummyAuth();
		$this->auth->aclf_global['f_post'] = true;
		$this->language = new DummyLanguage();

		$this->controller = new \vinny\wysiwyg\controller\wysiwyg_controller(
			$this->request,
			$this->converter,
			$this->config,
			$this->auth,
			$this->language
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
		$this->auth->aclf_global = [];
		$this->auth->acl = [];
		$response = $this->controller->html_to_bbcode();
		$this->assertEquals(403, $response->getStatusCode());
	}

	public function test_html_to_bbcode_pm_and_sig_permissions()
	{
		$this->auth->aclf_global = [];
		$this->auth->acl = ['u_sendpm' => true];
		$this->request->vars['html'] = '<p>pm</p>';
		$this->converter->bbcodeToReturn = 'pm';
		$this->assertEquals(200, $this->controller->html_to_bbcode()->getStatusCode());

		$this->auth->acl = ['u_sig' => true];
		$this->assertEquals(200, $this->controller->html_to_bbcode()->getStatusCode());
	}

	public function test_html_to_bbcode_non_ajax()
	{
		$this->request->ajax = false;
		$response = $this->controller->html_to_bbcode();
		$this->assertEquals(400, $response->getStatusCode());
	}

	public function test_html_to_bbcode_payload_too_large()
	{
		$this->request->vars['html'] = str_repeat('a', \vinny\wysiwyg\controller\wysiwyg_controller::MAX_PAYLOAD_BYTES + 1);
		$response = $this->controller->html_to_bbcode();
		$this->assertEquals(413, $response->getStatusCode());
	}

	public function test_html_to_bbcode_success()
	{
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

	public function test_bbcode_to_html_success()
	{
		$this->request->vars['bbcode'] = 'test';
		$this->converter->htmlToReturn = '<p>test</p>';

		$response = $this->controller->bbcode_to_html();
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals(['html' => '<p>test</p>'], json_decode($response->getContent(), true));
	}
}
