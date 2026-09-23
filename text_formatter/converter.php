<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\text_formatter;

class converter
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var string */
	protected $phpbb_root_path;

	/** @var \phpbb\textformatter\parser_interface */
	protected $parser;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $bbcodes_table;

	/** @var string */
	protected $smilies_table;

	/** @var array|null */
	protected $smilies_cache = null;

	/** @var array|null */
	protected $custom_bbcodes_cache = null;

	/** @var \phpbb\textformatter\utils_interface|null */
	protected $utils;

	/** @var opaque_bbcode_codec */
	protected $opaque_codec;

	/** @var custom_bbcode_catalog */
	protected $catalog;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface $db
	* @param \phpbb\config\config $config
	* @param string $phpbb_root_path
	* @param \phpbb\textformatter\parser_interface $parser
	* @param \phpbb\language\language $language
	* @param string $bbcodes_table
	* @param string $smilies_table
	* @param \phpbb\textformatter\utils_interface|null $utils
	* @param opaque_bbcode_codec|null $opaque_codec
	* @param custom_bbcode_catalog|null $catalog
	*/
	public function __construct($db, $config, $phpbb_root_path, $parser, $language, $bbcodes_table = '', $smilies_table = '', $utils = null, $opaque_codec = null, $catalog = null)
	{
		$this->db = $db;
		$this->config = $config;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->parser = $parser;
		$this->language = $language;
		$this->bbcodes_table = $bbcodes_table ?: (defined('BBCODES_TABLE') ? BBCODES_TABLE : '');
		$this->smilies_table = $smilies_table ?: (defined('SMILIES_TABLE') ? SMILIES_TABLE : '');
		$this->utils = $utils;
		$this->opaque_codec = $opaque_codec ?: new opaque_bbcode_codec();
		$this->catalog = $catalog ?: new custom_bbcode_catalog($this->db, $this->bbcodes_table);
	}

	/**
	* Get opaque BBCode codec
	*
	* @return opaque_bbcode_codec
	*/
	public function getOpaqueCodec()
	{
		if ($this->opaque_codec === null)
		{
			$this->opaque_codec = new opaque_bbcode_codec();
		}
		return $this->opaque_codec;
	}

	/**
	* Get custom BBCode catalog
	*
	* @return custom_bbcode_catalog
	*/
	public function getCatalog()
	{
		if ($this->catalog === null)
		{
			$this->catalog = new custom_bbcode_catalog($this->db, $this->bbcodes_table);
		}
		return $this->catalog;
	}

	/**
	* Get parser service (lazy loaded)
	*
	* @return \phpbb\textformatter\parser_interface
	*/
	protected function getParser()
	{
		return $this->parser;
	}

	/**
	* Convert BBCode to TipTap HTML
	*
	* @param string $bbcode
	* @param bool|null $is_trusted_xml
	* @return string
	*/
	public function toHtml($bbcode, $is_trusted_xml = null)
	{
		if (empty($bbcode))
		{
			return '';
		}

		// Check if trusted XML from internal phpBB caller
		$allow_xml = ($is_trusted_xml === null) ? $this->isS9eXml($bbcode) : ($is_trusted_xml && $this->isS9eXml($bbcode));
		if ($allow_xml)
		{
			return $this->xmlToHtml($bbcode);
		}

		// Parse BBCode to XML using phpBB parser
		$xml = $this->getParser()->parse($bbcode);

		// Convert XML to TipTap HTML
		return $this->xmlToHtml($xml);
	}

	/**
	* Check if a string is already s9e XML (starts with <r> or <t> root element)
	*
	* @param string $text
	* @return bool
	*/
	protected function isS9eXml($text)
	{
		$trimmed = ltrim($text);
		return (strncmp($trimmed, '<r>', 3) === 0 || strncmp($trimmed, '<t>', 3) === 0);
	}

	/**
	* Convert TipTap HTML back to BBCode
	*
	* @param string $html
	* @return string
	*/
	public function toBBCode($html)
	{
		if (empty($html))
		{
			return '';
		}

		$dom = new \DOMDocument();
		// Prevent loading external entities and parse safely
		libxml_use_internal_errors(true);

		// Wrap in a div to ensure a single root element and parse UTF-8 correctly
		$html = '<div>' . $html . '</div>';
		$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

		libxml_clear_errors();

		$bbcode = $this->htmlNodeToBBCode($dom->documentElement);

		// Clean up duplicate trailing newlines introduced by paragraph wrappers without altering protected envelope contents
		$bbcode = preg_replace('/(?:\r\n|\r|\n)+$/', '', $bbcode);

		return $bbcode;
	}

	/**
	* Convert s9e XML to clean HTML
	*
	* @param string $xml
	* @return string
	*/
	protected function xmlToHtml($xml)
	{
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		if (!$dom->loadXML($xml, LIBXML_NONET))
		{
			libxml_clear_errors();
			return '';
		}
		libxml_clear_errors();

		$html_dom = new \DOMDocument();
		$root = $html_dom->createElement('div');
		$html_dom->appendChild($root);

		$this->convertNodes($dom->documentElement, $html_dom, $root);

		// Structure inline elements and line breaks into proper TipTap paragraphs
		$tiptap_dom = new \DOMDocument();
		$tiptap_root = $tiptap_dom->createElement('div');
		$tiptap_dom->appendChild($tiptap_root);

		$is_block = function(\DOMNode $node) {
			if ($node->nodeType !== XML_ELEMENT_NODE)
			{
				return false;
			}
			$tag = strtolower($node->nodeName);
			return in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'div', 'table', 'ul', 'ol', 'pre', 'details', 'hr']);
		};

		$current_p = null;
		$flush_p = function() use (&$current_p, $tiptap_root) {
			if ($current_p !== null)
			{
				if ($current_p->hasChildNodes())
				{
					$tiptap_root->appendChild($current_p);
				}
				$current_p = null;
			}
		};

		$nodes = [];
		foreach ($root->childNodes as $child)
		{
			$nodes[] = $child;
		}

		foreach ($nodes as $child)
		{
			if ($child->nodeType === XML_ELEMENT_NODE && (strtolower($child->nodeName) === 'br'))
			{
				if ($current_p !== null)
				{
					// If inside a paragraph, the first <br> closes it to start the next line
					$flush_p();
				}
				else
				{
					// If outside any paragraph (already closed or after a block), this <br> is an explicit blank line!
					$empty_p = $tiptap_dom->createElement('p');
					$empty_p->appendChild($tiptap_dom->createElement('br'));
					$tiptap_root->appendChild($empty_p);
				}
				continue;
			}

			if ($is_block($child))
			{
				$flush_p();
				$tiptap_root->appendChild($tiptap_dom->importNode($child, true));
			}
			else
			{
				if ($current_p === null)
				{
					$current_p = $tiptap_dom->createElement('p');
				}
				$current_p->appendChild($tiptap_dom->importNode($child, true));
			}
		}
		$flush_p();

		// Output HTML content inside the wrapper div
		$html = '';
		foreach ($tiptap_root->childNodes as $child)
		{
			$html .= $tiptap_dom->saveHTML($child);
		}

		// Normalize any libxml whitespace formatting between block tags (e.g. PHP 7.2 libxml)
		$html = preg_replace('#<div>\s*<cite>#i', '<div><cite>', $html);

		return $html;
	}

	/**
	* Recursively convert s9e XML nodes to HTML elements
	*
	* @param \DOMNode $node
	* @param \DOMDocument $html_dom
	* @param \DOMNode $parent
	* @return void
	*/
	protected function convertNodes(\DOMNode $node, \DOMDocument $html_dom, \DOMNode $parent)
	{
		$last_was_br = false;

		foreach ($node->childNodes as $child)
		{
			if ($child->nodeType === XML_TEXT_NODE)
			{
				$text = $child->nodeValue;

				// In s9e XML, newlines immediately following <br/> are redundant formatting artifacts
				if ($last_was_br && strncmp($text, "\n", 1) === 0)
				{
					$text = substr($text, 1);
				}
				$last_was_br = false;

				if ($text === '')
				{
					continue;
				}

				$lines = explode("\n", $text);
				foreach ($lines as $index => $line)
				{
					if ($index > 0)
					{
						$parent->appendChild($html_dom->createElement('br'));
					}
					if ($line !== '')
					{
						$parent->appendChild($html_dom->createTextNode($line));
					}
				}
				continue;
			}

			if ($child->nodeType === XML_ELEMENT_NODE)
			{
				$tag_name = $child->nodeName;

				// Skip s9e delimiters
				if ($tag_name === 's' || $tag_name === 'e')
				{
					continue;
				}

				if ($tag_name === 'br' || $tag_name === 'BR')
				{
					$el = $html_dom->createElement('br');
					$parent->appendChild($el);
					$last_was_br = true;
					continue;
				}

				$last_was_br = false;

				switch ($tag_name)
				{
					case 'B':
						$el = $html_dom->createElement('strong');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'I':
						$el = $html_dom->createElement('em');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'U':
						$el = $html_dom->createElement('u');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'S':
						$el = $html_dom->createElement('s');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'COLOR':
						$color = $child->getAttribute('color');
						$el = $html_dom->createElement('span');
						$el->setAttribute('style', 'color: ' . $color);
						$el->setAttribute('data-bbcode', 'color');
						$el->setAttribute('data-bbcode-val', $color);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'SIZE':
						$size = $child->getAttribute('size');
						$el = $html_dom->createElement('span');
						$el->setAttribute('style', 'font-size: ' . $size . '%');
						$el->setAttribute('data-bbcode', 'size');
						$el->setAttribute('data-bbcode-val', $size);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'ALIGN':
						$align = $child->getAttribute('align');
						if (!$align && $child->hasAttribute('val'))
						{
							$align = $child->getAttribute('val');
						}
						if (!$align)
						{
							$align = 'center';
						}
						$el = $html_dom->createElement('div');
						$el->setAttribute('style', 'text-align: ' . $align . ';');
						$el->setAttribute('data-bbcode', 'align');
						$el->setAttribute('data-bbcode-val', $align);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'URL':
						$url = $child->getAttribute('url');
						$el = $html_dom->createElement('a');
						$el->setAttribute('href', $url);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'IMG':
						$src = $child->getAttribute('src');
						if (!$src)
						{
							$src = $child->textContent;
						}
						$el = $html_dom->createElement('img');
						$el->setAttribute('src', $src);
						$parent->appendChild($el);
						break;

					case 'LIST':
						$type = $child->getAttribute('type');
						if ($type)
						{
							$map = [
								'decimal'     => '1',
								'lower-alpha' => 'a',
								'upper-alpha' => 'A',
								'lower-roman' => 'i',
								'upper-roman' => 'I',
							];
							$html_type = isset($map[$type]) ? $map[$type] : $type;
							$el = $html_dom->createElement('ol');
							$el->setAttribute('type', $html_type);
							$el->setAttribute('data-bbcode', 'list');
							$el->setAttribute('data-bbcode-val', $html_type);
						}
						else
						{
							$el = $html_dom->createElement('ul');
							$el->setAttribute('data-bbcode', 'list');
						}
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'LI':
					case 'i': // s9e lists use <i> inside <LIST>
						$el = $html_dom->createElement('li');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'QUOTE':
						$author = $child->getAttribute('author');
						$el = $html_dom->createElement('blockquote');
						if ($child->hasAttribute('post_id'))
						{
							$el->setAttribute('data-post-id', $child->getAttribute('post_id'));
						}
						if ($child->hasAttribute('time'))
						{
							$el->setAttribute('data-time', $child->getAttribute('time'));
						}
						if ($child->hasAttribute('user_id'))
						{
							$el->setAttribute('data-user-id', $child->getAttribute('user_id'));
						}
						$div = $html_dom->createElement('div');
						if ($author)
						{
							$el->setAttribute('data-author', $author);
							$wrote = $this->language->lang('WROTE');
							$cite = $html_dom->createElement('cite', $author . ' ' . $wrote . ':');
							$div->appendChild($cite);
						}
						else
						{
							$el->setAttribute('class', 'uncited');
						}
						$el->appendChild($div);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $div);
						break;

					case 'CODE':
						$codebox = $html_dom->createElement('div');
						$codebox->setAttribute('class', 'codebox');

						$code_label = $this->language->is_set('WYSIWYG_CODE_LABEL') ? $this->language->lang('WYSIWYG_CODE_LABEL') : ($this->language->is_set('CODE') ? $this->language->lang('CODE') : 'Code');
						$select_all_label = $this->language->is_set('WYSIWYG_SELECT_ALL_CODE') ? $this->language->lang('WYSIWYG_SELECT_ALL_CODE') : ($this->language->is_set('SELECT_ALL_CODE') ? $this->language->lang('SELECT_ALL_CODE') : 'Select all');

						$p = $html_dom->createElement('p');
						$p->appendChild($html_dom->createTextNode($code_label . ': '));
						$a = $html_dom->createElement('a', $select_all_label);
						$a->setAttribute('href', '#');
						$a->setAttribute('onclick', 'selectCode(this); return false;');
						$p->appendChild($a);
						$codebox->appendChild($p);

						$pre = $html_dom->createElement('pre');
						$code_el = $html_dom->createElement('code');
						$pre->appendChild($code_el);
						$codebox->appendChild($pre);

						$parent->appendChild($codebox);
						$this->convertNodes($child, $html_dom, $code_el);
						break;

					case 'E': // Emoticon
						$smiley_code = $child->textContent;
						$smiley_url = $this->getSmileyUrl($smiley_code);
						if ($smiley_url)
						{
							$el = $html_dom->createElement('img');
							$el->setAttribute('class', 'smiley');
							$el->setAttribute('src', $smiley_url);
							$el->setAttribute('alt', $smiley_code);
							$el->setAttribute('data-smiley', $smiley_code);
							$parent->appendChild($el);
						}
						else
						{
							$parent->appendChild($html_dom->createTextNode($smiley_code));
						}
						break;

					case 'SPOILER':
						$el = $html_dom->createElement('details');
						$el->setAttribute('data-bbcode', 'spoiler');
						$summary = $html_dom->createElement('summary', 'Spoiler');
						$el->appendChild($summary);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'ATTACHMENT':
						$id = $child->getAttribute('id');
						$filename = $child->textContent;
						$el = $html_dom->createElement('div');
						$el->setAttribute('class', 'wysiwyg-attachment');
						$el->setAttribute('data-bbcode', 'attachment');
						$el->setAttribute('data-bbcode-val', $id);
						$el->setAttribute('data-filename', $filename);
						$el->appendChild($html_dom->createTextNode($filename));
						$parent->appendChild($el);
						break;

					case 'TABLE':
						$el = $html_dom->createElement('table');
						$el->setAttribute('data-bbcode', 'table');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'TR':
						$el = $html_dom->createElement('tr');
						$el->setAttribute('data-bbcode', 'tr');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'TD':
						$el = $html_dom->createElement('td');
						$el->setAttribute('data-bbcode', 'td');
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'HR':
						$el = $html_dom->createElement('hr');
						$el->setAttribute('data-bbcode', 'hr');
						$parent->appendChild($el);
						break;

					case 'ALIGN':
						$align = $child->getAttribute('align');
						if (!$align && $child->hasAttribute('val'))
						{
							$align = $child->getAttribute('val');
						}
						if (!$align)
						{
							$align = 'center';
						}

						// Create a temporary document fragment or parent
						$temp_el = $html_dom->createElement('div');
						$this->convertNodes($child, $html_dom, $temp_el);

						// If it only has block children, apply style to each. Otherwise wrap everything in a p.
						$has_inline = false;
						foreach ($temp_el->childNodes as $temp_child)
						{
							if ($temp_child->nodeType === XML_TEXT_NODE && trim($temp_child->nodeValue) !== '')
							{
								$has_inline = true;
								break;
							}
							if ($temp_child->nodeType === XML_ELEMENT_NODE)
							{
								$tag = strtolower($temp_child->nodeName);
								if (in_array($tag, ['span', 'a', 'strong', 'b', 'em', 'i', 'u', 's']))
								{
									$has_inline = true;
									break;
								}
							}
						}

						if ($has_inline || $temp_el->childNodes->length === 0)
						{
							$el = $html_dom->createElement('p');
							$el->setAttribute('style', 'text-align: ' . $align . ';');
							$el->setAttribute('data-bbcode', 'align');
							$el->setAttribute('data-bbcode-val', $align);
							while ($temp_el->childNodes->length > 0)
							{
								$el->appendChild($temp_el->firstChild);
							}
							$parent->appendChild($el);
						}
						else
						{
							// Apply text-align style to each child block
							while ($temp_el->childNodes->length > 0)
							{
								$block = $temp_el->firstChild;
								if ($block->nodeType === XML_ELEMENT_NODE)
								{
									$style = $block->getAttribute('style');
									$block->setAttribute('style', rtrim($style, '; ') . '; text-align: ' . $align . ';');
								}
								$parent->appendChild($block);
							}
						}
						break;

					case 'H1':
					case 'H2':
					case 'H3':
					case 'H4':
						$tag = strtolower($tag_name);
						$el = $html_dom->createElement($tag);
						$el->setAttribute('data-bbcode', $tag);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'SUP':
					case 'SUB':
						$tag = strtolower($tag_name);
						$el = $html_dom->createElement($tag);
						$el->setAttribute('data-bbcode', $tag);
						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;

					case 'br':
					case 'BR':
						$el = $html_dom->createElement('br');
						$parent->appendChild($el);
						break;

					default:
						$clean_tag = strtolower($tag_name);

						// Extract exact source for this custom BBCode subtree losslessly
						$source = $this->unparseXmlNode($child);

						$is_block = false;
						if ($this->getCatalog()->isBlock($clean_tag))
						{
							if ($parent->nodeName === 'div')
							{
								$is_block = true;
							}
						}

						$fingerprint = $this->getCatalog()->getFingerprint($clean_tag);
						$payload = $this->getOpaqueCodec()->createEnvelope($source, $clean_tag, $is_block, $fingerprint);
						$opaque_el = $this->getOpaqueCodec()->renderEnvelopeElement($html_dom, $payload);
						$parent->appendChild($opaque_el);
						break;
				}
			}
		}
	}

	/**
	* Recover exact BBCode source from an s9e XML node
	*
	* @param \DOMNode $node
	* @return string
	*/
	protected function unparseXmlNode(\DOMNode $node)
	{
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$imported = $doc->importNode($node, true);
		$doc->appendChild($imported);
		$node_xml = $doc->saveXML($imported);

		if ($this->utils !== null && method_exists($this->utils, 'unparse'))
		{
			try
			{
				return $this->utils->unparse('<r>' . $node_xml . '</r>');
			}
			catch (\Exception $e)
			{
				// Fallback to internal unparser
			}
		}

		if (class_exists('\\s9e\\TextFormatter\\Unparser'))
		{
			try
			{
				return \s9e\TextFormatter\Unparser::unparse('<r>' . $node_xml . '</r>');
			}
			catch (\Exception $e)
			{
				// Fallback to internal unparser
			}
		}

		return $this->fallbackUnparseNode($node);
	}

	/**
	* Fallback unparser using s9e <s> and <e> tags
	*
	* @param \DOMNode $node
	* @return string
	*/
	protected function fallbackUnparseNode(\DOMNode $node)
	{
		$s_node = null;
		$e_node = null;
		$inner = '';

		foreach ($node->childNodes as $child)
		{
			if ($child->nodeType === XML_ELEMENT_NODE)
			{
				$name = strtolower($child->nodeName);
				if ($name === 's')
				{
					$s_node = $child;
					continue;
				}
				if ($name === 'e')
				{
					$e_node = $child;
					continue;
				}
				$inner .= $this->fallbackUnparseNode($child);
			}
			else if ($child->nodeType === XML_TEXT_NODE)
			{
				$inner .= $child->nodeValue;
			}
		}

		if ($s_node !== null)
		{
			$start = $s_node->textContent;
			$end = $e_node !== null ? $e_node->textContent : '';
			return $start . $inner . $end;
		}

		$tag = strtolower($node->nodeName);
		$attrs_str = '';
		if ($node->hasAttributes())
		{
			foreach ($node->attributes as $attr)
			{
				if (strtolower($attr->name) === $tag)
				{
					$attrs_str = '=' . $attr->value . $attrs_str;
				}
				else
				{
					$attrs_str .= ' ' . $attr->name . '="' . $attr->value . '"';
				}
			}
		}

		return '[' . $tag . $attrs_str . ']' . $inner . '[/' . $tag . ']';
	}

	/**
	* Convert HTML nodes back to BBCode string
	*
	* @param \DOMNode $node
	* @return string
	*/
	protected function htmlNodeToBBCode(\DOMNode $node)
	{
		$bbcode = '';

		foreach ($node->childNodes as $child)
		{
			if ($child->nodeType === XML_TEXT_NODE)
			{
				$bbcode .= $child->nodeValue;
				continue;
			}

			if ($child->nodeType === XML_ELEMENT_NODE)
			{
				$tag_name = strtolower($child->nodeName);

				// Skip summary element inside details/spoiler
				if ($tag_name === 'summary')
				{
					continue;
				}

				// Check for opaque envelope FIRST (lossless protected custom BBCode)
				if ($child->hasAttribute('data-opaque-payload') || $child->getAttribute('data-opaque-bbcode') === 'true')
				{
					$source = $this->getOpaqueCodec()->extractSourceFromNode($child);
					if ($source !== null)
					{
						$bbcode .= $source;
						continue;
					}
				}

				// Custom BBCode via data-bbcode
				if ($child->hasAttribute('data-bbcode'))
				{
					$tag = strtolower($child->getAttribute('data-bbcode'));
					$val = $child->getAttribute('data-bbcode-val');
					$attrs_json = $child->getAttribute('data-bbcode-attrs');

					$attr_str = '';
					if ($attrs_json)
					{
						$attrs = json_decode($attrs_json, true);
						if (is_array($attrs))
						{
							foreach ($attrs as $name => $value)
							{
								$name_lower = strtolower($name);
								if ($tag === 'color' || $tag === 'size' || $tag === 'align')
								{
									$attr_str = '=' . $value;
								}
								else if ($name_lower === $tag)
								{
									$attr_str = '=' . $value . $attr_str;
								}
								else
								{
									$attr_str .= ' ' . $name . '="' . $value . '"';
								}
							}
						}
					}
					else if ($val !== '')
					{
						$attr_str = '=' . $val;
					}

					if ($tag === 'hr')
					{
						$bbcode .= '[hr]';
					}
					else
					{
						// Check if template had an inner content container marked with data-bbcode-content
						$content_container = null;
						if ($child->hasChildNodes())
						{
							foreach ($child->getElementsByTagName('*') as $descendant)
							{
								if ($descendant->getAttribute('data-bbcode-content') === 'true')
								{
									$content_container = $descendant;
									break;
								}
							}
						}

						$inner_source = $content_container ?: $child;
						$inner_content = $this->htmlNodeToBBCode($inner_source);
						$bbcode .= '[' . $tag . $attr_str . ']' . $inner_content . '[/' . $tag . ']';
					}
					continue;
				}

				switch ($tag_name)
				{
					case 'strong':
					case 'b':
						$bbcode .= '[b]' . $this->htmlNodeToBBCode($child) . '[/b]';
						break;

					case 'em':
					case 'i':
						$bbcode .= '[i]' . $this->htmlNodeToBBCode($child) . '[/i]';
						break;

					case 'u':
						$bbcode .= '[u]' . $this->htmlNodeToBBCode($child) . '[/u]';
						break;

					case 's':
					case 'del':
						$bbcode .= '[s]' . $this->htmlNodeToBBCode($child) . '[/s]';
						break;

					case 'blockquote':
						$author = $child->getAttribute('data-author');
						$author_attr = $author ? '="' . $author . '"' : '';

						$extra_attrs = '';
						if ($child->hasAttribute('data-post-id'))
						{
							$extra_attrs .= ' post_id=' . $child->getAttribute('data-post-id');
						}
						if ($child->hasAttribute('data-time'))
						{
							$extra_attrs .= ' time=' . $child->getAttribute('data-time');
						}
						if ($child->hasAttribute('data-user-id'))
						{
							$extra_attrs .= ' user_id=' . $child->getAttribute('data-user-id');
						}

						$clone = $child->cloneNode(true);
						$cites = $clone->getElementsByTagName('cite');
						while ($cites->length > 0)
						{
							$cite = $cites->item(0);
							$cite->parentNode->removeChild($cite);
						}

						$inner_bbcode = $this->htmlNodeToBBCode($clone);
						$inner_bbcode = trim($inner_bbcode);

						$bbcode .= '[quote' . $author_attr . $extra_attrs . ']' . $inner_bbcode . '[/quote]';
						break;

					case 'pre':
						$code_child = null;
						foreach ($child->childNodes as $sub_child)
						{
							if ($sub_child->nodeType === XML_ELEMENT_NODE && strtolower($sub_child->nodeName) === 'code')
							{
								$code_child = $sub_child;
								break;
							}
						}
						if ($code_child)
						{
							$bbcode .= '[code]' . $code_child->textContent . '[/code]';
						}
						else
						{
							$bbcode .= '[code]' . $child->textContent . '[/code]';
						}
						break;

					case 'code':
						$bbcode .= '[code]' . $child->textContent . '[/code]';
						break;

					case 'ul':
						$bbcode .= '[list]' . $this->htmlNodeToBBCode($child) . '[/list]';
						break;

					case 'ol':
						$type = $child->getAttribute('type');
						if ($type)
						{
							$map = [
								'1'           => '1',
								'decimal'     => '1',
								'a'           => 'a',
								'lower-alpha' => 'a',
								'A'           => 'A',
								'upper-alpha' => 'A',
								'i'           => 'i',
								'lower-roman' => 'i',
								'I'           => 'I',
								'upper-roman' => 'I',
							];
							$marker = isset($map[$type]) ? $map[$type] : $type;
							$bbcode .= '[list=' . $marker . ']' . $this->htmlNodeToBBCode($child) . '[/list]';
						}
						else
						{
							$bbcode .= '[list=1]' . $this->htmlNodeToBBCode($child) . '[/list]';
						}
						break;

					case 'li':
						$bbcode .= '[*]' . $this->htmlNodeToBBCode($child) . "\n";
						break;

					case 'a':
						$href = $child->getAttribute('href');
						$content = $this->htmlNodeToBBCode($child);
						if ($href === $content)
						{
							$bbcode .= '[url]' . $href . '[/url]';
						}
						else
						{
							$bbcode .= '[url=' . $href . ']' . $content . '[/url]';
						}
						break;

					case 'img':
						if ($child->getAttribute('data-smiley'))
						{
							$bbcode .= $child->getAttribute('data-smiley');
						}
						else
						{
							$bbcode .= '[img]' . $child->getAttribute('src') . '[/img]';
						}
						break;

					case 'p':
						$style = $child->getAttribute('style');
						$inner = $this->htmlNodeToBBCode($child);
						$inner = rtrim($inner, "\r\n");
						if ($style && preg_match('/text-align:\s*([^;]+)/', $style, $matches))
						{
							$align = trim($matches[1]);
							$bbcode .= '[align=' . $align . ']' . $inner . '[/align]' . "\n";
						}
						else
						{
							$bbcode .= $inner . "\n";
						}
						break;

					case 'br':
						$bbcode .= "\n";
						break;

					case 'span':
						$style = $child->getAttribute('style');
						$inner = $this->htmlNodeToBBCode($child);

						if (preg_match('/color:\s*([^;]+)/', $style, $matches))
						{
							$color = trim($matches[1]);
							$inner = '[color=' . $color . ']' . $inner . '[/color]';
						}

						if (preg_match('/font-size:\s*(\d+)%/', $style, $matches))
						{
							$size = $matches[1];
							$inner = '[size=' . $size . ']' . $inner . '[/size]';
						}
						else if (preg_match('/font-size:\s*(\d+)px/', $style, $matches))
						{
							$px = $matches[1];
							$percentage = round(($px / 12) * 100);
							$inner = '[size=' . $percentage . ']' . $inner . '[/size]';
						}

						if ($style && preg_match('/text-align:\s*([^;]+)/', $style, $matches))
						{
							$align = trim($matches[1]);
							$inner = '[align=' . $align . ']' . $inner . '[/align]';
						}

						$bbcode .= $inner;
						break;

					case 'details':
						if ($child->getAttribute('data-bbcode') === 'spoiler')
						{
							$inner_bbcode = '';
							foreach ($child->childNodes as $sub_child)
							{
								if ($sub_child->nodeType === XML_ELEMENT_NODE && strtolower($sub_child->nodeName) === 'summary')
								{
									continue;
								}
								$inner_bbcode .= $this->htmlNodeToBBCode($sub_child);
							}
							$bbcode .= '[spoiler]' . $inner_bbcode . '[/spoiler]';
						}
						else
						{
							$bbcode .= $this->htmlNodeToBBCode($child);
						}
						break;

					case 'div':
						$style = $child->getAttribute('style');
						$inner = '';
						if ($child->getAttribute('data-bbcode') === 'attachment')
						{
							$id = $child->getAttribute('data-bbcode-val');
							$filename = $child->getAttribute('data-filename') ?: $child->textContent;
							if (strpos($filename, '📎 ') === 0)
							{
								$filename = substr($filename, 4);
							}
							$inner = '[attachment=' . $id . ']' . $filename . '[/attachment]';
						}
						else if ($child->getAttribute('class') === 'codebox')
						{
							$code_el = $child->getElementsByTagName('code')->item(0);
							if ($code_el)
							{
								$inner = '[code]' . $code_el->textContent . '[/code]';
							}
							else
							{
								$inner = '[code]' . $this->htmlNodeToBBCode($child) . '[/code]';
							}
						}
						else
						{
							$inner = $this->htmlNodeToBBCode($child);
						}

						if ($style && preg_match('/text-align:\s*([^;]+)/', $style, $matches))
						{
							$align = trim($matches[1]);
							$bbcode .= '[align=' . $align . ']' . $inner . '[/align]';
						}
						else
						{
							$bbcode .= $inner;
						}
						break;

					case 'h1':
					case 'h2':
					case 'h3':
					case 'h4':
						$tag = strtolower($tag_name);
						$style = $child->getAttribute('style');
						$inner = $this->htmlNodeToBBCode($child);
						if ($style && preg_match('/text-align:\s*([^;]+)/', $style, $matches))
						{
							$align = trim($matches[1]);
							$bbcode .= '[align=' . $align . '][' . $tag . ']' . $inner . '[/' . $tag . '][/align]';
						}
						else
						{
							$bbcode .= '[' . $tag . ']' . $inner . '[/' . $tag . ']';
						}
						break;

					case 'sup':
					case 'sub':
						$tag = strtolower($tag_name);
						$bbcode .= '[' . $tag . ']' . $this->htmlNodeToBBCode($child) . '[/' . $tag . ']';
						break;

					case 'table':
						$bbcode .= '[table]' . $this->htmlNodeToBBCode($child) . '[/table]';
						break;

					case 'tr':
						$bbcode .= '[tr]' . $this->htmlNodeToBBCode($child) . '[/tr]';
						break;

					case 'td':
						$bbcode .= '[td]' . $this->htmlNodeToBBCode($child) . '[/td]';
						break;

					case 'hr':
						$bbcode .= '[hr]';
						break;

					default:
						$bbcode .= $this->htmlNodeToBBCode($child);
						break;
				}
			}
		}

		return $bbcode;
	}

	/**
	* Resolve smiley code to url path
	*
	* @param string $code
	* @return string
	*/
	protected function getSmileyUrl($code)
	{
		if ($this->smilies_cache === null)
		{
			$this->smilies_cache = [];
			$table = $this->smilies_table ?: (defined('SMILIES_TABLE') ? SMILIES_TABLE : '');

			if ($table)
			{
				try
				{
					$sql = 'SELECT code, smiley_url FROM ' . $table;
					$result = $this->db->sql_query($sql);
					while ($row = $this->db->sql_fetchrow($result))
					{
						$this->smilies_cache[$row['code']] = $row['smiley_url'];
					}
					$this->db->sql_freeresult($result);
				}
				catch (\Exception $e)
				{
					// Silence error and use defaults if db is not connected
				}
			}
		}

		if (isset($this->smilies_cache[$code]))
		{
			$smilies_path = isset($this->config['smilies_path']) ? $this->config['smilies_path'] : 'images/smilies';
			$script_path = isset($this->config['script_path']) ? rtrim($this->config['script_path'], '/') : '';
			return $script_path . '/' . $smilies_path . '/' . $this->smilies_cache[$code];
		}

		return '';
	}

	/**
	* Load and cache custom bbcodes from database
	*
	* @return array
	*/
	public function loadCustomBBCodes()
	{
		if ($this->custom_bbcodes_cache !== null)
		{
			return $this->custom_bbcodes_cache;
		}

		$this->custom_bbcodes_cache = [];
		$table = $this->bbcodes_table ?: (defined('BBCODES_TABLE') ? BBCODES_TABLE : '');
		if (!$table)
		{
			return $this->custom_bbcodes_cache;
		}

		try
		{
			$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_helpline, display_on_posting, bbcode_match, bbcode_tpl
					FROM ' . $table . '
					ORDER BY bbcode_tag ASC';
			$result = $this->db->sql_query($sql);
			if ($result)
			{
				while ($row = $this->db->sql_fetchrow($result))
				{
					$tag = $row['bbcode_tag'];
					$clean_tag = strtolower(rtrim($tag, '='));
					$has_val = (strpos($tag, '=') !== false ||
						strpos($row['bbcode_match'], '=' . $clean_tag) !== false ||
						strpos($row['bbcode_match'], '=' . strtoupper($clean_tag)) !== false ||
						strpos($row['bbcode_match'], '={') !== false);

					$this->custom_bbcodes_cache[$clean_tag] = [
						'id'                  => (int) $row['bbcode_id'],
						'name'                => $clean_tag,
						'tag'                 => $clean_tag,
						'helpline'            => !empty($row['bbcode_helpline']) ? $row['bbcode_helpline'] : '[' . $clean_tag . ']',
						'display_on_posting'  => (int) $row['display_on_posting'],
						'has_val'             => $has_val,
						'match'               => $row['bbcode_match'],
						'tpl'                 => $row['bbcode_tpl'],
					];
				}
				$this->db->sql_freeresult($result);
			}
		}
		catch (\Exception $e)
		{
			// Return empty array if table not available or error occurs
		}

		return $this->custom_bbcodes_cache;
	}

	/**
	* Get active custom BBCodes configured in phpBB for display on posting
	*
	* @return array
	*/
	public function getCustomBBCodes()
	{
		$all = $this->loadCustomBBCodes();
		$excluded = [
			'b', 'i', 'u', 's', 'quote', 'code', 'list', 'img', 'url', 'flash', 'size', 'color', 'email',
			'align', 'h1', 'h2', 'h3', 'h4', 'highlight', 'sub', 'sup', 'hr', 'table', 'tr', 'td', 'attachment', 'spoiler'
		];

		$posting_bbcodes = [];
		$catalog_defs = $this->getCatalog()->getDefinitions();

		foreach ($all as $clean_tag => $data)
		{
			if ($data['display_on_posting'] === 1 && !in_array($clean_tag, $excluded))
			{
				$cat_def = isset($catalog_defs[$clean_tag]) ? $catalog_defs[$clean_tag] : null;
				$posting_bbcodes[] = [
					'id'          => $data['id'],
					'name'        => $data['name'],
					'tag'         => $data['tag'],
					'helpline'    => $data['helpline'],
					'has_val'     => $data['has_val'],
					'tpl'         => $data['tpl'],
					'match'       => $data['match'],
					'is_block'    => $cat_def ? $cat_def['is_block'] : false,
					'fingerprint' => $cat_def ? $cat_def['fingerprint'] : '',
				];
			}
		}
		return $posting_bbcodes;
	}
}
