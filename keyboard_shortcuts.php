<?php
declare(strict_types=1);

/**
 * keyboard_shortcuts
 *
 * Enables some common tasks to be executed with keyboard shortcuts
 *
 * @version 2.5.0
 * @author Patrik Kullman / Roland 'rosali' Liebl / Cor Bosman <roundcube@wa.ter.net>
 * @licence GNU GPL
 */

/**
 * Shortcuts, list view:
 * ?:	Show shortcut help
 * a:	Select all visible messages (also marks them read)
 * c:	Compose new message
 * d:	Delete message
 * f:	Forward message
 * j:	Go to previous page of messages (as Gmail)
 * k:	Go to next page of messages (as Gmail)
 * p:	Print message
 * r:	Reply to message
 * R:	Reply to all of message
 * /:	Jump to quicksearch
 * u:	Check for new mail (update)
 * z:	Move message to archive
 *
 * Shortcuts, threads view:
 * E:   Expand all
 * C:   Collapse all
 * U:   Expand Unread
 *
 * Shortcuts, mail view:
 * d:	Delete message
 * f:	Forward message
 * i:	Go to back to message list (as Gmail)
 * j:	Go to previous message (as Gmail)
 * k:	Go to next message (as Gmail)
 * p:	Print message
 * r:	Reply to message
 * R:	Reply to all of message
 * z:	Move message to archive
 */

class keyboard_shortcuts extends rcube_plugin
{
    const PLUGIN_VERSION = '2.5.0';
    const PLUGIN_INFO = array(
        'name' => 'keyboard_shortcuts',
        'vendor' => 'Gene Hawkins, Saul Farber',
        'version' => self::PLUGIN_VERSION,
        'license' => 'GPL-2.0-only',
        'uri' => 'https://github.com/s-farber/keyboard_shortcuts',
    );

    public static function info(): array
    {
        return self::PLUGIN_INFO;
    }

    public $task = 'mail|compose';

    public function init(): void
    {
        $rcmail = rcmail::get_instance();

        if (!$rcmail->get_user_id() || $rcmail->task === 'login') {
            return;
        }

        $new_user_dialog = null;

        if ($rcmail->session) {
            if (method_exists($rcmail->session, 'get')) {
                $new_user_dialog = $rcmail->session->get('plugin.newuserdialog');
            } elseif (isset($_SESSION['plugin.newuserdialog'])) {
                $new_user_dialog = $_SESSION['plugin.newuserdialog'];
            }
        } elseif (isset($_SESSION['plugin.newuserdialog'])) {
            $new_user_dialog = $_SESSION['plugin.newuserdialog'];
        }

        if ($new_user_dialog) {
            return;
        }

        $this->require_plugin('jqueryui');
        $this->include_stylesheet('keyboard_shortcuts.css');

        $skin = (string) $rcmail->config->get('skin', 'elastic');
        $skin_css = sprintf('skins/%s/keyboard_shortcuts.css', $skin);

        if (file_exists(__DIR__ . '/' . $skin_css)) {
            $this->include_stylesheet($skin_css);
        }

        $this->include_script('keyboard_shortcuts.js');
        $this->add_hook('template_container', array($this, 'html_output'));
        $this->add_texts('localization', true);
    }

    public function html_output(array $p): array
    {
        if (($p['name'] ?? '') !== 'listcontrols') {
            return $p;
        }

        $rcmail = rcmail::get_instance();

        $this->load_config();
        $archive_supported = (bool) $rcmail->config->get('archive_mbox');

        if (!is_object($rcmail->storage)) {
            $rcmail->storage_connect();
        }

        $threading_supported = $rcmail->storage->get_capability('thread=references')
            || $rcmail->storage->get_capability('thread=orderedsubject')
            || $rcmail->storage->get_capability('thread=refs');

        $content = "<span class='keyboard-shortcuts-launcher'>";

        $button_label = $this->translate('keyboard_shortcuts') . ' ' . $this->translate('show');
        $content .= "<a id='keyboard_shortcuts_link' href='#' class='button keyboard-shortcuts-button' ";
        $content .= "aria-haspopup='dialog' title='{$button_label}' aria-label='{$button_label}' ";
        $content .= "onclick='return keyboard_shortcuts_show_help()'>";
        $icon = $this->plugin_skin_asset_url('images/keyboard.png');
        $content .= "<img src='{$icon}' alt='' role='presentation' />";
        $content .= '</a>';
        $content .= '<span id="keyboard_shortcuts_title">' . $this->translate('title') . '</span>';
        $content .= '</span>';

        $content .= "<div id='keyboard_shortcuts_help'>";
        $sections = $this->build_sections($archive_supported, (bool) $threading_supported);

        foreach ($sections as $section) {
            $content .= $this->render_help_section($section['title'], $section['shortcuts']);
        }

        $content .= '</div>';

        $rcmail->output->set_env('ks_functions', array('?' => 'ks_help'));
        $rcmail->output->set_env('ks_extras', $this->build_extras_map());
        $p['content'] = $content . $p['content'];

        return $p;
    }

    /**
     * Read $config['keyboard_shortcuts_extras'] and return it as a map the client can use:
     *
     *   array( 'mailbox' => array('x' => 'delete', ...), 'show' => array(...) )
     *
     * Each entry binds a single character to a Roundcube command name. The client looks up
     * this map before its built-in switch, so a configured binding overrides the default and
     * unconfigured keys keep working as before.
     *
     * Accepted forms:
     *   'x' => 'delete'                      mailbox only
     *   'mailbox' => array('x' => 'delete')  both scopes/
     *   'x' => array('command' => 'delete', 'scope' => 'mailbox|show|both')
     */
    private function build_extras_map(): array
    {
        $rcmail = rcmail::get_instance();
        $raw = $rcmail->config->get('keyboard_shortcuts_extras', array());

        if (!is_array($raw) || empty($raw)) {
            return array();
        }

        // A top-level 'mailbox' or 'show' key declares scope, so nesting is allowed.
        $scoped = (array_key_exists('mailbox', $raw) || array_key_exists('show', $raw));
        $source = $scoped ? $raw : array('mailbox' => $raw);

        $map = array();

        foreach ($source as $scope => $bindings) {
            if (!is_array($bindings)) {
                continue;
            }

            foreach ($bindings as $key => $definition) {
                $command = null;
                $entry_scope = $scope;

                if (is_array($definition)) {
                    $command = isset($definition['command']) ? (string) $definition['command'] : null;
                    if (isset($definition['scope'])) {
                        $entry_scope = (string) $definition['scope'];
                    }
                }
                elseif (is_string($definition)) {
                    $command = $definition;
                }

                $key = (string) $key;
                $command = trim((string) $command);

                // Skip anything unusable rather than letting it reach the client as a no-op.
                if ($command === '' || $key === '') {
                    continue;
                }

                if ($entry_scope === 'both') {
                    $targets = array('mailbox', 'show');
                }
                else {
                    $targets = array($entry_scope);
                }

                foreach ($targets as $target) {
                    if ($target !== 'mailbox' && $target !== 'show') {
                        continue;
                    }

                    if (!isset($map[$target])) {
                        $map[$target] = array();
                    }

                    $map[$target][$key] = $command;
                }
            }
        }

        return $map;
    }

    private function render_help_section(string $title, array $shortcuts): string
    {
        if (empty($shortcuts)) {
            return '';
        }

        $rows = '';

        foreach ($shortcuts as $shortcut) {
            $rows .= "<div class='shortcut_key'>{$shortcut['key']}</div> {$shortcut['label']}<br class='clear' />";
        }

        $rows .= "<div class='shortcut_key' aria-hidden='true'>&nbsp;</div><br class='clear' />";

        return "<div><h4>{$title}</h4>{$rows}</div>";
    }

    private function plugin_skin_asset_url(string $path): string
    {
        $rcmail = rcmail::get_instance();
        $relative_path = $this->resolve_skin_asset_path($path, (string) $rcmail->config->get('skin', 'elastic'));
        $url = $this->url($relative_path);

        if (isset($rcmail->output) && method_exists($rcmail->output, 'abs_url')) {
            return $rcmail->output->abs_url($url);
        }

        return $url;
    }

    private function resolve_skin_asset_path(string $path, string $skin): string
    {
        $path = ltrim($path, '/');
        $candidates = array_unique(array(
            "skins/{$skin}/{$path}",
            "skins/elastic/{$path}",
            "skins/larry/{$path}",
            "skins/classic/{$path}",
        ));

        foreach ($candidates as $candidate) {
            if (is_file(__DIR__ . '/' . $candidate)) {
                return $candidate;
            }
        }

        return "skins/elastic/{$path}";
    }

    /**
     * Label to show in the help dialog for a shortcut command name.
     *
     * Mirrors the labels the built-in sections use, so a key rebound through config is
     * described by what it now does rather than by what the default binding did.
     */
    private function label_for_command(string $command): string
    {
        $labels = array(
            'search'          => 'quicksearch',
            'help'            => 'help',
            'select-all'      => 'selectallvisiblemessages',
            'mark'            => 'markallvisiblemessagesasread',
            'compose'         => 'compose',
            'delete'          => 'deletemessage',
            'forward'         => 'forwardmessage',
            'reply'           => 'replytomessage',
            'reply-all'       => 'replytoallmessage',
            'print'           => 'printmessage',
            'previouspage'    => 'previouspage',
            'nextpage'        => 'nextpage',
            'previousmessage' => 'previousmessage',
            'nextmessage'     => 'nextmessage',
            'checkmail'       => 'checkmail',
            'list'            => 'backtolist',
            'expand-all'      => 'expand-all',
            'collapse-all'    => 'collapse-all',
            'expand-unread'   => 'expand-unread',
            'plugin.archive'  => 'archive.buttontitle',
            'archive'         => 'archive.buttontitle',
        );

        if (isset($labels[$command])) {
            return $this->translate($labels[$command]);
        }

        // Unknown command: show it verbatim rather than inventing a translation.
        return $this->escape($command);
    }

    /**
     * Rewrite a section's shortcut rows to reflect configured bindings.
     *
     * A configured key replaces a built-in row when it uses the same key. New keys are
     * additive, matching the dispatcher's behavior: the built-in binding remains active
     * on its original key.
     */
    private function apply_extras_to_section(array $shortcuts, string $scope, bool $append_bindings = true): array
    {
        $extras = $this->build_extras_map();

        if (empty($extras[$scope])) {
            return $shortcuts;
        }

        $bindings = $extras[$scope];

        $filtered = array();
        $existing_bindings = array();

        foreach ($shortcuts as $shortcut) {
            $slug = isset($shortcut['command']) ? $this->command_slug($shortcut['command']) : null;
            $configured_command = null;
            $shortcut_key = (string) $shortcut['key'];
            $lookup_key = array_key_exists($shortcut_key, $bindings) ? $shortcut_key : strtolower($shortcut_key);
            if (array_key_exists($lookup_key, $bindings)) {
                $configured_command = $this->command_slug($bindings[$lookup_key]);
            }

            if ($configured_command !== null && $configured_command !== $slug) {
                continue; // key now does something else
            }

            $filtered[] = $shortcut;
            if ($configured_command !== null && $configured_command === $slug) {
                $existing_bindings[$shortcut['key']][$configured_command] = true;
            }
        }

        if (!$append_bindings) {
            return $filtered;
        }

        foreach ($bindings as $key => $command) {
            $slug = $this->command_slug($command);
            if (isset($existing_bindings[$key][$slug])) {
                continue;
            }

            // Same shape as the built-in rows, so later passes and the renderer treat
            // them identically.
            $filtered[] = array(
                'key' => $key,
                'command' => $command,
                'label' => $this->label_for_command($command),
            );
        }

        return $filtered;
    }

    /**
     * Stable identifier for a command, used to match a configured binding against the
     * built-in rows. Falls back to the command name itself.
     */
    private function command_slug(string $command): string
    {
        return strtolower(trim($command));
    }

    private function build_sections(bool $archive_supported, bool $threading_supported): array
    {
        $sections = array();

        $mailbox = array(
            array('key' => '?', 'command' => 'help', 'label' => $this->translate('help')),
            array('key' => '/', 'command' => 'search', 'label' => $this->translate('quicksearch')),
            array('key' => 'a', 'command' => 'select-all', 'label' => $this->translate('selectallvisiblemessages') . ' + ' . $this->translate('markallvisiblemessagesasread')),
            array('key' => 'c', 'command' => 'compose', 'label' => $this->translate('compose')),
            array('key' => 'd', 'command' => 'delete', 'label' => $this->translate('deletemessage')),
        );

        if ($archive_supported) {
            $mailbox[] = array('key' => 'z', 'command' => 'plugin.archive', 'label' => $this->translate('archive.buttontitle'));
        }

        $mailbox = array_merge($mailbox, array(
            array('key' => 'f', 'command' => 'forward', 'label' => $this->translate('forwardmessage')),
            array('key' => 'j', 'command' => 'previouspage', 'label' => $this->translate('previouspage')),
            array('key' => 'k', 'command' => 'nextpage', 'label' => $this->translate('nextpage')),
            array('key' => 'p', 'command' => 'print', 'label' => $this->translate('printmessage')),
            array('key' => 'r', 'command' => 'reply', 'label' => $this->translate('replytomessage')),
            array('key' => 'R', 'command' => 'reply-all', 'label' => $this->translate('replytoallmessage')),
            array('key' => 'u', 'command' => 'checkmail', 'label' => $this->translate('checkmail')),
        ));

        $sections[] = array(
            'title' => $this->translate('mailboxview'),
            'shortcuts' => $this->apply_extras_to_section($mailbox, 'mailbox'),
        );

        if ($threading_supported) {
            $sections[] = array(
                'title' => $this->translate('threads'),
                'shortcuts' => $this->apply_extras_to_section(array(
                    array('key' => 'E', 'command' => 'expand-all', 'label' => $this->translate('expand-all')),
                    array('key' => 'C', 'command' => 'collapse-all', 'label' => $this->translate('collapse-all')),
                    array('key' => 'U', 'command' => 'expand-unread', 'label' => $this->translate('expand-unread')),
                ), 'mailbox', false),
            );
        }

        $message_shortcuts = array(
            array('key' => 'd', 'command' => 'delete', 'label' => $this->translate('deletemessage')),
        );

        if ($archive_supported) {
            $message_shortcuts[] = array('key' => 'z', 'command' => 'plugin.archive', 'label' => $this->translate('archive.buttontitle'));
        }

        $message_shortcuts = array_merge($message_shortcuts, array(
            array('key' => 'c', 'command' => 'compose', 'label' => $this->translate('compose')),
            array('key' => 'f', 'command' => 'forward', 'label' => $this->translate('forwardmessage')),
            array('key' => 'i', 'command' => 'list', 'label' => $this->translate('backtolist')),
            array('key' => 'j', 'command' => 'previousmessage', 'label' => $this->translate('previousmessage')),
            array('key' => 'k', 'command' => 'nextmessage', 'label' => $this->translate('nextmessage')),
            array('key' => 'p', 'command' => 'print', 'label' => $this->translate('printmessage')),
            array('key' => 'r', 'command' => 'reply', 'label' => $this->translate('replytomessage')),
            array('key' => 'R', 'command' => 'reply-all', 'label' => $this->translate('replytoallmessage')),
        ));

        $sections[] = array(
            'title' => $this->translate('messagesdisplaying'),
            'shortcuts' => $this->apply_extras_to_section($message_shortcuts, 'show'),
        );

        return $sections;
    }

    private function translate(string $key): string
    {
        return $this->escape($this->gettext($key));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
