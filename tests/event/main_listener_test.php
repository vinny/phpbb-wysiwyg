<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\tests\event;

class DummyRequest
{
	public $vars = [];
	public function variable($name, $default, $multiline = false)
	{
		return isset($this->vars[$name]) ? $this->vars[$name] : $default;
	}
}

class DummyUser
{
	public $loaded_langs = [];
	public $data = [
		'is_registered' => true,
		'user_wysiwyg_enabled' => 1
	];
	public function add_lang_ext($ext, $lang)
	{
		$this->loaded_langs[] = [$ext, $lang];
	}
	public function lang($key)
	{
		return $key;
	}
}

class DummyConverter
{
	public $bbcodeToReturn = '';
	public $htmlToReturn = '';
	public function toBBCode($html) { return $this->bbcodeToReturn; }
	public function toHtml($bbcode) { return $this->htmlToReturn; }
}

class DummyTemplate
{
	public $vars = [];
	public function assign_vars(array $vars)
	{
		$this->vars = array_merge($this->vars, $vars);
	}
}

class DummyAuth
{
	public $acl = [];
	public function acl_get($permission)
	{
		return isset($this->acl[$permission]) ? $this->acl[$permission] : false;
	}
}

class DummyHelper
{
	public function route($route, $params = [])
	{
		return 'http://example.com/' . $route;
	}
}

class main_listener_test extends \phpbb_test_case
{
	protected $listener;
	protected $request;
	protected $converter;
	protected $template;

	public function setUp(): void
	{
		parent::setUp();

		$this->request = new DummyRequest();
		$this->converter = new DummyConverter();
		$this->config = [
			'wysiwyg_enabled' => 1,
			'wysiwyg_default_enabled' => 1,
			'wysiwyg_allow_toggle' => 1
		];
		$this->user = new DummyUser();
		$this->template = new DummyTemplate();

		$this->helper = new DummyHelper();

		$this->listener = new \vinny\wysiwyg\event\main_listener(
			$this->request,
			$this->converter,
			$this->config,
			$this->user,
			$this->template,
			$this->helper
		);
	}

	public function test_getSubscribedEvents()
	{
		$events = \vinny\wysiwyg\event\main_listener::getSubscribedEvents();
		$this->assertArrayHasKey('core.user_setup', $events);
		$this->assertArrayHasKey('core.text_formatter_s9e_parse_before', $events);
		$this->assertArrayHasKey('core.posting_modify_template_vars', $events);
		$this->assertArrayHasKey('core.ucp_pm_compose_template', $events);
		$this->assertArrayHasKey('core.ucp_profile_modify_signature', $events);
		$this->assertArrayHasKey('core.adm_page_header', $events);
		$this->assertArrayHasKey('core.ucp_prefs_post_data', $events);
		$this->assertArrayHasKey('core.ucp_prefs_post_update_data', $events);
	}

	public function test_on_user_setup()
	{
		$event = new \phpbb\event\data([]);

		$this->listener->on_user_setup($event);

		$this->assertCount(1, $this->user->loaded_langs);
		$this->assertEquals(['vinny/wysiwyg', 'info_acp_wysiwyg'], $this->user->loaded_langs[0]);
	}

	public function test_on_s9e_parse_before_wysiwyg_not_used()
	{
		$this->request->vars['wysiwyg_used'] = 0;
		$event = new \phpbb\event\data([
			'text' => '[b]Hello[/b]',
		]);

		$this->listener->on_s9e_parse_before($event);

		$this->assertEquals('[b]Hello[/b]', $event['text']);
	}

	public function test_on_s9e_parse_before_wysiwyg_used()
	{
		$this->request->vars['wysiwyg_used'] = 1;
		$this->converter->bbcodeToReturn = '[b]Hello[/b]';
		$event = new \phpbb\event\data([
			'text' => '<strong>Hello</strong>',
		]);

		$this->listener->on_s9e_parse_before($event);

		$this->assertEquals('[b]Hello[/b]', $event['text']);
	}

	public function test_on_posting_modify_template_vars()
	{
		
		$message_parser = new \stdClass();
		$message_parser->message = '[b]Hello[/b]';

		$event = new \phpbb\event\data([
			'message_parser' => $message_parser,
		]);

		$this->converter->htmlToReturn = '<strong>Hello</strong>';

		$this->listener->on_posting_modify_template_vars($event);

		$this->assertArrayHasKey('S_WYSIWYG_ENABLED', $this->template->vars);
		$this->assertTrue($this->template->vars['S_WYSIWYG_ENABLED']);
		$this->assertEquals('<strong>Hello</strong>', $this->template->vars['WYSIWYG_CONTENT']);
	}
}
