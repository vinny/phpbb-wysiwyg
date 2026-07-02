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

	/** @var \Symfony\Component\DependencyInjection\ContainerInterface */
	protected $container;

	/** @var array|null */
	protected $smilies_cache = null;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface $db
	* @param \phpbb\config\config $config
	* @param string $phpbb_root_path
	* @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	*/
	public function __construct($db, $config, $phpbb_root_path, $container)
	{
		$this->db = $db;
		$this->config = $config;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->container = $container;
	}

	/**
	* Get parser service (lazy loaded)
	*
	* @return \phpbb\textformatter\parser_interface
	*/
	protected function getParser()
	{
		if ($this->parser === null)
		{
			$this->parser = $this->container->get('text_formatter.parser');
		}
		return $this->parser;
	}

	/**
	* Convert BBCode to TipTap HTML
	*
	* @param string $bbcode
	* @return string
	*/
	public function toHtml($bbcode)
	{
		if (empty($bbcode))
		{
			return '';
		}

		// Parse BBCode to XML using phpBB parser
		$xml = $this->getParser()->parse($bbcode);

		// Convert XML to TipTap HTML
		return $this->xmlToHtml($xml);
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

		// Clean up duplicate newlines at the end or normalize them
		$bbcode = trim($bbcode);

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
		if (!$dom->loadXML($xml, LIBXML_NOBLANKS))
		{
			libxml_clear_errors();
			return '';
		}
		libxml_clear_errors();

		$html_dom = new \DOMDocument();
		$root = $html_dom->createElement('div');
		$html_dom->appendChild($root);

		$this->convertNodes($dom->documentElement, $html_dom, $root);

		// Output HTML content inside the wrapper div
		$html = '';
		foreach ($root->childNodes as $child)
		{
			$html .= $html_dom->saveHTML($child);
		}

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
		foreach ($node->childNodes as $child)
		{
			if ($child->nodeType === XML_TEXT_NODE)
			{
				$text = $child->nodeValue;
				$lines = explode("\n", $text);
				foreach ($lines as $index => $line)
				{
					if ($index > 0)
					{
						$parent->appendChild($html_dom->createElement('br'));
					}
					$parent->appendChild($html_dom->createTextNode($line));
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
						$el = $html_dom->createElement('div');
						$el->setAttribute('style', 'text-align: ' . $align);
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
						$div = $html_dom->createElement('div');
						if ($author)
						{
							$el->setAttribute('data-author', $author);
							$wrote = 'escreveu';
							if ($this->container && method_exists($this->container, 'get'))
							{
								$lang_srv = $this->container->get('language');
								if ($lang_srv && method_exists($lang_srv, 'lang'))
								{
									$wrote = $lang_srv->lang('WROTE');
								}
							}
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

						$p = $html_dom->createElement('p');
						$p->appendChild($html_dom->createTextNode('Código: '));
						$a = $html_dom->createElement('a', 'Selecionar todos');
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
						// Custom BBCode mapping
						$el = $html_dom->createElement('span');
						$el->setAttribute('data-bbcode', strtolower($tag_name));

						$attrs = [];
						foreach ($child->attributes as $attr)
						{
							$attrs[$attr->name] = $attr->value;
						}
						if (!empty($attrs))
						{
							$first_attr_val = reset($attrs);
							$el->setAttribute('data-bbcode-val', $first_attr_val);
							$el->setAttribute('data-bbcode-attrs', json_encode($attrs));
						}

						$parent->appendChild($el);
						$this->convertNodes($child, $html_dom, $el);
						break;
				}
			}
		}
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
						$inner_content = $this->htmlNodeToBBCode($child);
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

						$clone = $child->cloneNode(true);
						$cites = $clone->getElementsByTagName('cite');
						while ($cites->length > 0)
						{
							$cite = $cites->item(0);
							$cite->parentNode->removeChild($cite);
						}

						$div = $clone->getElementsByTagName('div')->item(0);
						if ($div)
						{
							$inner_bbcode = $this->htmlNodeToBBCode($div);
						}
						else
						{
							$inner_bbcode = $this->htmlNodeToBBCode($clone);
						}

						$bbcode .= '[quote' . $author_attr . ']' . $inner_bbcode . '[/quote]';
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

			// Define constant SMILIES_TABLE if not loaded yet (fallback)
			$table = defined('SMILIES_TABLE') ? SMILIES_TABLE : 'phpbb_smilies';

			// Defensive query checking
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

		if (isset($this->smilies_cache[$code]))
		{
			$smilies_path = isset($this->config['smilies_path']) ? $this->config['smilies_path'] : 'images/smilies';
			$script_path = isset($this->config['script_path']) ? rtrim($this->config['script_path'], '/') : '';
			return $script_path . '/' . $smilies_path . '/' . $this->smilies_cache[$code];
		}

		return '';
	}
}
