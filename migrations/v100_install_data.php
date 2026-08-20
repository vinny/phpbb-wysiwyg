<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\migrations;

class v100_install_data extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\vinny\wysiwyg\migrations\v100_install_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['wysiwyg_enabled']);
	}

	public function update_data()
	{
		return [
			['config.add', ['wysiwyg_enabled', 1]],
			['config.add', ['wysiwyg_default_enabled', 1]],
			['config.add', ['wysiwyg_allow_toggle', 1]],

			// Add ACP module under Extensions tab (ACP_CAT_DOT_MODS)
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_WYSIWYG_TITLE'
			]],

			['module.add', [
				'acp',
				'ACP_WYSIWYG_TITLE',
				[
					'module_basename'	=> '\vinny\wysiwyg\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],

			['custom', [[$this, 'add_bbcodes']]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['wysiwyg_enabled']],
			['config.remove', ['wysiwyg_default_enabled']],
			['config.remove', ['wysiwyg_allow_toggle']],

			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_WYSIWYG_TITLE'
			]],

			['module.remove', [
				'acp',
				'ACP_WYSIWYG_TITLE',
				[
					'module_basename'	=> '\vinny\wysiwyg\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],

			['custom', [[$this, 'revert_bbcodes']]],
		];
	}

	public function add_bbcodes()
	{
		if (!class_exists('acp_bbcodes'))
		{
			include $this->phpbb_root_path . 'includes/acp/acp_bbcodes.' . $this->php_ext;
		}
		$acp_bbcodes = new \acp_bbcodes();

		$bbcodes = [
			'align' => [
				'bbcode_match'    => '[align={IDENTIFIER}]{TEXT}[/align]',
				'bbcode_tpl'      => '<div style="text-align: {IDENTIFIER};" data-bbcode="align" data-bbcode-val="{IDENTIFIER}">{TEXT}</div>',
				'bbcode_helpline' => 'WYSIWYG_HELP_ALIGN',
			],
			's' => [
				'bbcode_match'    => '[s]{TEXT}[/s]',
				'bbcode_tpl'      => '<span style="text-decoration: line-through;" data-bbcode="s">{TEXT}</span>',
				'bbcode_helpline' => 'WYSIWYG_HELP_S',
			],
			'h1' => [
				'bbcode_match'    => '[h1]{TEXT}[/h1]',
				'bbcode_tpl'      => '<h1 data-bbcode="h1">{TEXT}</h1>',
				'bbcode_helpline' => 'WYSIWYG_HELP_H1',
			],
			'h2' => [
				'bbcode_match'    => '[h2]{TEXT}[/h2]',
				'bbcode_tpl'      => '<h2 data-bbcode="h2">{TEXT}</h2>',
				'bbcode_helpline' => 'WYSIWYG_HELP_H2',
			],
			'h3' => [
				'bbcode_match'    => '[h3]{TEXT}[/h3]',
				'bbcode_tpl'      => '<h3 data-bbcode="h3">{TEXT}</h3>',
				'bbcode_helpline' => 'WYSIWYG_HELP_H3',
			],
			'h4' => [
				'bbcode_match'    => '[h4]{TEXT}[/h4]',
				'bbcode_tpl'      => '<h4 data-bbcode="h4">{TEXT}</h4>',
				'bbcode_helpline' => 'WYSIWYG_HELP_H4',
			],
			'highlight' => [
				'bbcode_match'    => '[highlight]{TEXT}[/highlight]',
				'bbcode_tpl'      => '<mark data-bbcode="highlight">{TEXT}</mark>',
				'bbcode_helpline' => 'WYSIWYG_HELP_HIGHLIGHT',
			],
			'sub' => [
				'bbcode_match'    => '[sub]{TEXT}[/sub]',
				'bbcode_tpl'      => '<sub>{TEXT}</sub>',
				'bbcode_helpline' => 'WYSIWYG_HELP_SUB',
			],
			'sup' => [
				'bbcode_match'    => '[sup]{TEXT}[/sup]',
				'bbcode_tpl'      => '<sup>{TEXT}</sup>',
				'bbcode_helpline' => 'WYSIWYG_HELP_SUP',
			],
			'hr' => [
				'bbcode_match'    => '[hr][/hr]',
				'bbcode_tpl'      => '<hr data-bbcode="hr" />',
				'bbcode_helpline' => 'WYSIWYG_HELP_HR',
			],
			'table' => [
				'bbcode_match'    => '[table]{TEXT}[/table]',
				'bbcode_tpl'      => '<table data-bbcode="table">{TEXT}</table>',
				'bbcode_helpline' => 'WYSIWYG_HELP_TABLE',
			],
			'tr' => [
				'bbcode_match'    => '[tr]{TEXT}[/tr]',
				'bbcode_tpl'      => '<tr data-bbcode="tr">{TEXT}</tr>',
				'bbcode_helpline' => 'WYSIWYG_HELP_TR',
			],
			'td' => [
				'bbcode_match'    => '[td]{TEXT}[/td]',
				'bbcode_tpl'      => '<td data-bbcode="td">{TEXT}</td>',
				'bbcode_helpline' => 'WYSIWYG_HELP_TD',
			],
		];

		foreach ($bbcodes as $tag => $data)
		{
			// Check if already exists
			$sql = 'SELECT bbcode_id FROM ' . $this->table_prefix . 'bbcodes' . " WHERE LOWER(bbcode_tag) = '" . $this->db->sql_escape(strtolower($tag)) . "'";
			$result = $this->db->sql_query($sql);
			$exists = $this->db->sql_fetchfield('bbcode_id');
			$this->db->sql_freeresult($result);

			if ($exists)
			{
				continue;
			}

			// Build pass regexes
			$regexp = $acp_bbcodes->build_regexp($data['bbcode_match'], $data['bbcode_tpl']);

			// Get new ID
			$sql = 'SELECT MAX(bbcode_id) as max_id FROM ' . $this->table_prefix . 'bbcodes';
			$result = $this->db->sql_query($sql);
			$max_id = (int) $this->db->sql_fetchfield('max_id');
			$this->db->sql_freeresult($result);

			$new_id = max($max_id, 12) + 1; // phpBB core uses IDs up to 12

			$bbcode_data = [
				'bbcode_id'           => $new_id,
				'bbcode_tag'          => $regexp['bbcode_tag'],
				'bbcode_helpline'     => $data['bbcode_helpline'],
				'display_on_posting'  => 0, // Keep clean, don't show custom buttons because we have the WYSIWYG editor
				'bbcode_match'        => $data['bbcode_match'],
				'bbcode_tpl'          => $data['bbcode_tpl'],
				'first_pass_match'    => $regexp['first_pass_match'],
				'first_pass_replace'  => $regexp['first_pass_replace'],
				'second_pass_match'   => $regexp['second_pass_match'],
				'second_pass_replace' => $regexp['second_pass_replace'],
			];

			$sql = 'INSERT INTO ' . $this->table_prefix . 'bbcodes' . ' ' . $this->db->sql_build_array('INSERT', $bbcode_data);
			$this->db->sql_query($sql);
		}
	}

	public function revert_bbcodes()
	{
		$bbcodes = [
			'align' => '[align={IDENTIFIER}]{TEXT}[/align]',
			's' => '[s]{TEXT}[/s]',
			'h1' => '[h1]{TEXT}[/h1]',
			'h2' => '[h2]{TEXT}[/h2]',
			'h3' => '[h3]{TEXT}[/h3]',
			'h4' => '[h4]{TEXT}[/h4]',
			'highlight' => '[highlight]{TEXT}[/highlight]',
			'sub' => '[sub]{TEXT}[/sub]',
			'sup' => '[sup]{TEXT}[/sup]',
			'hr' => '[hr][/hr]',
			'table' => '[table]{TEXT}[/table]',
			'tr' => '[tr]{TEXT}[/tr]',
			'td' => '[td]{TEXT}[/td]',
		];

		foreach ($bbcodes as $tag => $match)
		{
			$sql = 'DELETE FROM ' . $this->table_prefix . 'bbcodes' . " 
				WHERE LOWER(bbcode_tag) = '" . $this->db->sql_escape(strtolower($tag)) . "'
				AND bbcode_match = '" . $this->db->sql_escape($match) . "'";
			$this->db->sql_query($sql);
		}
	}
}
