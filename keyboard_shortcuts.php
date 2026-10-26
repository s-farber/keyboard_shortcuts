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

    private function build_sections(bool $archive_supported, bool $threading_supported): array
    {
        $sections = array();

        $mailbox = array(
            array('key' => '?', 'label' => $this->translate('help')),
            array('key' => '/', 'label' => $this->translate('quicksearch')),
            array('key' => 'a', 'label' => $this->translate('selectallvisiblemessages') . ' + ' . $this->translate('markallvisiblemessagesasread')),
            array('key' => 'c', 'label' => $this->translate('compose')),
            array('key' => 'd', 'label' => $this->translate('deletemessage')),
        );

        if ($archive_supported) {
            $mailbox[] = array('key' => 'z', 'label' => $this->translate('archive.buttontitle'));
        }

        $mailbox = array_merge($mailbox, array(
            array('key' => 'f', 'label' => $this->translate('forwardmessage')),
            array('key' => 'j', 'label' => $this->translate('previouspage')),
            array('key' => 'k', 'label' => $this->translate('nextpage')),
            array('key' => 'p', 'label' => $this->translate('printmessage')),
            array('key' => 'r', 'label' => $this->translate('replytomessage')),
            array('key' => 'R', 'label' => $this->translate('replytoallmessage')),
            array('key' => 'u', 'label' => $this->translate('checkmail')),
        ));

        $sections[] = array(
            'title' => $this->translate('mailboxview'),
            'shortcuts' => $mailbox,
        );

        if ($threading_supported) {
            $sections[] = array(
                'title' => $this->translate('threads'),
                'shortcuts' => array(
                    array('key' => 'E', 'label' => $this->translate('expand-all')),
                    array('key' => 'C', 'label' => $this->translate('collapse-all')),
                    array('key' => 'U', 'label' => $this->translate('expand-unread')),
                ),
            );
        }

        $message_shortcuts = array(
            array('key' => 'd', 'label' => $this->translate('deletemessage')),
        );

        if ($archive_supported) {
            $message_shortcuts[] = array('key' => 'z', 'label' => $this->translate('archive.buttontitle'));
        }

        $message_shortcuts = array_merge($message_shortcuts, array(
            array('key' => 'c', 'label' => $this->translate('compose')),
            array('key' => 'f', 'label' => $this->translate('forwardmessage')),
            array('key' => 'i', 'label' => $this->translate('backtolist')),
            array('key' => 'j', 'label' => $this->translate('previousmessage')),
            array('key' => 'k', 'label' => $this->translate('nextmessage')),
            array('key' => 'p', 'label' => $this->translate('printmessage')),
            array('key' => 'r', 'label' => $this->translate('replytomessage')),
            array('key' => 'R', 'label' => $this->translate('replytoallmessage')),
        ));

        $sections[] = array(
            'title' => $this->translate('messagesdisplaying'),
            'shortcuts' => $message_shortcuts,
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
