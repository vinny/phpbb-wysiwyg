<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\text_formatter;

class opaque_bbcode_codec
{
	const SCHEMA_VERSION = 1;
	const KIND = 'opaque_bbcode';
	const MAX_SOURCE_LENGTH = 262144; // 256 KB limit per BBCode chunk

	/**
	* Create an opaque envelope array
	*
	* @param string $source
	* @param string $displayName
	* @param bool $isBlock
	* @param string $fingerprint
	* @return array
	*/
	public function createEnvelope($source, $displayName, $isBlock = false, $fingerprint = '')
	{
		return [
			'version' => self::SCHEMA_VERSION,
			'kind' => self::KIND,
			'source' => (string) $source,
			'displayName' => (string) $displayName,
			'isBlock' => (bool) $isBlock,
			'definitionFingerprint' => (string) $fingerprint,
		];
	}

	/**
	* Encode envelope payload array to a safe Base64 JSON string
	*
	* @param array $payload
	* @return string
	* @throws \InvalidArgumentException
	*/
	public function encodePayload(array $payload)
	{
		if (!isset($payload['version']) || $payload['version'] !== self::SCHEMA_VERSION)
		{
			throw new \InvalidArgumentException('Invalid envelope schema version.');
		}

		if (!isset($payload['kind']) || $payload['kind'] !== self::KIND)
		{
			throw new \InvalidArgumentException('Invalid envelope kind.');
		}

		if (!isset($payload['source']) || !is_string($payload['source']))
		{
			throw new \InvalidArgumentException('Envelope source must be a string.');
		}

		if (strlen($payload['source']) > self::MAX_SOURCE_LENGTH)
		{
			throw new \InvalidArgumentException('Envelope source exceeds maximum allowed size.');
		}

		if (!isset($payload['displayName']) || !is_string($payload['displayName']))
		{
			throw new \InvalidArgumentException('Envelope displayName must be a string.');
		}

		// Ensure valid UTF-8
		if (function_exists('mb_check_encoding') && !mb_check_encoding($payload['source'], 'UTF-8'))
		{
			throw new \InvalidArgumentException('Envelope source is not valid UTF-8.');
		}

		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false)
		{
			throw new \InvalidArgumentException('Failed to JSON encode envelope payload.');
		}

		return base64_encode($json);
	}

	/**
	* Decode and strictly validate a Base64 JSON envelope payload
	*
	* @param string $raw
	* @return array|null Null if invalid or corrupt
	*/
	public function decodePayload($raw)
	{
		if (!is_string($raw) || $raw === '')
		{
			return null;
		}

		// Base64 decode strictly
		$json = base64_decode($raw, true);
		if ($json === false)
		{
			return null;
		}

		$data = json_decode($json, true);
		if (!is_array($data))
		{
			return null;
		}

		if (!isset($data['version']) || $data['version'] !== self::SCHEMA_VERSION)
		{
			return null;
		}

		if (!isset($data['kind']) || $data['kind'] !== self::KIND)
		{
			return null;
		}

		if (!isset($data['source']) || !is_string($data['source']))
		{
			return null;
		}

		if (strlen($data['source']) > self::MAX_SOURCE_LENGTH)
		{
			return null;
		}

		if (!isset($data['displayName']) || !is_string($data['displayName']))
		{
			return null;
		}

		if (function_exists('mb_check_encoding') && !mb_check_encoding($data['source'], 'UTF-8'))
		{
			return null;
		}

		return [
			'version' => $data['version'],
			'kind' => $data['kind'],
			'source' => $data['source'],
			'displayName' => $data['displayName'],
			'isBlock' => !empty($data['isBlock']),
			'definitionFingerprint' => isset($data['definitionFingerprint']) && is_string($data['definitionFingerprint']) ? $data['definitionFingerprint'] : '',
		];
	}

	/**
	* Create a DOM element representing the opaque envelope for HTML/TipTap
	*
	* @param \DOMDocument $dom
	* @param array $payload
	* @return \DOMElement
	*/
	public function renderEnvelopeElement(\DOMDocument $dom, array $payload)
	{
		$isBlock = !empty($payload['isBlock']);
		$tag = $isBlock ? 'div' : 'span';
		$encoded = $this->encodePayload($payload);

		$element = $dom->createElement($tag);
		$element->setAttribute('data-opaque-bbcode', 'true');
		$element->setAttribute('data-opaque-payload', $encoded);

		$class = 'wysiwyg-opaque-bbcode';
		if ($isBlock)
		{
			$class .= ' wysiwyg-opaque-bbcode--block';
		}
		$element->setAttribute('class', $class);
		$element->setAttribute('contenteditable', 'false');

		// Safe textual presentation for fallback viewing / non-Tiptap preview
		$display_name = $payload['displayName'] !== '' ? $payload['displayName'] : 'bbcode';
		$element->textContent = '[' . $display_name . ']';

		return $element;
	}

	/**
	* Extract original BBCode source from a DOMNode if it contains an opaque envelope
	*
	* @param \DOMNode $node
	* @return string|null Null if not an opaque envelope or invalid
	* @throws \InvalidArgumentException If payload is present but corrupted/invalid
	*/
	public function extractSourceFromNode(\DOMNode $node)
	{
		if ($node->nodeType !== XML_ELEMENT_NODE)
		{
			return null;
		}

		/** @var \DOMElement $node */
		if (!$node->hasAttribute('data-opaque-payload') && $node->getAttribute('data-opaque-bbcode') !== 'true')
		{
			return null;
		}

		$payload_str = $node->getAttribute('data-opaque-payload');
		$payload = $this->decodePayload($payload_str);

		if ($payload === null)
		{
			throw new \InvalidArgumentException('Corrupted or invalid opaque BBCode envelope payload.');
		}

		return $payload['source'];
	}
}
