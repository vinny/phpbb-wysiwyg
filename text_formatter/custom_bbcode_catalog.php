<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\text_formatter;

class custom_bbcode_catalog
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $bbcodes_table;

	/** @var \phpbb\cache\driver\driver_interface|null */
	protected $cache;

	/** @var array|null Keyed by normalized tag name (without trailing '=') */
	protected $catalog = null;

	/** @var array|null All definitions keyed by bbcode_id (preserves duplicate tag / tag= rows) */
	protected $definitions_by_id = null;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface $db
	* @param string $bbcodes_table
	* @param \phpbb\cache\driver\driver_interface|null $cache
	*/
	public function __construct($db, $bbcodes_table = '', $cache = null)
	{
		$this->db = $db;
		$this->bbcodes_table = $bbcodes_table ?: (defined('BBCODES_TABLE') ? BBCODES_TABLE : '');
		$this->cache = $cache;
	}

	/**
	* Clear both in-memory and phpBB SQL cache for the BBCodes table.
	*
	* @return void
	*/
	public function clearCache()
	{
		$this->catalog = null;
		$this->definitions_by_id = null;

		if ($this->cache !== null && $this->bbcodes_table)
		{
			$this->cache->destroy('sql', $this->bbcodes_table);
		}
	}

	/**
	* Load all custom BBCode definitions from database (cached per request)
	*
	* @return array
	*/
	public function getDefinitions()
	{
		if ($this->catalog !== null)
		{
			return $this->catalog;
		}

		$this->catalog = [];
		$this->definitions_by_id = [];
		if (!$this->bbcodes_table)
		{
			return $this->catalog;
		}

		try
		{
			$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_helpline, bbcode_match, bbcode_tpl, display_on_posting
					FROM ' . $this->bbcodes_table . '
					ORDER BY bbcode_tag ASC';
			$result = $this->db->sql_query($sql, 3600);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$raw_tag = isset($row['bbcode_tag']) ? trim($row['bbcode_tag']) : '';
				$clean_tag = strtolower(rtrim($raw_tag, '='));
				$tpl = isset($row['bbcode_tpl']) ? trim($row['bbcode_tpl']) : '';
				$match = isset($row['bbcode_match']) ? trim($row['bbcode_match']) : '';

				$is_block = $this->detectIsBlock($tpl);
				$schema = $this->parseMatch($match, $tpl, $clean_tag);
				$fingerprint = md5($clean_tag . '|' . $match . '|' . $tpl);

				$entry = [
					'id'                 => (int) $row['bbcode_id'],
					'tag'                => $clean_tag,
					'raw_tag'            => $raw_tag,
					'helpline'           => isset($row['bbcode_helpline']) ? $row['bbcode_helpline'] : '',
					'match'              => $match,
					'tpl'                => $tpl,
					'display_on_posting' => !empty($row['display_on_posting']),
					'is_block'           => $is_block,
					'fingerprint'        => $fingerprint,
					'schema'             => $schema,
				];

				$this->definitions_by_id[(int) $row['bbcode_id']] = $entry;

				// Keep first definition or prefer the one with display_on_posting / richer match
				if (!isset($this->catalog[$clean_tag]))
				{
					$this->catalog[$clean_tag] = $entry;
				}
			}
			$this->db->sql_freeresult($result);
		}
		catch (\Exception $e)
		{
			// Database query failed or table not found (e.g. mock DB or setup), leave empty
		}

		return $this->catalog;
	}

	/**
	* Detect whether a BBCode HTML replacement produces a block-level structure
	*
	* @param string $tpl
	* @return bool
	*/
	public function detectIsBlock($tpl)
	{
		if ($tpl === '')
		{
			return false;
		}

		return (bool) preg_match(
			'#^(?:<!--.*?-->\s*)*<(div|blockquote|table|pre|p|section|article|aside|figure|h[1-6]|ul|ol|details|iframe|video|audio|hr|fieldset|dl|center|header|footer|main|nav)\b#is',
			trim($tpl)
		);
	}

	/**
	* Parse a phpBB 3.3 / s9e\TextFormatter bbcode_match string into a structured schema
	*
	* Supports all official BBCodeMonkey patterns:
	* - Simple wrappers: [center]{TEXT}[/center]
	* - Default attribute: [font={SIMPLETEXT}]{TEXT}[/font], [spoiler={TEXT1?}]{TEXT2}[/spoiler]
	* - Composite default attribute: [flash={NUMBER1},{NUMBER2}]{URL}[/flash]
	* - Named attributes: [anchor name={IDENTIFIER} visible={CHOICE=yes;optional}]{TEXT}[/anchor]
	* - Composite content: [badge]{TEXT1}-{TEXT2}-{TEXT3}[/badge], [youtube]https://...={SIMPLETEXT}[/youtube]
	* - Void / self-closing / empty content: [hr], [icon={IDENTIFIER}/], [clear][/clear]
	* - s9e options ($...) and rules (#...) are ignored as user inputs
	*
	* @param string $usage
	* @param string $tpl
	* @param string $fallback_tag
	* @return array
	*/
	public function parseMatch($usage, $tpl = '', $fallback_tag = '')
	{
		$usage = trim((string) $usage);
		$tpl = trim((string) $tpl);

		$schema = [
			'tag'              => strtolower(rtrim(trim($fallback_tag), '=')),
			'has_close_tag'    => false,
			'self_closing'     => false,
			'default_attr'     => null,
			'attributes'       => [],
			'content'          => null,
			'fields'           => [],
			'can_inline_edit'  => false,
			'inline_edit_meta' => null,
		];

		if ($usage === '')
		{
			return $schema;
		}

		// Protect REGEXP / PARSE / MAP / HASHMAP definitions from whitespace/delimiter splitting
		$protected = [];
		$protected_usage = preg_replace_callback(
			'#\{(?:HASH)?MAP=[^}]+\}|\{(?:PARSE|REGEXP)=[^}]+\}#i',
			function ($m) use (&$protected)
			{
				$key = '__PROTECTED_' . count($protected) . '__';
				$protected[$key] = $m[0];
				return $key;
			},
			$usage
		);

		$restore = function ($str) use ($protected)
		{
			return empty($protected) ? $str : strtr($str, $protected);
		};

		// Official BBCodeMonkey grammar regex
		$regexp = '(^'
			. '\\[(?<bbcodeName>[a-zA-Z0-9_-]+)'
			. '(?<defaultAttribute>=[^\\s\\]/]+(?:\\s+[^\\s=\\]/]+)*)?'
			. '(?<attributes>(?:\\s+[^=\\s\\]/]+=[^\\s\\]/]+)*)?'
			. '\\s*(?:(?<selfClosing>/?)\\]|\\]\\s*(?<content>.*?)\\s*(?<endTag>\\[/\\1\\]))'
			. '$)is';

		if (!preg_match($regexp, $protected_usage, $m))
		{
			// Fallback for unusual definitions: check if closing tag exists
			$tag_name = $schema['tag'];
			if ($tag_name !== '' && stripos($usage, '[/' . $tag_name . ']') !== false)
			{
				$schema['has_close_tag'] = true;
			}
			return $schema;
		}

		$schema['tag'] = strtolower($m['bbcodeName']);
		$schema['has_close_tag'] = !empty($m['endTag']);
		$schema['self_closing'] = isset($m['selfClosing']) && $m['selfClosing'] === '/';

		$fields = [];

		// 1. Default attribute ([tag=...])
		if (!empty($m['defaultAttribute']))
		{
			$raw_def = trim($restore(substr($m['defaultAttribute'], 1)), '"');
			$parts = $this->parseParts($raw_def, 'default_attr', $schema['tag'], $fields);
			$schema['default_attr'] = [
				'raw'   => $raw_def,
				'parts' => $parts,
			];
		}

		// 2. Named attributes ([tag attr1=... attr2=...])
		if (!empty($m['attributes']))
		{
			$attr_chunks = preg_split('#\s+#', trim($m['attributes']), -1, PREG_SPLIT_NO_EMPTY);
			foreach ($attr_chunks as $chunk)
			{
				$eq_pos = strpos($chunk, '=');
				if ($eq_pos === false)
				{
					continue;
				}
				$attr_name = substr($chunk, 0, $eq_pos);
				$attr_val = trim($restore(substr($chunk, $eq_pos + 1)), '"');

				// Skip s9e BBCode options ($...) and tag rules (#...)
				if ($attr_name === '' || $attr_name[0] === '$' || $attr_name[0] === '#')
				{
					continue;
				}

				$clean_attr_name = strtolower($attr_name);
				$parts = $this->parseParts($attr_val, 'named_attr', $clean_attr_name, $fields);
				$schema['attributes'][] = [
					'name'  => $clean_attr_name,
					'raw'   => $attr_val,
					'parts' => $parts,
				];
			}
		}

		// 3. Content between ] and [/tag]
		if ($schema['has_close_tag'])
		{
			$raw_content = $restore(isset($m['content']) ? $m['content'] : '');
			if ($raw_content !== '')
			{
				$is_passthrough = (bool) preg_match('/^\{(?:TEXT|ANYTHING)[0-9]*\}$/', $raw_content);
				$parts = $this->parseParts($raw_content, 'content', '', $fields);
				$schema['content'] = [
					'raw'            => $raw_content,
					'is_passthrough' => $is_passthrough,
					'parts'          => $parts,
				];
			}
			else
			{
				$schema['content'] = [
					'raw'            => '',
					'is_passthrough' => false,
					'parts'          => [],
				];
			}
		}

		$schema['fields'] = $fields;

		// 4. Evaluate Tier 1 (safe in-place editable wrapper) eligibility
		$inline_meta = $this->analyzeInlineEditEligibility($schema, $tpl);
		if ($inline_meta !== null)
		{
			$schema['can_inline_edit'] = true;
			$schema['inline_edit_meta'] = $inline_meta;
		}

		return $schema;
	}

	/**
	* Split a raw attribute/content pattern into literal and token parts, and register user fields
	*
	* @param string $raw
	* @param string $location 'default_attr'|'named_attr'|'content'
	* @param string $attr_name
	* @param array &$fields
	* @return array
	*/
	protected function parseParts($raw, $location, $attr_name, array &$fields)
	{
		$parts = [];
		$offset = 0;
		$len = strlen($raw);

		if (preg_match_all('/\{([A-Z_]+[0-9]*)([^}]*)\}/', $raw, $matches, PREG_OFFSET_CAPTURE))
		{
			$token_count = count($matches[0]);
			for ($i = 0; $i < $token_count; $i++)
			{
				$full_match = $matches[0][$i][0];
				$match_pos = $matches[0][$i][1];
				$token_id = $matches[1][$i][0];
				$modifiers_raw = $matches[2][$i][0];

				if ($match_pos > $offset)
				{
					$parts[] = [
						'type'  => 'literal',
						'value' => substr($raw, $offset, $match_pos - $offset),
					];
				}

				$token_meta = $this->parseTokenMeta($token_id, $modifiers_raw, $location, $attr_name, count($fields));

				// Detect static prefix when there is a single token preceded by a literal (e.g. YouTube URL)
				if ($token_count === 1 && $match_pos > 0 && ($match_pos + strlen($full_match)) === $len)
				{
					$token_meta['static_prefix'] = substr($raw, 0, $match_pos);
				}

				// Skip pure {PARSE=...} tokens or ;useContent tokens that don't require separate user input
				if ($token_meta['token_type'] !== 'PARSE' && !$token_meta['use_content'])
				{
					$fields[] = $token_meta;
				}

				$parts[] = [
					'type'  => 'token',
					'key'   => $token_meta['key'],
					'token' => $token_meta,
				];

				$offset = $match_pos + strlen($full_match);
			}
		}

		if ($offset < $len)
		{
			$parts[] = [
				'type'  => 'literal',
				'value' => substr($raw, $offset),
			];
		}

		return $parts;
	}

	/**
	* Parse individual token metadata (type, optional, choices, range, defaultValue, etc.)
	*
	* @param string $token_id e.g. TEXT1, CHOICE, RANGE, NUMBER2
	* @param string $modifiers_raw e.g. '?', '=yes,no;optional', '=10,32', ';defaultValue=5'
	* @param string $location
	* @param string $attr_name
	* @param int $field_index
	* @return array
	*/
	protected function parseTokenMeta($token_id, $modifiers_raw, $location, $attr_name, $field_index)
	{
		$token_type = preg_replace('/[0-9]+$/', '', $token_id);
		$optional = false;
		$default_value = '';
		$choices = [];
		$range = null;
		$use_content = false;

		$mod = $modifiers_raw;
		if (substr($mod, -1) === '?')
		{
			$optional = true;
			$mod = substr($mod, 0, -1);
		}

		// Split leading =arg from ;options
		$arg_val = '';
		$options_str = '';
		if ($mod !== '')
		{
			$semi_pos = strpos($mod, ';');
			if ($semi_pos !== false)
			{
				$leading = substr($mod, 0, $semi_pos);
				$options_str = substr($mod, $semi_pos + 1);
			}
			else
			{
				$leading = $mod;
			}

			if ($leading !== '' && $leading[0] === '=')
			{
				$arg_val = substr($leading, 1);
			}
		}

		if ($options_str !== '')
		{
			$opt_parts = explode(';', $options_str);
			foreach ($opt_parts as $opt)
			{
				$opt = trim($opt);
				if ($opt === '')
				{
					continue;
				}
				if (strtolower($opt) === 'optional')
				{
					$optional = true;
				}
				else if (strtolower($opt) === 'usecontent')
				{
					$use_content = true;
				}
				else if (stripos($opt, 'defaultValue=') === 0)
				{
					$default_value = substr($opt, 13);
					$optional = true;
				}
			}
		}

		if ($token_type === 'CHOICE' && $arg_val !== '')
		{
			$choices = array_values(array_filter(array_map('trim', explode(',', $arg_val)), function ($v) {
				return $v !== '';
			}));
		}
		else if ($token_type === 'RANGE' && $arg_val !== '')
		{
			$bounds = explode(',', $arg_val);
			if (count($bounds) >= 2)
			{
				$range = [(float) trim($bounds[0]), (float) trim($bounds[1])];
			}
		}

		$is_multiline = ($location === 'content' && in_array($token_type, ['TEXT', 'ANYTHING'], true));

		// Build a readable label
		if ($location === 'named_attr' && $attr_name !== '')
		{
			$label = ucfirst($attr_name);
		}
		else if ($location === 'default_attr')
		{
			$label = ($attr_name !== '') ? ucfirst($attr_name) : $token_id;
		}
		else
		{
			$label = $token_id;
		}

		return [
			'key'           => $location . '_' . ($attr_name !== '' ? $attr_name . '_' : '') . $field_index,
			'token_id'      => $token_id,
			'token_type'    => $token_type,
			'location'      => $location,
			'attr_name'     => $attr_name,
			'label'         => $label,
			'optional'      => $optional,
			'default_value' => $default_value,
			'choices'       => $choices,
			'range'         => $range,
			'use_content'   => $use_content,
			'multiline'     => $is_multiline,
			'static_prefix' => '',
		];
	}

	/**
	* Determine if a custom BBCode can be safely edited in-place (Tier 1)
	*
	* Requirements:
	* - Must have a closing tag and a single passthrough content token ({TEXT}/{ANYTHING})
	* - Must have a single safe wrapper root HTML tag containing ONLY the passthrough token
	* - Must NOT contain scripts, iframes, media tags, XSLT directives, or JS event handlers
	* - Must NOT have composite attributes or multiple tokens in the same attribute
	*
	* @param array $schema
	* @param string $tpl
	* @return array|null
	*/
	protected function analyzeInlineEditEligibility(array $schema, $tpl)
	{
		if (!$schema['has_close_tag'] || empty($schema['content']['is_passthrough']) || $tpl === '')
		{
			return null;
		}

		// Reject any unsafe/complex template constructs
		if (preg_match('/<(script|iframe|audio|video|object|embed|details|summary|table|form|input|button|select|textarea|svg|style|xsl:[a-z0-9_-]+)\b/i', $tpl))
		{
			return null;
		}
		if (preg_match('/\bon[a-z]+\s*=|javascript\s*:/i', $tpl))
		{
			return null;
		}

		// Reject composite attributes (more than 1 part in default_attr or any named attr)
		if ($schema['default_attr'] !== null && count($schema['default_attr']['parts']) !== 1)
		{
			return null;
		}
		foreach ($schema['attributes'] as $attr)
		{
			if (count($attr['parts']) !== 1)
			{
				return null;
			}
		}

		// Extract the passthrough token ID from content
		$content_token = trim($schema['content']['raw'], '{}');

		// Template must be a single clean wrapper element whose ONLY child is {CONTENT_TOKEN}
		$allowed_tags = 'span|div|blockquote|mark|kbd|samp|ins|del|code|b|i|u|s|em|strong|small|p|aside|section';
		$pattern = '#^<(' . $allowed_tags . ')(\s+[^>]*)?>\{' . preg_quote($content_token, '#') . '\}</\1>$#i';

		if (!preg_match($pattern, $tpl, $m))
		{
			return null;
		}

		$root_tag = strtolower($m[1]);
		$attrs_str = isset($m[2]) ? $m[2] : '';

		$style = '';
		if (preg_match('/\bstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs_str, $sm))
		{
			$style = isset($sm[1]) && $sm[1] !== '' ? $sm[1] : (isset($sm[2]) ? $sm[2] : '');
		}

		$class = '';
		if (preg_match('/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attrs_str, $cm))
		{
			$class = isset($cm[1]) && $cm[1] !== '' ? $cm[1] : (isset($cm[2]) ? $cm[2] : '');
		}

		return [
			'root_tag' => $root_tag,
			'style'    => $style,
			'class'    => $class,
		];
	}

	/**
	* Get single definition by tag name (normalized, handles trailing '=')
	*
	* @param string $tag
	* @return array|null
	*/
	public function getDefinition($tag)
	{
		$defs = $this->getDefinitions();
		$clean_tag = strtolower(rtrim(trim($tag), '='));
		return isset($defs[$clean_tag]) ? $defs[$clean_tag] : null;
	}

	/**
	* Check if custom BBCode tag produces a block-level structure
	*
	* @param string $tag
	* @return bool
	*/
	public function isBlock($tag)
	{
		$def = $this->getDefinition($tag);
		return $def !== null ? $def['is_block'] : false;
	}

	/**
	* Get definition fingerprint hash
	*
	* @param string $tag
	* @return string
	*/
	public function getFingerprint($tag)
	{
		$def = $this->getDefinition($tag);
		return $def !== null ? $def['fingerprint'] : '';
	}

	/**
	* Get custom BBCodes marked for display on posting (keyed by tag for backward compatibility)
	*
	* @return array
	*/
	public function getPostingBbcodes()
	{
		$defs = $this->getDefinitions();
		$posting = [];
		foreach ($defs as $tag => $data)
		{
			if ($data['display_on_posting'])
			{
				$posting[$tag] = $data;
			}
		}
		return $posting;
	}

	/**
	* Get all custom BBCodes marked for display on posting (list, preserving duplicate tag / tag= rows)
	*
	* @return array
	*/
	public function getPostingBbcodesList()
	{
		$this->getDefinitions();
		$posting = [];
		if (is_array($this->definitions_by_id))
		{
			foreach ($this->definitions_by_id as $data)
			{
				if ($data['display_on_posting'])
				{
					$posting[] = $data;
				}
			}
		}
		return $posting;
	}
}
