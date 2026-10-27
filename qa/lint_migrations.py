"""Static check of database/migrations (no MySQL available here): FK targets exist earlier and are indexed,
column types match, SET NULL columns are nullable, statement splitting is safe. Also writes docs/schema.md."""
import glob, re, sys, os
tables = {}; errors = []; order = []
def split_top(s):
    out, d, cur = [], 0, ''
    for ch in s:
        if ch == '(': d += 1
        if ch == ')': d -= 1
        if ch == ',' and d == 0: out.append(cur.strip()); cur = ''
        else: cur += ch
    if cur.strip(): out.append(cur.strip())
    return out
def cols(x): return [c.strip().strip('`') for c in x.split(',')]
for f in sorted(glob.glob('database/migrations/*.sql')):
    raw = open(f).read()
    nocomment = re.sub(r'--[^\n]*', '', raw)
    stmts = [s.strip() for s in raw.split(';') if s.strip()]
    real = [s for s in (re.sub(r'--[^\n]*', '', x).strip() for x in stmts) if s]
    if len(real) != len([x for x in nocomment.split(';') if x.strip()]): errors.append(f'{f}: a semicolon inside a comment breaks the runner')
    for st in real:
        m = re.match(r'CREATE TABLE IF NOT EXISTS (\w+) \((.*)\)\s*ENGINE', st, re.S)
        if not m:
            if st.startswith('ALTER TABLE'): order.append((f, 'ALTER ' + st.split()[2])); continue
            errors.append(f'{f}: unparsed statement {st[:40]}'); continue
        name, body = m.group(1), m.group(2)
        t = dict(file=os.path.basename(f), cols={}, pk=[], uq=[], keys=[], fks=[]); tables[name] = t; order.append((f, name))
        for d in split_top(body):
            if d.startswith('PRIMARY KEY'): t['pk'] = cols(re.search(r'\((.*)\)', d).group(1))
            elif d.startswith('UNIQUE KEY'): n, c = re.match(r'UNIQUE KEY (\w+) \((.*)\)', d).groups(); t['uq'].append((n, cols(c)))
            elif d.startswith('KEY'): n, c = re.match(r'KEY (\w+) \((.*)\)', d).groups(); t['keys'].append((n, cols(c)))
            elif d.startswith('CONSTRAINT'):
                n, c, rt, rc, act = re.match(r'CONSTRAINT (\w+) FOREIGN KEY \((.*?)\) REFERENCES (\w+) \((.*?)\) ON DELETE (SET NULL|CASCADE|RESTRICT)', d).groups()
                t['fks'].append((n, cols(c), rt, cols(rc), act))
            else:
                parts = d.split(None, 1); cn = parts[0]; spec = parts[1]
                t['cols'][cn] = dict(spec=spec, null='NOT NULL' not in spec and 'PRIMARY KEY' not in spec, type=re.split(r'\s+(NOT|NULL|DEFAULT|AUTO_INCREMENT|PRIMARY)', spec)[0].strip())
                if 'PRIMARY KEY' in spec: t['pk'] = [cn]
def indexed(t, c):  # c is a column list that must be a PK/UNIQUE prefix
    return t['pk'][:len(c)] == c or any(u[1][:len(c)] == c for u in t['uq'])
for n, t in tables.items():
    for fk in t['fks']:
        fn, c, rt, rc, act = fk
        if rt not in tables: errors.append(f'{n}.{fn}: references unknown/later table {rt}'); continue
        if not indexed(tables[rt], rc): errors.append(f'{n}.{fn}: {rt}{rc} is not PK/UNIQUE')
        for a, b in zip(c, rc):
            ta = t['cols'][a]['type'].replace(' ', ''); tb = tables[rt]['cols'][b]['type'].replace(' ', '')
            if ta != tb: errors.append(f'{n}.{a} type {ta} != {rt}.{b} {tb}')
        if act == 'SET NULL' and not all(t['cols'][a]['null'] for a in c): errors.append(f'{n}.{fn}: SET NULL on NOT NULL column')
        if not (t['pk'][:len(c)] == c or any(k[1][:len(c)] == c for k in t['keys'] + t['uq'])): errors.append(f'{n}.{fn}: FK column has no leading index')
# creation order = dependency order?
seen = set(['admin_users', 'site_settings', 'contact_submissions'])
for f, name in order:
    if name in tables:
        for fk in tables[name]['fks']:
            if fk[2] not in seen and fk[2] != name: errors.append(f'{name}: created before its parent {fk[2]}')
        seen.add(name)
print('tables parsed:', len(tables), '| errors:', len(errors)); [print('  ERROR', e) for e in errors]
# ---- write the schema map ----
L = ['# Klyval schema map (generated from database/migrations by qa/lint_migrations.py)\n', 'Order everywhere: `display_order ASC, id ASC` (id is the stable tie-breaker).\n']
for n, t in tables.items():
    L.append(f'\n## {n}  _(migration {t["file"]})_')
    L.append('columns: ' + ', '.join(t['cols']))
    L.append('PK: ' + ', '.join(t['pk']))
    if t['uq']: L.append('unique: ' + '; '.join(f'{u[0]}({", ".join(u[1])})' for u in t['uq']))
    if t['keys']: L.append('indexes: ' + '; '.join(f'{k[0]}({", ".join(k[1])})' for k in t['keys']))
    for fk in t['fks']: L.append(f'FK {fk[0]}: {", ".join(fk[1])} → {fk[2]}({", ".join(fk[3])}) ON DELETE {fk[4]}')
open('docs/schema.md', 'w').write('\n'.join(L) + '\n')
sys.exit(1 if errors else 0)
