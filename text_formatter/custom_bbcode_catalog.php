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

	/** @var array|null */
	protected $catalog = null;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface $db
	* @param string $bbcodes_table
	*/
	public function __construct($db, $bbcodes_table = '')
	{
		$this->db = $db;
		$this->bbcodes_table = $bbcodes_table ?: (defined('BBCODES_TABLE') ? BBCODES_TABLE : '');
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
		if (!$this->bbcodes_table)
		{
			return $this->catalog;
		}

		try
		{
			$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_helpline, bbcode_match, bbcode_tpl, display_on_posting
					FROM ' . $this->bbcodes_table . '
					ORDER BY bbcode_tag ASC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$clean_tag = strtolower(trim($row['bbcode_tag']));
				$tpl = isset($row['bbcode_tpl']) ? trim($row['bbcode_tpl']) : '';
				$match = isset($row['bbcode_match']) ? trim($row['bbcode_match']) : '';

				$is_block = false;
				if ($tpl !== '')
				{
					// Check if root element of template is a known HTML block tag
					if (preg_match('#^<(div|blockquote|table|pre|p|section|article|aside|figure|h[1-6]|ul|ol)\b#i', $tpl))
					{
						$is_block = true;
					}
				}

				$fingerprint = md5($clean_tag . '|' . $match . '|' . $tpl);

				$this->catalog[$clean_tag] = [
					'id'                 => (int) $row['bbcode_id'],
					'tag'                => $clean_tag,
					'helpline'           => isset($row['bbcode_helpline']) ? $row['bbcode_helpline'] : '',
					'match'              => $match,
					'tpl'                => $tpl,
					'display_on_posting' => !empty($row['display_on_posting']),
					'is_block'           => $is_block,
					'fingerprint'        => $fingerprint,
				];
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
	* Get single definition by tag name
	*
	* @param string $tag
	* @return array|null
	*/
	public function getDefinition($tag)
	{
		$defs = $this->getDefinitions();
		$clean_tag = strtolower(trim($tag));
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
	* Get custom BBCodes marked for display on posting
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
	* Clear in-memory catalog cache (useful for tests)
	*
	* @return void
	*/
	public function clearCache()
	{
		$this->catalog = null;
	}
}
