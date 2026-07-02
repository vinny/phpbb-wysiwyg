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
	'ACL_U_WYSIWYG_USE'		=> 'Can use WYSIWYG Editor',
	'ACL_U_WYSIWYG_TOGGLE'	=> 'Can toggle WYSIWYG Editor preference',
]);
