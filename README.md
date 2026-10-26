# keyboard_shortcuts

`keyboard_shortcuts` adds a keyboard shortcuts help launcher to Roundcube mail and enables common mailbox and message actions from the keyboard.

This repository is maintained by Gene Hawkins (`texxasrulez`).

## Features

- Shortcut help dialog available from the mail toolbar/list controls area
- Keyboard actions for common mailbox tasks such as compose, reply, forward, delete, print, and refresh
- Thread-view shortcuts when the IMAP server supports threading
- Archive shortcut support when `archive_mbox` is configured
- Skin assets for `elastic`, `larry`, `classic`, and Larry color variants
- Updated asset handling for newer Roundcube layouts, including Roundcube 1.7 `public_html` installs

## Supported Context

- Roundcube mail task
- Roundcube compose task
- Logged-in users only

## Included Shortcuts

Mailbox view:

- `?` Show shortcut help
- `/` Focus quick search
- `a` Select all visible messages (also marks them read)
- `c` Compose
- `d` Delete
- `f` Forward
- `j` Previous page
- `k` Next page
- `p` Print
- `r` Reply
- `R` Reply all
- `u` Check for new mail
- `z` Archive, when archive is configured

Thread view:

- `E` Expand all
- `C` Collapse all
- `U` Expand unread

Message view:

- `d` Delete
- `f` Forward
- `i` Return to message list
- `j` Previous message
- `k` Next message
- `p` Print
- `r` Reply
- `R` Reply all
- `z` Archive, when archive is configured

## Installation

### Composer

Add the package to your Roundcube installation:

```bash
composer require texxasrulez/keyboard_shortcuts
```

### Manual Install

Copy this repository into your Roundcube `plugins/keyboard_shortcuts` directory.

## Enable The Plugin

Add `keyboard_shortcuts` to the Roundcube plugin list in `config/config.inc.php`:

```php
$config['plugins'][] = 'keyboard_shortcuts';
```

## Notes

- The launcher is injected into the `listcontrols` template container.
- The plugin loads `jqueryui` and its own JavaScript/CSS assets automatically.
- Skin-specific icon/CSS fallbacks are included so the launcher remains visible across supported skins.

## Maintainer

- Gene Hawkins
- GitHub: https://github.com/texxasrulez/keyboard_shortcuts
- Issues: https://github.com/texxasrulez/keyboard_shortcuts/issues
- Site: https://www.genesworld.net

## License

GPL-2.0-only
