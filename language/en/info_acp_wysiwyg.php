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
	'ACP_WYSIWYG_DEFAULT_EXPLAIN'		=> 'If enabled, the WYSIWYG editor will be the default for new users and guests.',
	'ACP_WYSIWYG_ALLOW_TOGGLE'			=> 'Allow toggling',
	'ACP_WYSIWYG_ALLOW_TOGGLE_EXPLAIN'	=> 'Allow users to switch between the WYSIWYG editor and the standard BBCode editor.',
	'ACP_WYSIWYG_SETTINGS_SAVED'		=> 'WYSIWYG editor settings have been saved.',
	'UCP_WYSIWYG_ENABLED'				=> 'Enable WYSIWYG editor',
	'UCP_WYSIWYG_ENABLED_EXPLAIN'		=> 'Use the visual editor instead of the standard BBCode editor when composing posts.',
	'WYSIWYG_DISABLED'					=> 'WYSIWYG editor is disabled or you do not have permission to use it.',

	'WYSIWYG_UNDO'					=> 'Undo',
	'WYSIWYG_REDO'					=> 'Redo',
	'WYSIWYG_HEADING'				=> 'Heading',
	'WYSIWYG_HEADING_P'				=> 'Paragraph',
	'WYSIWYG_HEADING_1'				=> 'Heading 1',
	'WYSIWYG_HEADING_2'				=> 'Heading 2',
	'WYSIWYG_HEADING_3'				=> 'Heading 3',
	'WYSIWYG_HEADING_4'				=> 'Heading 4',
	'WYSIWYG_LISTS'					=> 'Lists',
	'WYSIWYG_LIST_NONE'				=> 'No list',
	'WYSIWYG_LIST_BULLET'			=> 'Bullet list',
	'WYSIWYG_LIST_ORDERED'			=> 'Ordered list',
	'WYSIWYG_SIZE'					=> 'Size',
	'WYSIWYG_SIZE_NORMAL'			=> 'Normal size',
	'WYSIWYG_SIZE_TINY'				=> 'Tiny',
	'WYSIWYG_SIZE_SMALL'			=> 'Small',
	'WYSIWYG_SIZE_LARGE'			=> 'Large',
	'WYSIWYG_SIZE_HUGE'				=> 'Huge',
	'WYSIWYG_QUOTE'					=> 'Quote',
	'WYSIWYG_CODE_BLOCK'			=> 'Code',
	'WYSIWYG_BOLD'					=> 'Bold',
	'WYSIWYG_ITALIC'				=> 'Italic',
	'WYSIWYG_S'						=> 'Strikethrough',
	'WYSIWYG_UNDERLINE'				=> 'Underline',
	'WYSIWYG_FONT_COLOR'			=> 'Font color',
	'WYSIWYG_DEFAULT_COLOR'			=> 'Default color',
	'WYSIWYG_LINK'					=> 'Insert link',
	'WYSIWYG_HIGHLIGHT'				=> 'Highlight',
	'WYSIWYG_SUPERSCRIPT'			=> 'Superscript',
	'WYSIWYG_SUBSCRIPT'				=> 'Subscript',
	'WYSIWYG_ALIGN_LEFT'			=> 'Align left',
	'WYSIWYG_ALIGN_CENTER'			=> 'Align center',
	'WYSIWYG_ALIGN_RIGHT'			=> 'Align right',
	'WYSIWYG_ALIGN_JUSTIFY'			=> 'Align justify',
	'WYSIWYG_ADD'					=> 'Add',
	'WYSIWYG_TOGGLE_SOURCE'			=> 'Toggle source code',
	'WYSIWYG_URL_PROMPT'			=> 'URL:',
	'WYSIWYG_IMAGE_PROMPT'			=> 'Image URL:',
	'WYSIWYG_CHARACTERS'			=> 'Characters: %d',
	'WYSIWYG_WROTE'					=> 'wrote',
	'WYSIWYG_CODE_LABEL'			=> 'Code',
	'WYSIWYG_SELECT_ALL_CODE'		=> 'Select all',
	'WYSIWYG_HELP_ALIGN'			=> 'Align text: [align=left|center|right|justify]text[/align]',
	'WYSIWYG_HELP_S'				=> 'Strikethrough text: [s]text[/s]',
	'WYSIWYG_HELP_H1'				=> 'Heading 1: [h1]text[/h1]',
	'WYSIWYG_HELP_H2'				=> 'Heading 2: [h2]text[/h2]',
	'WYSIWYG_HELP_H3'				=> 'Heading 3: [h3]text[/h3]',
	'WYSIWYG_HELP_H4'				=> 'Heading 4: [h4]text[/h4]',
	'WYSIWYG_HELP_HIGHLIGHT'		=> 'Highlight text: [highlight]text[/highlight]',
	'WYSIWYG_HELP_SUB'				=> 'Subscript text: [sub]text[/sub]',
	'WYSIWYG_HELP_SUP'				=> 'Superscript text: [sup]text[/sup]',
	'WYSIWYG_HELP_HR'				=> 'Horizontal rule: [hr][/hr]',
	'WYSIWYG_HELP_TABLE'			=> 'Table: [table][tr][td]text[/td][/tr][/table]',
	'WYSIWYG_HELP_TR'				=> 'Table row: [tr]text[/tr]',
	'WYSIWYG_HELP_TD'				=> 'Table cell: [td]text[/td]',
	'WYSIWYG_HR'					=> 'Horizontal rule',
	'WYSIWYG_TABLE'					=> 'Table',
	'WYSIWYG_TABLE_INSERT'			=> 'Insert table (3x3)',
	'WYSIWYG_TABLE_ADD_ROW_BEFORE'	=> 'Add row above',
	'WYSIWYG_TABLE_ADD_ROW_AFTER'	=> 'Add row below',
	'WYSIWYG_TABLE_DELETE_ROW'		=> 'Delete row',
	'WYSIWYG_TABLE_ADD_COL_BEFORE'	=> 'Add column before',
	'WYSIWYG_TABLE_ADD_COL_AFTER'	=> 'Add column after',
	'WYSIWYG_TABLE_DELETE_COL'		=> 'Delete column',
	'WYSIWYG_TABLE_MERGE_CELLS'		=> 'Merge cells',
	'WYSIWYG_TABLE_SPLIT_CELL'		=> 'Split cell',
	'WYSIWYG_TABLE_DELETE_TABLE'	=> 'Delete table',
]);
