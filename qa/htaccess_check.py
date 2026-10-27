"""Interprets the project's REAL .htaccess files with a small mod_rewrite model and checks where each URL ends up.
This is a logic check of the rules, not Apache: it cannot prove host-specific behaviour (see README: 'Not verifiable here')."""
import os, re, sys
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))

def load_vfs(base, extra_files=()):
    files = set()
    for d, _, fs in os.walk(base):
        for f in fs: files.add(os.path.relpath(os.path.join(d, f), base).replace(os.sep, '/'))
    files |= set(extra_files)
    dirs = {''}
    for f in files:
        parts = f.split('/')[:-1]
        for i in range(1, len(parts) + 1): dirs.add('/'.join(parts[:i]))
    return files, dirs

def parse_htaccess(path):
    rules, conds, base = [], [], None
    on = False
    for raw in open(path).read().split('\n'):
        l = raw.strip()
        if not l or l.startswith('#'): continue
        if l.startswith('RewriteEngine'): on = l.split()[1].lower() == 'on'
        elif l.startswith('RewriteBase'): base = l.split()[1]
        elif l.startswith('RewriteCond'):
            m = re.match(r'RewriteCond\s+(\S+)\s+(\S+)(?:\s+\[(.*)\])?$', l); conds.append((m.group(1), m.group(2), m.group(3) or ''))
        elif l.startswith('RewriteRule'):
            m = re.match(r'RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[(.*)\])?$', l)
            rules.append((m.group(1), m.group(2), (m.group(3) or '').split(','), conds)); conds = []
    return (rules, base) if on and rules else (None, None)

def resolve(url, docroot_dir, files, dirs):
    """Return one of: 403, app, setup, static:<path>, php-exec:<path>, 404."""
    path = url.lstrip('/')
    for _ in range(10):
        # deepest directory on the path that exists and carries rewrite rules (else inherit from the parent)
        parts = path.split('/')[:-1]; chain = ['']
        for i in range(1, len(parts) + 1):
            d = '/'.join(parts[:i])
            if d in dirs: chain.append(d)
            else: break
        ruleset = None
        for d in reversed(chain):
            ht = os.path.join(docroot_dir, d, '.htaccess')
            if (d + '/.htaccess').lstrip('/') in files or (d == '' and '.htaccess' in files):
                rules, base = parse_htaccess(ht)
                if rules: ruleset = (d, rules, base); break
        if not ruleset: break
        d, rules, base = ruleset
        prefix = (d + '/') if d else ''
        base = base or '/' + prefix
        changed = False; cur = path
        for pat, sub, flags, conds in rules:
            rel = cur[len(prefix):] if cur.startswith(prefix) else cur
            neg = pat.startswith('!'); rx = pat[1:] if neg else pat
            m = re.search(rx, rel, re.I if 'NC' in flags else 0)
            if bool(m) == neg: continue
            ok = True
            for test, op, cflags in conds:
                if test == '%{REQUEST_URI}': val = '/' + cur
                elif test == '%{REQUEST_FILENAME}': val = cur
                elif test.startswith('%{DOCUMENT_ROOT}/public/$0'): val = 'public/' + (m.group(0) if m else '')
                else: raise ValueError('unsupported condition ' + test)
                if op in ('-f', '!-f'): res = val in files; res = (not res) if op == '!-f' else res
                else:
                    neg2 = op.startswith('!'); res = bool(re.search(op[1:] if neg2 else op, val, re.I if 'NC' in cflags else 0)); res = (not res) if neg2 else res
                if not res: ok = False; break
            if not ok: continue
            if 'F' in flags: return '403'
            if sub != '-':
                if sub.startswith('/'): cur = sub.lstrip('/')
                else:
                    new = (base + sub); new = new.replace('$0', m.group(0) if m else '')
                    cur = new.lstrip('/')
                changed = True
            if 'L' in flags: break
        if not changed or cur == path: break
        path = cur
    if path in files:
        if path == 'index.php': return 'app'
        if path == 'setup.php': return 'setup'
        return ('php-exec:' if path.endswith('.php') else 'static:') + path
    return '404'

def main():
    bad = 0
    def check(label, got, want):
        nonlocal bad
        ok = got in want if isinstance(want, list) else got == want
        if not ok: bad += 1
        print(('PASS  ' if ok else 'FAIL  ') + f'{label:<52} -> {got}' + ('' if ok else f'   (expected {want})'))
    files, dirs = load_vfs(ROOT, extra_files=['public/uploads/' + 'a1' * 16 + '.jpg', 'public/uploads/probe.php', 'public/uploads/' + 'b2' * 16 + '.png', 'setup.php'])
    R = lambda u: resolve(u, ROOT, files, dirs)
    print('== docroot = htdocs (project folder), InfinityFree layout ==')
    for u in ['/', '/work', '/work/some-slug', '/studio', '/services', '/team/some-person', '/contact', '/insights', '/insights/an-article', '/admin', '/admin/login', '/admin/projects/1/edit', '/sitemap.xml', '/robots.txt', '/index.php', '/work?kind=client'.split('?')[0], '/old-slug-that-redirects']:
        check('route ' + u, R(u), 'app')
    for u in ['/assets/css/base.css', '/assets/css/motion.css', '/assets/js/transitions.js', '/assets/js/boot.js', '/favicon.svg']:
        check('asset ' + u, R(u), 'static:public' + u if u != '/favicon.svg' else 'static:public/favicon.svg')
    check('uploaded image (jpg)', R('/uploads/' + 'a1' * 16 + '.jpg'), 'static:public/uploads/' + 'a1' * 16 + '.jpg')
    check('uploaded image (png)', R('/uploads/' + 'b2' * 16 + '.png'), 'static:public/uploads/' + 'b2' * 16 + '.png')
    check('missing asset is the app 404, not a server error', R('/assets/css/nope.css'), 'app')
    check('asset directory does not list', R('/assets/css/'), 'app')
    check('uploads directory does not list', R('/uploads/'), 'app')
    print('-- protected --')
    for u in ['/app/core/Router.php', '/app/', '/APP/core/Router.php', '/config/', '/Config/x', '/database/migrations/001_foundation.sql', '/routes/web.php', '/views/layouts/main.php', '/storage/logs/php.log', '/storage/uploads/x', '/deploy/nginx.conf.example', '/deploy/infinityfree-setup.php', '/qa/smoke.sh', '/docs/schema.md', '/bin/migrate.php', '/bin/create-admin.php',
              '/.env', '/.env.example', '/.gitignore', '/.htaccess', '/.git/config', '/README.md', '/public/index.php', '/public/.htaccess', '/public/uploads/.htaccess', '/anything.sql', '/notes.md', '/x/.env']:
        check('blocked ' + u, R(u), '403')
    print('-- uploads cannot execute --')
    for u in ['/uploads/probe.php', '/uploads/x.php', '/uploads/x.phtml', '/uploads/x.phar', '/uploads/x.PHP', '/uploads/x.php.jpg.php', '/public/uploads/probe.php', '/uploads/.htaccess']:
        check('no PHP via ' + u, R(u), ['403', 'app'])
    check('direct php in uploads is 403 (probe present)', R('/uploads/probe.php'), '403')
    check('only images served from public/uploads (index.html)', R('/uploads/index.html'), '403')
    print('-- temporary installer --')
    check('/setup.php while present', R('/setup.php'), 'setup')
    f2, d2 = load_vfs(ROOT)   # no setup.php present
    check('/setup.php after deletion goes to the app (404 page)', resolve('/setup.php', ROOT, f2, d2), 'app')
    print('== docroot = public/ (standard VPS layout) still works ==')
    pf, pd = load_vfs(os.path.join(ROOT, 'public'), extra_files=['uploads/' + 'a1' * 16 + '.jpg', 'uploads/probe.php'])
    P = lambda u: resolve(u, os.path.join(ROOT, 'public'), pf, pd)
    for u in ['/', '/work', '/work/x', '/admin', '/sitemap.xml', '/robots.txt']: check('public-root route ' + u, P(u), 'app')
    check('public-root asset', P('/assets/css/base.css'), 'static:assets/css/base.css')
    check('public-root asset dir does not list', P('/assets/css/'), 'app')
    check('public-root upload image', P('/uploads/' + 'a1' * 16 + '.jpg'), 'static:uploads/' + 'a1' * 16 + '.jpg')
    check('public-root upload php blocked', P('/uploads/probe.php'), '403')
    print('== entry point wiring ==')
    idx = open(os.path.join(ROOT, 'index.php')).read()
    code = [l for l in idx.split('\n') if l.strip() and not l.strip().startswith(('//', '<?php', '/*', '*', 'declare'))]
    check('root index.php is thin (a single require)', (len(code), "require __DIR__ . '/public/index.php';" in idx), (1, True))
    pub = open(os.path.join(ROOT, 'public/index.php')).read()
    check("public/index.php finds bootstrap via dirname(__DIR__)", "dirname(__DIR__) . '/app/helpers/bootstrap.php'" in pub, True)
    check('bootstrap.php exists where that resolves (project root)', os.path.isfile(os.path.join(ROOT, 'app/helpers/bootstrap.php')), True)
    bs = open(os.path.join(ROOT, 'app/helpers/bootstrap.php')).read()
    check('ROOT = dirname(__DIR__, 2) is independent of the entry point', "define('ROOT', dirname(__DIR__, 2))" in bs, True)
    check('routes + views are resolved from ROOT (not the web root)', "require ROOT . '/routes/web.php'" in pub, True)
    print(f'\n{"all checks passed" if not bad else str(bad) + " FAILED"}'); sys.exit(1 if bad else 0)
main()
