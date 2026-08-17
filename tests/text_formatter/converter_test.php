<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\tests\text_formatter;

class StubParser implements \phpbb\textformatter\parser_interface
{
	public $xmlToReturn = '';
	public $lastTextParsed = '';
	public function parse($text) { $this->lastTextParsed = $text; return $this->xmlToReturn; }
	public function disable_bbcode($name) {}
	public function disable_bbcodes() {}
	public function disable_censor() {}
	public function disable_magic_url() {}
	public function disable_smilies() {}
	public function enable_bbcode($name) {}
	public function enable_bbcodes() {}
	public function enable_censor() {}
	public function enable_magic_url() {}
	public function enable_smilies() {}
	public function get_errors() { return []; }
	public function set_var($name, $value) {}
	public function set_vars(array $vars) {}
}

class StubRenderer implements \phpbb\textformatter\renderer_interface
{
	public $htmlToReturn = '';
	public $lastXmlRendered = '';
	public function render($text) { $this->lastXmlRendered = $text; return $this->htmlToReturn; }
	public function set_smilies_path($path) {}
	public function get_viewcensors() { return true; }
	public function get_viewflash() { return true; }
	public function get_viewimg() { return true; }
	public function get_viewsmilies() { return true; }
	public function set_viewcensors($value) {}
	public function set_viewflash($value) {}
	public function set_viewimg($value) {}
	public function set_viewsmilies($value) {}
}

class DummyDB
{
	public function sql_query($sql) { return true; }
	public function sql_fetchrow($result) { return false; }
	public function sql_freeresult($result) {}
}

class DummyLanguage
{
	public function lang($key)
	{
		$map = [
			'WROTE' => 'wrote:',
			'CODE' => 'Code:',
			'SELECT_ALL_CODE' => 'Select all',
		];
		return isset($map[$key]) ? $map[$key] : $key;
	}
}

class converter_test extends \phpbb_test_case
{
	protected $converter;
	protected $db;
	protected $config;
	protected $parser;
	protected $renderer;
	protected $language;

	public function setUp(): void
	{
		parent::setUp();

		$this->db = new DummyDB();
		$this->config = [
			'smilies_path' => 'images/smilies',
			'script_path' => '/forum'
		];
		$this->parser = new StubParser();
		$this->renderer = new StubRenderer();
		$this->language = new DummyLanguage();

		$this->converter = new \vinny\wysiwyg\text_formatter\converter(
			$this->db,
			$this->config,
			'./',
			$this->parser,
			$this->language
		);
	}

	public function test_toHtml_empty()
	{
		$this->assertEquals('', $this->converter->toHtml(''));
		$this->assertEquals('', $this->converter->toHtml(null));
	}

	public function test_toBBCode_empty()
	{
		$this->assertEquals('', $this->converter->toBBCode(''));
		$this->assertEquals('', $this->converter->toBBCode(null));
	}

	public function test_toHtml_delegation()
	{
		$bbcode = '[b]Hello[/b]';
		$xml = '<r><B>Hello</B></r>';

		$this->parser->xmlToReturn = $xml;

		$result = $this->converter->toHtml($bbcode);
		$this->assertEquals($bbcode, $this->parser->lastTextParsed);
		$this->assertEquals('<p><strong>Hello</strong></p>', $result);
	}

	public function test_toBBCode_conversions()
	{
		// Test simple tags
		$this->assertEquals('[b]Hello[/b]', $this->converter->toBBCode('<strong>Hello</strong>'));
		$this->assertEquals('[i]Hello[/i]', $this->converter->toBBCode('<em>Hello</em>'));
		$this->assertEquals('[u]Hello[/u]', $this->converter->toBBCode('<u>Hello</u>'));
		$this->assertEquals('[s]Hello[/s]', $this->converter->toBBCode('<s>Hello</s>'));

		// Test blocks
		$this->assertEquals('[quote]Hello[/quote]', $this->converter->toBBCode('<blockquote>Hello</blockquote>'));
		$this->assertEquals('[code]echo 1;[/code]', $this->converter->toBBCode('<pre><code>echo 1;</code></pre>'));

		// Test links
		$this->assertEquals('[url=https://example.com]Link[/url]', $this->converter->toBBCode('<a href="https://example.com">Link</a>'));

		// Test lists
		$this->assertEquals("[list][*]Item 1\n[*]Item 2\n[/list]", $this->converter->toBBCode('<ul data-bbcode="list"><li>Item 1</li><li>Item 2</li></ul>'));
		$this->assertEquals("[list=1][*]Item 1\n[*]Item 2\n[/list]", $this->converter->toBBCode('<ol data-bbcode="list" data-bbcode-val="1"><li>Item 1</li><li>Item 2</li></ol>'));

		// Test Spoilers
		$this->assertEquals('[spoiler]Spoiler content[/spoiler]', $this->converter->toBBCode('<details data-bbcode="spoiler"><summary>Spoiler</summary>Spoiler content</details>'));

		// Test Attachments
		$this->assertEquals('[attachment=0]file.txt[/attachment]', $this->converter->toBBCode('<div class="wysiwyg-attachment" data-bbcode="attachment" data-bbcode-val="0" data-filename="file.txt">file.txt</div>'));

		// Test Tables
		$this->assertEquals('[table][tr][td]Cell[/td][/tr][/table]', $this->converter->toBBCode('<table data-bbcode="table"><tr data-bbcode="tr"><td data-bbcode="td">Cell</td></tr></table>'));

		// Test Horizontal Rules
		$this->assertEquals('[hr]', $this->converter->toBBCode('<hr data-bbcode="hr" />'));
	}

	public function test_toBBCode_custom_bbcodes()
	{
		// Simple custom BBCode
		$this->assertEquals('[noguests]Hello[/noguests]', $this->converter->toBBCode('<span data-bbcode="noguests">Hello</span>'));

		// Custom BBCode with data-bbcode-val
		$this->assertEquals('[noguests=123]Hello[/noguests]', $this->converter->toBBCode('<span data-bbcode="noguests" data-bbcode-val="123">Hello</span>'));

		// Custom BBCode with primary attribute in JSON
		$this->assertEquals('[testtag=val1 foo="bar"]Hello[/testtag]', $this->converter->toBBCode('<span data-bbcode="testtag" data-bbcode-attrs="{&quot;testtag&quot;:&quot;val1&quot;,&quot;foo&quot;:&quot;bar&quot;}">Hello</span>'));
	}

	public function test_xmlToHtml_conversions()
	{
		// Test Spoilers
		$this->parser->xmlToReturn = '<r><SPOILER>Spoiler content</SPOILER></r>';
		$this->assertEquals('<details data-bbcode="spoiler"><summary>Spoiler</summary>Spoiler content</details>', $this->converter->toHtml('[spoiler]Spoiler content[/spoiler]'));

		// Test Attachments
		$this->parser->xmlToReturn = '<r><ATTACHMENT id="0">file.txt</ATTACHMENT></r>';
		$this->assertEquals('<div class="wysiwyg-attachment" data-bbcode="attachment" data-bbcode-val="0" data-filename="file.txt">file.txt</div>', $this->converter->toHtml('[attachment=0]file.txt[/attachment]'));

		// Test Tables
		$this->parser->xmlToReturn = '<r><TABLE><TR><TD>Cell</TD></TR></TABLE></r>';
		$this->assertEquals('<table data-bbcode="table"><tr data-bbcode="tr"><td data-bbcode="td">Cell</td></tr></table>', $this->converter->toHtml('[table][tr][td]Cell[/td][/tr][/table]'));

		// Test Horizontal Rules
		$this->parser->xmlToReturn = '<r><HR /></r>';
		$this->assertEquals('<hr data-bbcode="hr">', $this->converter->toHtml('[hr]'));
	}
}
