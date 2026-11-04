"""Browser check steps for mod_leafr (used by the full check before a release).

Run with the generic tool, which builds a fresh Moodle in Moodle Playground for every Moodle version,
theme and browser, installs this plugin from GitHub and calls run(ctx):

    python /c/moodle/tools/browsercheck/browsercheck.py .github/scripts/browsercheck/leafr.py --ref main

Every step clicks a control of the reader with a real click (which fails if another element covers it)
and checks that the click had its effect. Full screen mode is checked with all controls (GitHub issue #16).
"""

REPO = 'peterpleimfeldner-design/moodle-mod_leafr'
PLUGIN_TYPE = 'mod'
PLUGIN_NAME = 'leafr'

# Body of a PHP closure($gen, $course); returns the page to test, relative to wwwroot.
SETUP_PHP = r"""
        $cm = $gen->create_module('leafr', ['course' => $course->id, 'name' => 'Handbook']);
        return '/mod/leafr/view.php?id=' . $cm->cmid;
"""

STATE = """() => { const R = document.querySelector('.leafr-reader'); const q = s => R.querySelector(s);
  return {page: q('[data-region="page-input"]').value, menu: !q('[data-region="view-menu"]').hidden,
    fs: !!document.fullscreenElement && R.classList.contains('is-fullscreen'),
    zoom: q('[data-region="zoom-label"]').textContent, sidebar: q('[data-action="sidebar"]').getAttribute('aria-expanded'),
    help: !q('[data-region="help"]').hidden, simple: q('[data-action="simpleview"]').getAttribute('aria-checked'),
    single: q('[data-action="spread-single"]').getAttribute('aria-checked'),
    fitw: q('[data-action="fit-width"]').getAttribute('aria-checked'),
    bm: q('[data-action="bookmark"]').getAttribute('aria-pressed')}; }"""

# Controls hidden on purpose on narrow screens are skipped there.
VIEW_MENU_ITEMS = [
    ('Single page', 'spread-single', lambda b, a: a['single'] == 'true'),
    ('Automatic', 'spread-auto', lambda b, a: a['single'] == 'false'),
    ('Zoom in', 'zoom-in', lambda b, a: a['zoom'] != b['zoom']),
    ('Zoom out', 'zoom-out', lambda b, a: a['zoom'] != b['zoom']),
    ('Fit width', 'fit-width', lambda b, a: a['fitw'] == 'true'),
    ('Fit page', 'fit-page', lambda b, a: a['fitw'] == 'false'),
    ('Simple view on', 'simpleview', lambda b, a: a['simple'] != b['simple']),
    ('Simple view off', 'simpleview', lambda b, a: a['simple'] != b['simple']),
]


def run(ctx):
    ctx.goto(ctx.landing, '.leafr-reader [data-action="view-menu"]')
    f = ctx.frame
    ctx.page.wait_for_timeout(2500)
    for sel in ['[data-action="restart"]', '[data-action="fullscreen-tip-close"]']:
        if f.locator(sel).first.is_visible():
            try:
                f.locator(sel).first.click(timeout=3000)
            except Exception:
                pass  # A covered notice shows up as failed steps below.

    def state():
        return f.evaluate(STATE)

    def act(name, action, check, wait=900):
        sel = f'.leafr-reader [data-action="{action}"]'
        if not f.locator(sel).first.is_visible():
            return
        ctx.step(name, sel, check, state, wait)

    def menu_open():
        if not state()['menu']:
            act('Open view menu', 'view-menu', lambda b, a: a['menu'])

    def all_controls(fullscreen):
        suffix = ' (full screen)' if fullscreen else ''
        for name, action in [('Next page', 'next'), ('Previous page', 'prev'), ('Last page', 'last'), ('First page', 'first')]:
            act(name + suffix, action, lambda b, a: a['page'] != b['page'], 1400)
        act('Bookmark on' + suffix, 'bookmark', lambda b, a: a['bm'] != b['bm'])
        act('Bookmark off' + suffix, 'bookmark', lambda b, a: a['bm'] != b['bm'])
        act('Sidebar open' + suffix, 'sidebar', lambda b, a: a['sidebar'] != b['sidebar'])
        act('Sidebar close' + suffix, 'sidebar', lambda b, a: a['sidebar'] != b['sidebar'])
        for name, action, check in VIEW_MENU_ITEMS:
            menu_open()
            act(name + suffix, action, check, 1300)
        if state()['menu']:
            act('Close view menu' + suffix, 'view-menu', lambda b, a: not a['menu'])
        act('Help open' + suffix, 'help', lambda b, a: a['help'])
        act('Help close' + suffix, 'help-close', lambda b, a: not a['help'])

    all_controls(False)
    menu_open()
    if f.locator('.leafr-reader [data-action="fullscreen"]').first.is_visible():
        act('Enter full screen', 'fullscreen', lambda b, a: a['fs'], 2000)
        if state()['fs']:
            all_controls(True)
            menu_open()
            act('Leave full screen', 'fullscreen', lambda b, a: not a['fs'], 2000)
    else:
        ctx.note('Full screen button shown', False, 'The browser offers no full screen mode here')
