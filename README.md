# WYSIWYG Editor phpBB Extension [![Tests](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml/badge.svg)](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml)

Replaces the native BBCode text editor with a visual editor built on TipTap. Content is stored in the database as standard BBCode, preserving compatibility with native phpBB formatting, search indexing, notifications, and future updates.

## Features

- **Decoupled parser:** Converts BBCode to HTML for editing in the browser, and translates it back to BBCode when saving.
- **Global configuration:** Enable the editor globally, configure defaults, and allow users to toggle it on or off in the ACP.
- **User preferences:** Let users select their preferred editor interface in their posting settings.
- **Permissions:** Manage which users and groups can use the editor or switch between editors via `u_wysiwyg_use` and `u_wysiwyg_toggle`.
- **Local assets:** Editor scripts are bundled locally. Zero external CDN dependencies.
- **Fallback support:** Seamlessly reverts to the native phpBB textarea if JavaScript is disabled or fails to load.

## Requirements

- phpBB 3.3.0 or higher
- PHP 7.1.3 or higher

## Installation

1. Copy the extension files into your phpBB directory under `ext/vinny/wysiwyg/`.
2. Go to the ACP -> **Customise** -> **Manage extensions**.
3. Locate **WYSIWYG Editor** under the disabled list and click **Enable**.

## Configuration

### Admin Control Panel (ACP)
Configure global options in **ACP** -> **Customise** -> **WYSIWYG editor**:
- **Enable WYSIWYG editor**: Toggle the visual editor for the entire board.
- **Enabled by default**: Set the visual editor as the default for new users and guests.
- **Allow toggling**: Let users override the default setting and switch back to the plain BBCode editor.

### User Control Panel (UCP)
If toggling is allowed, users can select their preferred interface in **UCP** -> **Board preferences** -> **Edit posting defaults**:
- Standard BBCode Editor
- WYSIWYG Editor

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

## License

GPL-2.0-only
