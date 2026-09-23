<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\tests\text_formatter;

use vinny\wysiwyg\text_formatter\custom_bbcode_catalog;

class MockCatalogDb
{
	protected $rows = [];
	protected $index = 0;

	public function __construct(array $rows = [])
	{
		$this->rows = $rows;
	}

	public function sql_query($sql)
	{
		$this->index = 0;
		return true;
	}

	public function sql_fetchrow($result)
	{
		if ($this->index < count($this->rows))
		{
			return $this->rows[$this->index++];
		}
		return false;
	}

	public function sql_freeresult($result)
	{
		$this->index = 0;
	}
}

class custom_bbcode_catalog_test extends \phpbb_test_case
{
	public function test_catalog_definitions_and_block_detection()
	{
		$rows = [
			[
				'bbcode_id' => 1,
				'bbcode_tag' => 'hashtag',
				'bbcode_helpline' => 'Hashtag helpline',
				'bbcode_match' => '[hashtag]{TEXT}[/hashtag]',
				'bbcode_tpl' => '<span class="hashtag">#{TEXT}</span>',
				'display_on_posting' => 1,
			],
			[
				'bbcode_id' => 2,
				'bbcode_tag' => 'alert',
				'bbcode_helpline' => 'Alert box',
				'bbcode_match' => '[alert]{TEXT}[/alert]',
				'bbcode_tpl' => '<div class="alert">{TEXT}</div>',
				'display_on_posting' => 0,
			],
		];

		$db = new MockCatalogDb($rows);
		$catalog = new custom_bbcode_catalog($db, 'phpbb_bbcodes');

		$defs = $catalog->getDefinitions();
		$this->assertEquals(2, count($defs));

		$hashtag = $catalog->getDefinition('hashtag');
		$this->assertNotNull($hashtag);
		$this->assertEquals('hashtag', $hashtag['tag']);
		$this->assertFalse($catalog->isBlock('hashtag'));
		$this->assertTrue(strlen($catalog->getFingerprint('hashtag')) > 0);

		$alert = $catalog->getDefinition('alert');
		$this->assertNotNull($alert);
		$this->assertEquals('alert', $alert['tag']);
		$this->assertTrue($catalog->isBlock('alert'));

		$posting = $catalog->getPostingBbcodes();
		$this->assertEquals(1, count($posting));
		$this->assertArrayHasKey('hashtag', $posting);
		$this->assertFalse(isset($posting['alert']));
	}
}
