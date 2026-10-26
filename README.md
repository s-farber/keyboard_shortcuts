# keyboard_shortcuts

`keyboard_shortcuts` adds a keyboard shortcuts help launcher to Roundcube mail and enables common mailbox and message actions from the keyboard.

This is a fork of [texxasrulez/keyboard_shortcuts](https://github.com/texxasrulez/keyboard_shortcuts) by Gene Hawkins.
It targets Roundcube 1.7 and fixes shortcut dispatch, which was broken on that release, so the plain-letter
and `?` shortcuts now work. See [Changes in this fork](#changes-in-this-fork).

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

This fork targets Roundcube 1.7.x. The fixes depend on 1.7 markup, so older releases are not supported.

### Manual Install (recommended)

Clone into your Roundcube `plugins` directory:

```bash
cd /path/to/roundcubemail/plugins
git clone https://github.com/s-farber/keyboard_shortcuts.git keyboard_shortcuts
```

To track updates, symlink a checkout instead of copying, then `git pull` to update:

```bash
cd /path/to/roundcubemail/plugins
ln -s /path/to/your/checkout keyboard_shortcuts
```

### Composer

The package is not published on Packagist, so `composer require` will not install this fork.
Reference the repository directly in your `composer.json` instead:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/s-farber/keyboard_shortcuts" }
    ],
    "require": {
        "s-farber/keyboard_shortcuts": "dev-main"
    }
}
```

Note that if you also require upstream `texxasrulez/keyboard_shortcuts`, Composer will see two
packages claiming the same plugin directory. Require only one of them.

## Enable The Plugin

Add `keyboard_shortcuts` to the Roundcube plugin list in `config/config.inc.php`:

```php
$config['plugins'][] = 'keyboard_shortcuts';
```

## Notes

- The launcher is injected into the `listcontrols` template container.
- The plugin loads `jqueryui` and its own JavaScript/CSS assets automatically.
- Skin-specific icon/CSS fallbacks are included so the launcher remains visible across supported skins.

## Changes in this fork

Shortcut handling was broken on Roundcube 1.7: nothing happened for the plain-letter shortcuts
or `?`. The cause is that on a `keydown` event `e.which` reports the shifted/physical key rather
than the character produced, so pressing `s` reported `83` (`S`) and `?` reported `16` then `191`
(`Shift`, `/`). Dispatch now uses `e.key`, so case and layout no longer matter.

Other fixes and changes:

- Bind the listener in the capture phase. Roundcube core registers a native document `keydown`
  handler for the message list and calls `stopPropagation()` for some keys.
- Call `preventDefault()` when a shortcut is consumed. `return false` is inert for a native
  `addEventListener` handler, so the shortcut character was also typed into the focused field.
- Target the search input by its real id (`mailsearchform`), so the search shortcut works on 1.7.
- Move the quick-search shortcut from `s` to `/`.
- Look up `env.ks_functions` by character, matching how the plugin populates it.
- Initialize the help dialog lazily so a keypress arriving before the DOM-ready block does not throw.
- Correct the help dialog and this README, which documented the old bindings.

## Maintainer

- Saul Farber
- GitHub: https://github.com/s-farber/keyboard_shortcuts
- Issues: https://github.com/s-farber/keyboard_shortcuts/issues

## License

GPL-2.0-only
