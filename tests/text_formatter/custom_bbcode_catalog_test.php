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

	public function test_parseMatch_all_phpbb_patterns()
	{
		$catalog = new custom_bbcode_catalog(new MockCatalogDb([]), 'phpbb_bbcodes');

		// 1. Simple Tier 1 wrapper
		$center = $catalog->parseMatch('[center]{TEXT}[/center]', '<div style="text-align:center">{TEXT}</div>', 'center');
		$this->assertTrue($center['has_close_tag']);
		$this->assertTrue($center['can_inline_edit']);
		$this->assertEquals('div', $center['inline_edit_meta']['root_tag']);
		$this->assertEquals('text-align:center', $center['inline_edit_meta']['style']);
		$this->assertEquals(1, count($center['fields']));

		// 2. Named attributes + optional + CHOICE
		$anchor = $catalog->parseMatch(
			'[anchor name={IDENTIFIER} visible={CHOICE=yes,no;optional}]{TEXT}[/anchor]',
			'<a id="{IDENTIFIER}">{TEXT}</a>',
			'anchor'
		);
		$this->assertEquals(2, count($anchor['attributes']));
		$this->assertEquals('name', $anchor['attributes'][0]['name']);
		$this->assertEquals('visible', $anchor['attributes'][1]['name']);
		$this->assertEquals(3, count($anchor['fields']));
		$this->assertFalse($anchor['fields'][0]['optional']);
		$this->assertTrue($anchor['fields'][1]['optional']);
		$this->assertEquals(['yes', 'no'], $anchor['fields'][1]['choices']);
		$this->assertFalse($anchor['can_inline_edit']);

		// 3. Composite content (TEXT1-TEXT2-TEXT3)
		$badge = $catalog->parseMatch(
			'[badge]{TEXT1}-{TEXT2}-{TEXT3}[/badge]',
			'<img src="https://img.shields.io/badge/{TEXT1}-{TEXT2}-{TEXT3}" alt="" />',
			'badge'
		);
		$this->assertEquals(3, count($badge['fields']));
		$this->assertEquals('TEXT1', $badge['fields'][0]['token_id']);
		$this->assertEquals('TEXT2', $badge['fields'][1]['token_id']);
		$this->assertEquals('TEXT3', $badge['fields'][2]['token_id']);
		$this->assertFalse($badge['can_inline_edit']);

		// 4. Static prefix in content (YouTube URL) + iframe block detection
		$yt = $catalog->parseMatch(
			'[youtube]https://www.youtube.com/watch?v={SIMPLETEXT}[/youtube]',
			'<iframe src="https://www.youtube.com/embed/{SIMPLETEXT}"></iframe>',
			'youtube'
		);
		$this->assertEquals(1, count($yt['fields']));
		$this->assertEquals('https://www.youtube.com/watch?v=', $yt['fields'][0]['static_prefix']);
		$this->assertTrue($catalog->detectIsBlock('<iframe src="https://www.youtube.com/embed/abc"></iframe>'));
		$this->assertFalse($yt['can_inline_edit']);

		// 5. Composite default attribute + RANGE + s9e $options / #rules ignored
		$flash = $catalog->parseMatch(
			'[box={RANGE=10,100},{NUMBER2} $tagName=box #denyChild=box]{TEXT}[/box]',
			'<div style="width:{RANGE}%;height:{NUMBER2}px">{TEXT}</div>',
			'box'
		);
		$this->assertEquals(0, count($flash['attributes']));
		$this->assertEquals(3, count($flash['fields']));
		$this->assertEquals([10.0, 100.0], $flash['fields'][0]['range']);
		$this->assertEquals('NUMBER2', $flash['fields'][1]['token_id']);
		$this->assertFalse($flash['can_inline_edit']);

		// 6. Empty content / self-closing tags (0 fields)
		$clear = $catalog->parseMatch('[clear][/clear]', '<div style="clear:both"></div>', 'clear');
		$this->assertTrue($clear['has_close_tag']);
		$this->assertEquals(0, count($clear['fields']));

		$divider = $catalog->parseMatch('[divider/]', '<hr />', 'divider');
		$this->assertFalse($divider['has_close_tag']);
		$this->assertTrue($divider['self_closing']);
		$this->assertEquals(0, count($divider['fields']));
	}

	public function test_duplicate_tag_and_trailing_equals_normalization()
	{
		$rows = [
			[
				'bbcode_id' => 10,
				'bbcode_tag' => 'note',
				'bbcode_helpline' => 'Simple note',
				'bbcode_match' => '[note]{TEXT}[/note]',
				'bbcode_tpl' => '<div class="note">{TEXT}</div>',
				'display_on_posting' => 1,
			],
			[
				'bbcode_id' => 11,
				'bbcode_tag' => 'note=',
				'bbcode_helpline' => 'Titled note',
				'bbcode_match' => '[note={TEXT1}]{TEXT2}[/note]',
				'bbcode_tpl' => '<details><summary>{TEXT1}</summary>{TEXT2}</details>',
				'display_on_posting' => 1,
			],
		];

		$catalog = new custom_bbcode_catalog(new MockCatalogDb($rows), 'phpbb_bbcodes');
		$list = $catalog->getPostingBbcodesList();
		$this->assertEquals(2, count($list));
		$this->assertNotNull($catalog->getDefinition('note'));
		$this->assertNotNull($catalog->getDefinition('note='));
	}
}
