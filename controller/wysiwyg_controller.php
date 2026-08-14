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
use phpbb\request\request_interface;
use phpbb\config\config;
use phpbb\auth\auth;
use phpbb\user;
use vinny\wysiwyg\text_formatter\converter;

class wysiwyg_controller
{
	/** @var request_interface */
	protected $request;

	/** @var converter */
	protected $converter;

	/** @var config */
	protected $config;

	/** @var user */
	protected $user;

	/**
	* Constructor
	*
	* @param request_interface $request
	* @param converter $converter
	* @param config $config
	* @param user $user
	*/
	public function __construct($request, $converter, $config, $user)
	{
		$this->request = $request;
		$this->converter = $converter;
		$this->config = $config;
		$this->user = $user;
	}

	/**
	* Convert HTML to BBCode
	*
	* @return JsonResponse
	*/
	public function html_to_bbcode()
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return new JsonResponse(['error' => $this->user->lang('WYSIWYG_DISABLED')], 403);
		}

		$html = $this->request->raw_variable('html', '');
		$bbcode = $this->converter->toBBCode($html);

		return new JsonResponse(['bbcode' => $bbcode]);
	}

	/**
	* Convert BBCode to HTML
	*
	* @return JsonResponse
	*/
	public function bbcode_to_html()
	{
		if (empty($this->config['wysiwyg_enabled']))
		{
			return new JsonResponse(['error' => $this->user->lang('WYSIWYG_DISABLED')], 403);
		}

		$bbcode = $this->request->raw_variable('bbcode', '');
		$html = $this->converter->toHtml($bbcode);

		return new JsonResponse(['html' => $html]);
	}
}
