<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \vinny\wysiwyg\text_formatter\converter */
	protected $converter;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/**
	* Constructor
	*
	* @param \phpbb\request\request_interface $request
	* @param \vinny\wysiwyg\text_formatter\converter $converter
	* @param \phpbb\config\config $config
	* @param \phpbb\user $user
	* @param \phpbb\template\template $template
	* @param \phpbb\auth\auth $auth
	* @param \phpbb\controller\helper $helper
	*/
	public function __construct($request, $converter, $config, $user, $template, $auth, $helper)
	{
		$this->request = $request;
		$this->converter = $converter;
		$this->config = $config;
		$this->user = $user;
		$this->template = $template;
		$this->auth = $auth;
		$this->helper = $helper;
	}

	/**
	* {@inheritdoc}
	*/
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'						=> 'on_user_setup',
			'core.text_formatter_s9e_parse_before'	=> 'on_s9e_parse_before',
			'core.posting_modify_template_vars'		=> 'on_posting_modify_template_vars',
			'core.ucp_pm_compose_template'			=> 'on_ucp_pm_compose_template',
			'core.ucp_profile_modify_signature'		=> 'on_ucp_profile_modify_signature',
			'core.adm_page_header'					=> 'on_adm_page_header',
			'core.ucp_prefs_post_data'				=> 'on_ucp_prefs_post_data',
			'core.ucp_prefs_post_update_data'		=> 'on_ucp_prefs_post_update_data',
			'core.permissions'						=> 'on_permissions',
		];
	}

	/**
	* Intercept text parsing and convert HTML to BBCode if WYSIWYG was used
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_s9e_parse_before($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if ($this->request->variable('wysiwyg_used', 0))
		{
			$html = html_entity_decode($event['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$event['text'] = $this->converter->toBBCode($html);
		}
	}

	/**
	* Pass content converted to HTML and settings to the posting template
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_posting_modify_template_vars($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if ($this->is_user_wysiwyg_enabled())
		{
			$message_parser = $event['message_parser'];
			$message = isset($message_parser->message) ? $message_parser->message : '';

			$html_content = $this->converter->toHtml($message);

			$this->template->assign_vars($this->get_wysiwyg_template_vars($html_content));
		}
	}

	/**
	* Pass content converted to HTML and settings to the PM compose template
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_ucp_pm_compose_template($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if ($this->is_user_wysiwyg_enabled())
		{
			$template_ary = $event['template_ary'];
			$message = isset($template_ary['MESSAGE']) ? $template_ary['MESSAGE'] : '';

			$html_content = $this->converter->toHtml($message);

			$this->template->assign_vars($this->get_wysiwyg_template_vars($html_content));
		}
	}

	/**
	* Pass content converted to HTML and settings to the UCP Signature template
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_ucp_profile_modify_signature($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if ($this->is_user_wysiwyg_enabled())
		{
			$signature = $event['signature'];
			$html_content = $this->converter->toHtml($signature);

			$this->template->assign_vars($this->get_wysiwyg_template_vars($html_content));
		}
	}

	/**
	* Check if WYSIWYG editor is enabled for the current user
	*
	* @return bool
	*/
	protected function is_user_wysiwyg_enabled()
	{
		if (!$this->auth->acl_get('u_wysiwyg_use'))
		{
			return false;
		}

		if (empty($this->config['wysiwyg_allow_toggle']) || !$this->auth->acl_get('u_wysiwyg_toggle'))
		{
			return (bool) $this->config['wysiwyg_default_enabled'];
		}

		if (!$this->user->data['is_registered'])
		{
			return (bool) $this->config['wysiwyg_default_enabled'];
		}

		if (isset($this->user->data['user_wysiwyg_enabled']))
		{
			return (bool) $this->user->data['user_wysiwyg_enabled'];
		}

		return (bool) $this->config['wysiwyg_default_enabled'];
	}

	/**
	* Get WYSIWYG editor translation strings as JSON
	*
	* @return string
	*/
	protected function get_wysiwyg_lang_json()
	{
		$lang_keys = [
			'WYSIWYG_UNDO', 'WYSIWYG_REDO', 'WYSIWYG_HEADING', 'WYSIWYG_HEADING_P',
			'WYSIWYG_HEADING_1', 'WYSIWYG_HEADING_2', 'WYSIWYG_HEADING_3', 'WYSIWYG_HEADING_4',
			'WYSIWYG_LISTS', 'WYSIWYG_LIST_NONE', 'WYSIWYG_LIST_BULLET', 'WYSIWYG_LIST_ORDERED',
			'WYSIWYG_SIZE', 'WYSIWYG_SIZE_NORMAL', 'WYSIWYG_SIZE_TINY', 'WYSIWYG_SIZE_SMALL',
			'WYSIWYG_SIZE_LARGE', 'WYSIWYG_SIZE_HUGE', 'WYSIWYG_QUOTE', 'WYSIWYG_CODE_BLOCK',
			'WYSIWYG_BOLD', 'WYSIWYG_ITALIC', 'WYSIWYG_S',
			'WYSIWYG_UNDERLINE', 'WYSIWYG_FONT_COLOR', 'WYSIWYG_DEFAULT_COLOR', 'WYSIWYG_LINK', 'WYSIWYG_HIGHLIGHT',
			'WYSIWYG_SUPERSCRIPT', 'WYSIWYG_SUBSCRIPT', 'WYSIWYG_ALIGN_LEFT', 'WYSIWYG_ALIGN_CENTER',
			'WYSIWYG_ALIGN_RIGHT', 'WYSIWYG_ALIGN_JUSTIFY', 'WYSIWYG_ADD', 'WYSIWYG_TOGGLE_SOURCE',
			'WYSIWYG_URL_PROMPT', 'WYSIWYG_IMAGE_PROMPT', 'WYSIWYG_CHARACTERS',
			'WYSIWYG_WROTE', 'WYSIWYG_CODE_LABEL', 'WYSIWYG_SELECT_ALL_CODE',
			'WYSIWYG_HR', 'WYSIWYG_TABLE',
			'WYSIWYG_TABLE_INSERT', 'WYSIWYG_TABLE_ADD_ROW_BEFORE', 'WYSIWYG_TABLE_ADD_ROW_AFTER',
			'WYSIWYG_TABLE_DELETE_ROW', 'WYSIWYG_TABLE_ADD_COL_BEFORE', 'WYSIWYG_TABLE_ADD_COL_AFTER',
			'WYSIWYG_TABLE_DELETE_COL', 'WYSIWYG_TABLE_MERGE_CELLS', 'WYSIWYG_TABLE_SPLIT_CELL',
			'WYSIWYG_TABLE_DELETE_TABLE'
		];

		$translations = [];
		foreach ($lang_keys as $key)
		{
			$translations[$key] = $this->user->lang($key);
		}

		return json_encode($translations);
	}

	/**
	* Get WYSIWYG editor template variables
	*
	* @param string $html_content
	* @return array
	*/
	protected function get_wysiwyg_template_vars($html_content = '')
	{
		$vars = [
			'S_WYSIWYG_ENABLED'             => true,
			'S_WYSIWYG_TOGGLE'              => (bool) $this->config['wysiwyg_allow_toggle'],
			'WYSIWYG_LANG_JSON'             => $this->get_wysiwyg_lang_json(),
			'WYSIWYG_HTML_TO_BBCODE_URL'    => $this->helper->route('vinny_wysiwyg_html_to_bbcode'),
			'WYSIWYG_BBCODE_TO_HTML_URL'    => $this->helper->route('vinny_wysiwyg_bbcode_to_html'),
		];

		if ($html_content !== '')
		{
			$vars['WYSIWYG_CONTENT'] = $html_content;
		}

		return $vars;
	}

	/**
	* Pass S_WYSIWYG_ENABLED to the ACP template header if globally enabled
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_adm_page_header($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		$this->template->assign_vars($this->get_wysiwyg_template_vars());
	}

	/**
	* Load user preference for WYSIWYG editor in UCP settings
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_ucp_prefs_post_data($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if (empty($this->config['wysiwyg_allow_toggle']) || !$this->auth->acl_get('u_wysiwyg_toggle'))
		{
			return;
		}

		$user_wysiwyg_enabled = isset($this->user->data['user_wysiwyg_enabled']) ? $this->user->data['user_wysiwyg_enabled'] : $this->config['wysiwyg_default_enabled'];

		$this->template->assign_vars([
			'S_WYSIWYG_ENABLED'			=> true,
			'S_USER_WYSIWYG_ENABLED'	=> (bool) $user_wysiwyg_enabled,
		]);
	}

	/**
	* Save user preference for WYSIWYG editor in UCP settings
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_ucp_prefs_post_update_data($event)
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return;
		}

		if (empty($this->config['wysiwyg_allow_toggle']) || !$this->auth->acl_get('u_wysiwyg_toggle'))
		{
			return;
		}

		$user_wysiwyg_enabled = $this->request->variable('user_wysiwyg_enabled', (int) $this->config['wysiwyg_default_enabled']);

		$sql_ary = $event['sql_ary'];
		$sql_ary['user_wysiwyg_enabled'] = $user_wysiwyg_enabled;
		$event['sql_ary'] = $sql_ary;
	}

	/**
	* Register custom user permissions
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_permissions($event)
	{
		$event->update_subarray('permissions', 'u_wysiwyg_use', [
			'lang'	=> 'ACL_U_WYSIWYG_USE',
			'cat'	=> 'post',
		]);

		$event->update_subarray('permissions', 'u_wysiwyg_toggle', [
			'lang'	=> 'ACL_U_WYSIWYG_TOGGLE',
			'cat'	=> 'post',
		]);
	}

	/**
	* Load extension language files
	*
	* @param \phpbb\event\data $event
	* @return void
	*/
	public function on_user_setup($event)
	{
		$this->user->add_lang_ext('vinny/wysiwyg', 'info_acp_wysiwyg');
		$this->user->add_lang_ext('vinny/wysiwyg', 'permissions_wysiwyg');
	}
}
