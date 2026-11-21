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
		global $phpbb_container;

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request_interface $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');
		/** @var \phpbb\log\log_interface $phpbb_log */
		$phpbb_log = $phpbb_container->get('log');
		/** @var \phpbb\user $user */
		$user = $phpbb_container->get('user');
		/** @var \phpbb\cache\driver\driver_interface $cache */
		$cache = $phpbb_container->get('cache.driver');

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

			$cache->destroy('sql', BBCODES_TABLE);
			$cache->destroy('sql', SMILIES_TABLE);

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_WYSIWYG_SETTINGS');

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
