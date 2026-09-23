<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\tests\text_formatter;

use vinny\wysiwyg\text_formatter\opaque_bbcode_codec;

class opaque_bbcode_codec_test extends \phpbb_test_case
{
	/** @var opaque_bbcode_codec */
	protected $codec;

	public function setUp(): void
	{
		parent::setUp();
		$this->codec = new opaque_bbcode_codec();
	}

	public function test_createEnvelope_and_encode_decode()
	{
		$source = '[pair left="um" right="dois"]meu texto[/pair]';
		$envelope = $this->codec->createEnvelope($source, 'pair', false, 'fingerprint123');

		$this->assertEquals(1, $envelope['version']);
		$this->assertEquals('opaque_bbcode', $envelope['kind']);
		$this->assertEquals($source, $envelope['source']);
		$this->assertEquals('pair', $envelope['displayName']);
		$this->assertEquals(false, $envelope['isBlock']);
		$this->assertEquals('fingerprint123', $envelope['definitionFingerprint']);

		$encoded = $this->codec->encodePayload($envelope);
		$this->assertTrue(is_string($encoded) && strlen($encoded) > 0);

		$decoded = $this->codec->decodePayload($encoded);
		$this->assertNotNull($decoded);
		$this->assertEquals($source, $decoded['source']);
		$this->assertEquals('pair', $decoded['displayName']);
		$this->assertEquals(false, $decoded['isBlock']);
		$this->assertEquals('fingerprint123', $decoded['definitionFingerprint']);
	}

	public function test_decode_invalid_inputs()
	{
		$this->assertNull($this->codec->decodePayload(''));
		$this->assertNull($this->codec->decodePayload(null));
		$this->assertNull($this->codec->decodePayload('not-valid-base64!@#$'));
		$this->assertNull($this->codec->decodePayload(base64_encode('not json')));
		$this->assertNull($this->codec->decodePayload(base64_encode(json_encode(['version' => 2]))));
		$this->assertNull($this->codec->decodePayload(base64_encode(json_encode(['version' => 1, 'kind' => 'other']))));
	}

	public function test_render_and_extract_envelope()
	{
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$envelope = $this->codec->createEnvelope('[custom]hello[/custom]', 'custom', true);
		$element = $this->codec->renderEnvelopeElement($dom, $envelope);
		$dom->appendChild($element);

		$this->assertEquals('div', $element->nodeName);
		$this->assertEquals('true', $element->getAttribute('data-opaque-bbcode'));
		$this->assertStringContainsString('wysiwyg-opaque-bbcode--block', $element->getAttribute('class'));
		$this->assertEquals('false', $element->getAttribute('contenteditable'));
		$this->assertEquals('[custom]', $element->textContent);

		$extracted = $this->codec->extractSourceFromNode($element);
		$this->assertEquals('[custom]hello[/custom]', $extracted);
	}
}
