<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\language\language;
use phpbb\request\request_interface;
use vinny\wysiwyg\text_formatter\converter;

class wysiwyg_controller
{
	/** @var int Maximum accepted payload size in bytes (512 KiB) */
	const MAX_PAYLOAD_BYTES = 524288;

	/** @var request_interface */
	protected $request;

	/** @var converter */
	protected $converter;

	/** @var config */
	protected $config;

	/** @var auth */
	protected $auth;

	/** @var language */
	protected $language;

	/**
	* Constructor
	*
	* @param request_interface $request
	* @param converter $converter
	* @param config $config
	* @param auth $auth
	* @param language $language
	*/
	public function __construct($request, $converter, $config, $auth, $language)
	{
		$this->request = $request;
		$this->converter = $converter;
		$this->config = $config;
		$this->auth = $auth;
		$this->language = $language;
	}

	/**
	* Check whether the current user has permission to compose posts, PMs, signatures, or ACP content
	*
	* @return bool
	*/
	protected function can_use_endpoints()
	{
		return $this->auth->acl_getf_global('f_post')
			|| $this->auth->acl_getf_global('f_reply')
			|| $this->auth->acl_getf_global('f_edit')
			|| $this->auth->acl_get('u_sendpm')
			|| $this->auth->acl_get('u_sig')
			|| $this->auth->acl_get('a_');
	}

	/**
	* Validate extension enabled state, user ACL permissions, AJAX header, and CSRF hash
	*
	* @return JsonResponse|null Error response if validation fails, null if valid
	*/
	protected function validate_request()
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return new JsonResponse(['error' => $this->language->lang('WYSIWYG_DISABLED')], 403);
		}

		if (!$this->can_use_endpoints())
		{
			return new JsonResponse(['error' => $this->language->lang('NO_AUTH_OPERATION')], 403);
		}

		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['error' => $this->language->lang('FORM_INVALID')], 400);
		}

		if (function_exists('check_link_hash') && !check_link_hash($this->request->variable('hash', ''), 'vinny_wysiwyg'))
		{
			return new JsonResponse(['error' => $this->language->lang('FORM_INVALID')], 403);
		}

		return null;
	}

	/**
	* Convert HTML to BBCode
	*
	* @return JsonResponse
	*/
	public function html_to_bbcode()
	{
		$error_response = $this->validate_request();
		if ($error_response !== null)
		{
			return $error_response;
		}

		$html = $this->request->raw_variable('html', '');
		if (strlen($html) > self::MAX_PAYLOAD_BYTES)
		{
			return new JsonResponse(['error' => $this->language->lang('WYSIWYG_PAYLOAD_TOO_LARGE')], 413);
		}

		try
		{
			$bbcode = $this->converter->toBBCode($html);
			return new JsonResponse(['bbcode' => $bbcode]);
		}
		catch (\Exception $e)
		{
			return new JsonResponse(['error' => $this->language->lang('WYSIWYG_EXPORT_ERROR')], 400);
		}
	}

	/**
	* Convert BBCode to HTML
	*
	* @return JsonResponse
	*/
	public function bbcode_to_html()
	{
		$error_response = $this->validate_request();
		if ($error_response !== null)
		{
			return $error_response;
		}

		$bbcode = $this->request->raw_variable('bbcode', '');
		if (strlen($bbcode) > self::MAX_PAYLOAD_BYTES)
		{
			return new JsonResponse(['error' => $this->language->lang('WYSIWYG_PAYLOAD_TOO_LARGE')], 413);
		}

		// Input from client request must never be treated as trusted server-side s9e XML
		$html = $this->converter->toHtml($bbcode, false);

		return new JsonResponse(['html' => $html]);
	}
}
