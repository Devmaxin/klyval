"""Static audits that need no PHP: route protection, public-query visibility filters, output escaping in templates."""
import re, glob, sys
bad = 0
routes = open('routes/web.php').read()
print('== Routes ==')
router = open('app/core/Router.php').read()
print(' POST adds Csrf automatically:', "array_merge(['Csrf'], $mw)" in router)
rs = re.findall(r"\$router->(get|post)\('([^']+)',\s*'([^']+)'(?:,\s*(\$\w+|\[[^\]]*\]))?", routes)
for m, path, h, mw in rs:
    if path.startswith('/admin') and path not in ('/admin/login',) and not mw:
        print('  FAIL admin route without middleware:', m, path); bad += 1
only = [p for m, p, h, mw in rs if mw == '$AA']
print(' administrator-only routes:', ', '.join(only))
for must in ['/admin/settings', '/admin/media/{id}/delete', '/admin/projects/{id}/delete', '/admin/team/{id}/delete', '/admin/services/{id}/delete', '/admin/insights/{id}/delete']:
    if must not in only and any(must == p for m, p, h, mw in rs): print('  FAIL not admin-only:', must); bad += 1
print('\n== Public queries only return live content ==')
for f in ['app/services/SiteContent.php', 'app/services/Sitemap.php', 'app/services/Seo.php']:
    try: src = open(f).read()
    except FileNotFoundError: continue
    qs = re.findall(r'"((?:SELECT|INSERT|UPDATE)[^"]*)"|\'((?:SELECT)[^\']*)\'', src); print(' scanned', len(qs), 'queries in', f.split('/')[-1])
    for q in qs:
        q = q[0] or q[1]
        for tbl, need in [(r'\bprojects\b', "status = 'published'"), (r'\bteam_members\b', 'is_active = 1'), (r'\bservices\b', 'is_active = 1'), (r'\barticles\b', "status = 'published'")]:
            if re.search(r'(FROM|JOIN)\s+' + tbl[2:-2] + r'\b', q) and need not in q and 'slug_history' not in q:
                print('  CHECK', f.split('/')[-1], '->', tbl[2:-2], 'query without', need, ':', q[:90].replace('\n', ' ')); bad += 1
print('\n== Template output escaping ==')
SAFE_CALLS = r'(?:nl2br\(e\((?:[^()]|\([^()]*\))*\)\)|e\((?:[^()]|\([^()]*\)|\((?:[^()]|\([^()]*\))*\))*\)|csrf_field\(\)|kv_img\(.*\)|img_ratio\(.*\)|\$attr\([^)]*\)|fld\(.*\)|fcheck\(.*\)|fselect\(.*\)|fmedia\(.*\)|admin_pill\(.*\)|asset\([^)]*\)|analytics_tags\(\)|\$v\([^)]*\)|\$err\([^)]*\)|\(int\)\s*\$[\w\[\]\'"]+|\(int\)\([^)]*\)|round\([^)]*\)|str_pad\([^)]*\)|date\([^)]*\)|count\([^)]*\)|is_active\([^)]*\))'
REVIEWED = {"$projects['published']", "$projects['draft']", "$projects['archived']", '$team', '$services', '$media', '$enq[$k]', '$counts[$k]', '$i', "$k ? '?status=' . e($k) : ''", "$k ? '?state=' . e($k) : ''", "str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)", "str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT)", '$mode', '$loopAttrs'}  # integers from COUNT()/loop indexes, or code-set constants (reviewed by hand)
KNOWN = {'views/components/header.php': ['$href', '$label'], 'views/layouts/admin.php': ['$href', '$label', '$content'], 'views/components/intro.php': ['$i']}
def top_ternary(ex):
    d = 0; q = None
    for i, ch in enumerate(ex):
        if ch in '([': d += 1
        elif ch in ')]': d -= 1
        elif ch == '?' and d == 0 and ex[i:i+2] != '??': return i
    return -1
def risky(ex, f):
    ex = ex.strip()
    i = top_ternary(ex)
    if i >= 0:
        rest = ex[i+1:]; j = rest.rfind(':')
        return risky(rest[:j], f) or risky(rest[j+1:], f)
    ex = re.sub(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"", "''", ex)
    ex = re.sub(SAFE_CALLS, '', ex)
    ex = re.sub(r"[\s.]|''", '', ex)
    ex = ex.replace('(string)', '')
    left = re.findall(r'\$\w+', ex)
    left = [v for v in left if v not in KNOWN.get(f, [])]
    return bool(left)
for f in sorted(glob.glob('views/**/*.php', recursive=True)):
    for i, line in enumerate(open(f).read().split('\n'), 1):
        for m in re.finditer(r'<\?=\s*(.+?)\s*\?>', line):
            ex = m.group(1)
            if ex.startswith('$content') or ex in REVIEWED: continue
            if risky(ex, f): print(f'  REVIEW {f}:{i}: {ex[:90]}'); bad += 1
print('\nIssues to review:', bad); sys.exit(1 if bad else 0)
