<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\acp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $language, $template, $request, $config;

		$this->tpl_name = 'acp_wysiwyg_body';
		$this->page_title = $language->lang('ACP_WYSIWYG_TITLE');

		add_form_key('vinny_wysiwyg_settings');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('vinny_wysiwyg_settings'))
			{
				trigger_error('FORM_INVALID');
			}

			$config->set('wysiwyg_enabled', $request->variable('wysiwyg_enabled', 0));
			$config->set('wysiwyg_default_enabled', $request->variable('wysiwyg_default_enabled', 0));
			$config->set('wysiwyg_allow_toggle', $request->variable('wysiwyg_allow_toggle', 0));

			trigger_error($language->lang('ACP_WYSIWYG_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'WYSIWYG_ENABLED'			=> $config['wysiwyg_enabled'],
			'WYSIWYG_DEFAULT_ENABLED'	=> $config['wysiwyg_default_enabled'],
			'WYSIWYG_ALLOW_TOGGLE'		=> $config['wysiwyg_allow_toggle'],
			'U_ACTION'					=> $this->u_action,
		]);
	}
}
