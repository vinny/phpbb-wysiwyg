<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_WYSIWYG_TITLE'					=> 'WYSIWYG editor',
	'ACP_WYSIWYG_SETTINGS'				=> 'Settings',
	'ACP_WYSIWYG_SETTINGS_EXPLAIN'		=> 'Configure options for the WYSIWYG editor extension. Users can also configure their preferences in the User Control Panel.',
	'ACP_WYSIWYG_LEGEND'				=> 'Global editor settings',
	'ACP_WYSIWYG_ENABLED'				=> 'Enable WYSIWYG editor',
	'ACP_WYSIWYG_ENABLED_EXPLAIN'		=> 'Enable or disable the TipTap WYSIWYG editor.',
	'ACP_WYSIWYG_DEFAULT'				=> 'Enabled by default',
	'ACP_WYSIWYG_DEFAULT_EXPLAIN'		=> 'If enabled, the WYSIWYG editor will be the default for new users.',
	'ACP_WYSIWYG_ALLOW_TOGGLE'			=> 'Allow toggling',
	'ACP_WYSIWYG_ALLOW_TOGGLE_EXPLAIN'	=> 'Allow users to switch between the WYSIWYG editor and the standard BBCode editor.',
	'ACP_WYSIWYG_SETTINGS_SAVED'		=> 'WYSIWYG editor settings have been saved.',
	'LOG_WYSIWYG_SETTINGS'				=> '<strong>Altered WYSIWYG editor settings</strong>',
]);
