<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\vinny\wysiwyg\acp\main_module',
			'title'		=> 'ACP_WYSIWYG_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_WYSIWYG_SETTINGS',
					'auth'	=> 'ext_vinny/wysiwyg && acl_a_board',
					'cat'	=> ['ACP_WYSIWYG_TITLE'],
				],
			],
		];
	}
}
