# WYSIWYG Editor phpBB Extension [![Tests](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml/badge.svg)](https://github.com/vinny/phpbb-wysiwyg/actions/workflows/tests.yml)

A modern phpBB 3.3.x+ extension that replaces the native BBCode editor with a clean, TipTap-based WYSIWYG editor using the **Simple** theme.

## Architecture

This extension is built to strictly adhere to phpBB standards and ensure safety and graceful degradation:

1. **Storage**: Content is always stored as native BBCode in the database.
2. **Parser**: A robust translation layer converts BBCode to HTML for editing in the frontend, and HTML back to BBCode when saving.
3. **Control Settings**:
    - **Global Settings (ACP)**: Toggle the editor globally, configure default state, and allow users to toggle it on or off.
    - **User Preferences (UCP)**: Choose the preferred editor.
4. **JS Fallback**: If JavaScript is disabled or fails to load, the system degrades gracefully back to phpBB's native textarea BBCode editor.

## Installation

1. Copy the extension contents into your phpBB directory under `ext/vinny/wysiwyg/`.
2. Go to the ACP -> Customise -> Manage extensions.
3. Locate "WYSIWYG Editor" and click **Enable**.
4. Configure global settings in the Extensions tab under **WYSIWYG Editor**.

## Permissions

The extension adds two user permissions under the **Post** tab:
- `u_wysiwyg_use`: Allows the user to use the WYSIWYG editor.
- `u_wysiwyg_toggle`: Allows the user to toggle the editor on/off (either globally in UCP or dynamically via the editor toolbar).

By default, these permissions are set to `Yes` for Standard Users and Full Administrators.

## Configuration

### Admin Control Panel (ACP)
Navigate to **ACP -> Customise -> WYSIWYG Editor** (or via the Customise tab) to manage global configuration:
- **Enable WYSIWYG Editor globally**: Turns the editor on or off for the entire forum.
- **Enable by default**: Determines whether the WYSIWYG editor is selected by default for new users.
- **Allow user toggle**: Determines whether individual users can override the default and toggle back to the BBCode source editor.

### User Control Panel (UCP)
If toggling is permitted, users can go to **UCP -> Board Preferences -> Edit posting defaults** to choose between:
- Standard BBCode Editor
- WYSIWYG Editor

## Modern Visual Editing

Unlike older visual editors that stored raw HTML directly in the database (which often caused formatting issues and security risks), this extension introduces a modern approach:

- **Clean Storage**: Posts are still saved as standard BBCode in the database. This ensures compatibility with search engines, notifications, and future phpBB updates.
- **Dynamic Conversion**: Converts HTML to BBCode on the fly.
- **Graceful Fallback**: If JavaScript is disabled or fails to load, the editor reverts back to the standard phpBB text box, ensuring posting is never interrupted.

## Development & Building JS

The TipTap editor and its dependencies are bundled locally (without CDN dependencies) into a single optimized file at `styles/all/template/js/tiptap-simple.js`.

If you wish to modify the editor code:
1. Edit the unbundled source code file at `styles/all/template/js/src/tiptap-simple.js`.
2. Install npm packages:
   ```bash
   npm install
   ```
3. Compile the bundle:
   ```bash
   npm run build
   ```

