# WYSIWYG editor phpBB extension [![Tests](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml/badge.svg)](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml)

Replaces the native BBCode text editor with a visual editor built on TipTap. Content is stored in the database as standard BBCode, preserving compatibility with native phpBB formatting, search indexing, notifications, and future updates.

## Features

- **Decoupled parser:** Converts BBCode to HTML for editing in the browser, and translates it back to BBCode when saving.
- **ACP settings:** Enable the editor across the board, configure defaults, and allow users to toggle it on or off.
- **User preferences:** Let users select their preferred editor interface in their posting settings.
- **Local assets:** Editor scripts are bundled locally. Zero external CDN dependencies.
- **Fallback support:** Falls back to the native phpBB textarea if JavaScript is disabled or fails to load.

## Requirements

- phpBB 3.3.0 or higher
- PHP 7.2 or higher

## Installation

1. Copy the extension files into your phpBB directory under `ext/vinny/wysiwyg/`.
2. Go to the ACP -> **Customise** -> **Manage extensions**.
3. Locate **WYSIWYG editor** under the disabled list and click **Enable**.

## Configuration

### Admin Control Panel (ACP)
Configure options in **ACP** -> **Extensions** -> **WYSIWYG editor**:
- **Enable WYSIWYG editor**: Toggle the visual editor for the entire board.
- **Enabled by default**: Set the visual editor as the default for new users and guests.
- **Allow toggling**: Let users override the default setting and switch back to the plain BBCode editor.

### User Control Panel (UCP)
If toggling is allowed, users can select their preferred interface in **UCP** -> **Board preferences** -> **Edit posting defaults**:
- Standard BBCode editor
- WYSIWYG editor

## Development

The TipTap editor and its dependencies are compiled into `styles/all/template/js/tiptap-simple.js`. 

To modify the editor source code:
1. Edit the files under `styles/all/template/js/src/`.
2. Install the build tools:
   ```bash
   npm install
   ```
3. Rebuild the distribution bundle:
   ```bash
   npm run build
   ```

## Support

If you find this extension useful, you can support its development on [Ko-fi](https://ko-fi.com/vinny1).

## License

[![License](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](license.txt)
