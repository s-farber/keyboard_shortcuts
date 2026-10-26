function keyboard_shortcuts_show_help() {
  var help = $('#keyboard_shortcuts_help');

  // The dialog is initialized in a DOM-ready block below. If the key arrives before
  // that ran (or the widget failed to load), initialize lazily instead of throwing.
  if (help.length && !help.hasClass('ui-dialog-content')) {
    help.dialog({
      autoOpen: false,
      draggable: true,
      modal: false,
      resizable: false,
      width: 750,
      title: rcmail.gettext('keyboard_shortcuts.keyboard_shortcuts')
    });
  }

  help.dialog('open');
  return false;
}

$(function() {

  // initialize a dialog window
  $('#keyboard_shortcuts_help').dialog({
    autoOpen: false,
    draggable: true,
    modal: false,
    resizable: false,
    width: 750,
    title: rcmail.gettext("keyboard_shortcuts.keyboard_shortcuts")
  });

  // fire up the keydown event listener
  // NOTE: Roundcube core (program/js/list.js) registers a *native* keydown listener on
  // document for the message list, and that listener calls e.stopPropagation() for some
  // keys. A bubbling jQuery handler on document can therefore miss those events. We bind
  // with capture=true so this handler runs during the capture phase for every key.
  document.addEventListener('keydown', function (e) {
    return key_pressed(e);
  }, true);


  function key_pressed (e) {
    // special case. If we hit ctrl-enter, and we're composing, and we have focus, then send email
    if (rcmail.env.action == 'compose' && e.which == 13 && e.ctrlKey && $("*:focus").is("#composebody")) {
      $('.button.send').click();
      return false;
    }

    // Down/Up from the search box moves into the results list. Core's list handler
    // ignores keys whose target is an input (list.js:1472), and Tab only blurs the
    // list without selecting anything, so there is otherwise no way to reach the
    // results by keyboard from the search box. Handled before the focus guard below,
    // which would otherwise skip this plugin entirely while an input is focused.
    if (e.key == 'ArrowDown' || e.key == 'ArrowUp') {
      var ks_qsearch = rcmail.gui_objects.qsearchbox;
      // gui_objects entries are resolved to DOM elements only after load
      // (app.js:196/241), so compare through the DOM node, not by identity.
      var ks_focused_el = $("*:focus").get(0);
      var ks_qsearch_el = ks_qsearch ? $(ks_qsearch).get(0) : null;

      if (ks_qsearch_el && ks_focused_el === ks_qsearch_el && rcmail.message_list) {
        var ks_rows = rcmail.message_list.rowcount || 0;

        // Only steal focus when the search actually returned something, so an
        // empty result set leaves the cursor in the box to refine the query.
        if (ks_rows > 0) {
          e.preventDefault();
          rcmail.message_list.focus();

          if (e.key == 'ArrowDown') {
            rcmail.message_list.select_first(null, true);
          } else {
            rcmail.message_list.select_last(null, true);
          }

          return false;
        }
      }
    }

    // check if some element has focus. If it does, skip this plugin.
    if ( $("*:focus").is("textarea, input") ) {
      return true;
    }

    if (rcmail.env.action == 'compose' || rcmail.env.task == 'login' || e.ctrlKey || e.metaKey) return true;

    if (rcmail.env.action == '') {	// list mailbox

      // Characters this branch consumes as shortcuts; used to suppress the browser
      // default so a shortcut key is never also typed into the focused element.
      var ks_shortcut_keys = { '?':1, 'a':1, 'c':1, 'e':1, 'r':1, 'u':1, '/':1,
                               'd':1, 'f':1, 'j':1, 'k':1, 'p':1, 'y':1, 'z':1 };

      // Resolve the shortcut from the character the key produces rather than from
      // e.which. On keydown, e.which reports the SHIFTED/physical key (pressing s gives
      // 83 = 'S'; pressing ? on a US layout gives 191 = '/'), not the character, so a
      // code-based switch misses plain letters and '?' entirely. e.key is the produced
      // character and is correct across Shift/Caps Lock/layout.
      var ks_key = e.key;

      // Normalize so shortcuts are case-insensitive: Shift+A, caps-lock 'A' and plain
      // 'a' all mean the 'a' shortcut, which is what the help dialog advertises.
      var ks_lookup = typeof ks_key === 'string' && ks_key.length == 1 ? ks_key.toLowerCase() : ks_key;

      // Deployers can rebind keys from config: $config['keyboard_shortcuts_extras'] is
      // emitted as rcmail.env.ks_extras and takes precedence over the built-in switch
      // below. Anything not configured falls through and behaves as before.
      var ks_extras = (rcmail.env.ks_extras || {}).mailbox || {};
      var ks_extra_cmd = ks_extras[ks_key] || ks_extras[ks_lookup];

      if (typeof ks_extra_cmd === 'string' && ks_extra_cmd) {
        // Stop the browser default, or the bound key is also typed into the page.
        e.preventDefault();
        rcmail.command(ks_extra_cmd);
        return false;
      }

      // env.ks_functions is keyed by the literal character ('?' => 'ks_help').
      var ks_function = (rcmail.env.ks_functions || {})[ks_key]
        || (rcmail.env.ks_functions || {})[ks_lookup];

      if (typeof ks_function === 'string' && typeof window[ks_function] === 'function') {
        // 'return false' is a no-op in a native addEventListener handler, so stop the
        // browser default here or the shortcut char is also typed into the focused field.
        e.preventDefault();
        window[ks_function]();
        return false;
      }

      // Any shortcut we consume must not also be delivered as text. Set once here so
      // every case below inherits it; the 'no match' path falls through untouched.
      if (ks_lookup in ks_shortcut_keys) {
        e.preventDefault();
      }

      switch (ks_lookup) {
          case '?':		// ? = help
          keyboard_shortcuts_show_help();
          return false;
        case 'a':		// a/A = select all + mark all read (as shipped and confirmed working)
          rcmail.command('select-all', 'page');
          rcmail.command('mark', 'read');
          return false;
        case 'c':		// c/C = collapse-all
          rcmail.command('collapse-all');
          return false;
        case 'e':		// e/E = expand-all
          rcmail.command('expand-all');
          return false;
        case 'r':		// r/R = reply-all
          if (rcmail.message_list.selection.length == 1)
          rcmail.command('reply-all');
          return false;
        case 'u':		// u/U = expand-unread
          rcmail.command('expand-unread');
          return false;
        case '/':		// / = search
          // The mail template passes id="mailsearchform" (skins/elastic/templates/mail.html),
          // search_form() falls back to rcmqsearchbox, older skins use quicksearchbox.
          // 'return false' is a no-op for a native addEventListener handler, so the
          // default is suppressed above via ks_shortcut_keys -> preventDefault().
          var ks_search = $('#mailsearchform, #quicksearchbox, #rcmqsearchbox').first();
          ks_search.focus();
          ks_search.select();
          return false;
        case 'd':		// d/D = delete
          rcmail.command('delete', '', rcmail);
          return false;
        case 'f':		// f/F = forward
          if (rcmail.message_list.selection.length == 1)
          rcmail.command('forward');
          return false;
        case 'j':		// j = previous page (similar to Gmail)
          rcmail.command('previouspage');
          return false;
        case 'k':		// k = next page (similar to Gmail)
          rcmail.command('nextpage');
          return false;
        case 'p':		// p/P = print
          if (rcmail.message_list.selection.length == 1)
          rcmail.command('print');
          return false;
        case 'y':		// y = check for mail (update)
          rcmail.command('checkmail');
          return false;
        case 'z':		// z = archive (move to archive)
          rcmail.command('plugin.archive');
          return false;
      }
    } else if (rcmail.env.action == 'show' || rcmail.env.action == 'preview') {
      // Same reasoning as the mailbox branch: e.which on keydown is the shifted/physical
      // key, so dispatch on the produced character instead.
      var ks_key2 = e.key,
          ks_lookup2 = typeof ks_key2 === 'string' && ks_key2.length == 1 ? ks_key2.toLowerCase() : ks_key2;

      // Config-supplied bindings take precedence here too; see the mailbox branch.
      var ks_extras2 = (rcmail.env.ks_extras || {}).show || {};
      var ks_extra_cmd2 = ks_extras2[ks_key2] || ks_extras2[ks_lookup2];

      if (typeof ks_extra_cmd2 === 'string' && ks_extra_cmd2) {
        e.preventDefault();
        rcmail.command(ks_extra_cmd2);
        return false;
      }

      var ks_function2 = (rcmail.env.ks_functions || {})[ks_key2]
            || (rcmail.env.ks_functions || {})[ks_lookup2];

      if (typeof ks_function2 === 'string' && typeof window[ks_function2] === 'function') {
        e.preventDefault();
        window[ks_function2]();
        return false;
      }

      // Suppress the browser default for keys this branch consumes, so e.g. 'd' delete
      // does not also type a 'd'.
      if (ks_lookup2 in { 'r':1, 'c':1, 'd':1, 'f':1, 'i':1, 'j':1, 'k':1, 'p':1, 'z':1 }) {
        e.preventDefault();
      }

      switch (ks_lookup2) {
        case 'r':		// r/R = reply-all
          rcmail.command('reply-all');
          return false;
        case 'c':		// c/C = compose
          rcmail.command('compose');
          return false;
        case 'd':		// d/D = delete
          rcmail.command('delete');
          return false;
        case 'f':		// f/F = forward
          rcmail.command('forward');
          return false;
        case 'i':		// i = back to list
          rcmail.command('list');
          return false;
        case 'j':		// j = previous message (similar to Gmail)
          rcmail.command('previousmessage');
          return false;
        case 'k':		// k = next message (similar to Gmail)
          rcmail.command('nextmessage');
          return false;
        case 'p':		// p/P = print
          rcmail.command('print');
          return false;
        case 'z':		// z = archive (move to archive)
          rcmail.command('plugin.archive');
          return false;
      }
    }
  }
});

// support functions for each function we support
function ks_help() {
  keyboard_shortcuts_show_help();
}
