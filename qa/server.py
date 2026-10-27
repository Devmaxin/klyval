"""QA harness (no PHP needed). Serves a static render of the homepage markup, mirrored from views/, using the REAL
css/js in public/assets. /?n=5 renders 5 projects. Not part of the site."""
import http.server, os, sys, urllib.parse, mimetypes
ROOT = os.path.join(os.path.dirname(__file__), '..', 'public')
def logo(): return '<span class="logo"><svg class="logo__mark" viewBox="0 0 32 32" width="26" height="26" aria-hidden="true"><path d="M7 4v24M26 4 11 16l15 12" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="square"/></svg><span class="logo__word">KLYVAL</span></span>'
def btn(l, h, v='primary', a='→'): return f'<a class="btn btn--{v}" href="{h}"><span class="btn__label">{l}</span><span class="btn__arrow" aria-hidden="true">{a}</span></a>'
def page(n):
    mode = 'one' if n <= 1 else ('two' if n == 2 else 'many')
    cats = ['Client Work', 'Klyval Product / Venture', 'Internal Experiment']
    projs = ''.join(f'<li class="proj"><a class="proj__link" href="/work/placeholder-{i+1}" data-cursor="VIEW"><span class="proj__visual ph" style="--ph-bg:#0b2750"><span class="ph__label">Project visual placeholder</span></span><span class="proj__name">PROJECT NAME</span><span class="proj__meta"><span>{cats[i%3]} · Client name</span><span>Build</span></span><span class="proj__desc">Project description placeholder. <span class="ph-tag">Placeholder</span></span></a></li>' for i in range(n))
    team = ''.join(f'<li><a class="member" href="/team/placeholder-{i+1}" data-cursor="PROFILE"><span class="member__portrait ph" aria-hidden="true"></span><span class="member__body"><span class="member__name"><span class="member__index">0{i+1} /</span> NAME</span><span class="member__role">Role</span></span></a></li>' for i in range(3))
    svc = ''.join(f'<div class="svc wrap" id="{k}"><div class="grid svc__row"><h3 class="svc__name" style="--m:4;--d:7">We {k}.</h3><div class="svc__body" style="--m:4;--d:4;--s:9"><p class="svc__lead">Lead.</p><ul class="svc__list"><li>One</li><li>Two</li></ul></div></div></div>' for k in ['build','create','launch'])
    steps = ''.join(f'<li class="proc__step" data-step><span class="proc__num">0{i+1}</span><h3 class="proc__name">Step</h3><p class="proc__text">Text.</p></li>' for i in range(5))
    intro = '<div class="intro" data-intro data-stage="pre"><div class="intro__grid" aria-hidden="true">' + ''.join(f'<i style="--i:{i}"></i>' for i in range(12)) + f'</div><div class="intro__brand">{logo()}</div><ol class="intro__stages" aria-hidden="true"><li>Build</li><li>Create</li><li>Launch</li></ol><div class="intro__block" aria-hidden="true"></div>' + ''.join(f'<i class="intro__streak" style="--k:{k}" aria-hidden="true"></i>' for k in range(3)) + '<div class="intro__words"><p class="intro__word" data-w="build"><span>WE <br class="br-m">BUILD.</span></p><p class="intro__word" data-w="create"><span>WE <br class="br-m">CREATE.</span></p><p class="intro__word" data-w="launch"><span>WE <br class="br-m">LAUNCH.</span></p></div><button class="intro__skip" type="button" data-intro-skip>Skip intro</button><div class="intro__progress" aria-hidden="true"><i></i></div></div>'
    css = ''.join(f'<link rel="stylesheet" href="/assets/css/{c}.css">' for c in ['tokens','base','components','motion','intro','home'])
    js = ''.join(f'<script src="/assets/js/{j}.js" defer></script>' for j in ['motion','nav','cursor','transitions','intro','work','process','magnetic'])
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>t</title><script src="/assets/js/boot.js"></script>{css}</head><body>{intro}<a class="skip" href="#main">Skip</a>
<header class="site-header site-header--dark" data-nav><a class="brand" href="/">{logo()}</a><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav"><span class="nav-toggle__text">Menu</span></button><nav class="nav" id="primary-nav"><ul class="nav__list"><li><a class="nav__link" href="/work">Work</a></li><li><a class="nav__link" href="/studio">Studio</a></li></ul>{btn('Start a project','/contact','primary','↗')}</nav></header>
<main id="main">
<section class="hero theme-deep"><div class="grid-lines"></div><div class="grid wrap hero__inner"><h1 class="display hero__title" style="--m:4;--d:9" data-reveal><span class="mask"><span>Good ideas</span></span><span class="mask"><span>deserve good</span></span><span class="mask"><span>execution.</span></span></h1><ul class="triad" style="--m:4;--d:3;--s:10"><li><a href="#build">We build.</a></li><li><a href="#create">We create.</a></li><li><a href="#launch">We launch.</a></li></ul><p class="lede hero__lede" style="--m:4;--d:5">We build digital experiences, create content, and launch products for brands ready to move.</p><div class="hero__cta" style="--m:4;--d:6">{btn('Start a project','/contact','primary','↗')}{btn('View our work','/work','ghost')}</div></div></section>
<section class="services theme-light"><div class="grid wrap"><h2 class="h2 services__title" style="--m:4;--d:8">We build things people use.</h2></div>{svc}</section>
<section class="work theme-deep" data-work><div class="work__pin"><div class="wrap work__head"><h2 class="h2">Selected work</h2><div class="work__progress" aria-hidden="true"><i></i></div><a class="btn btn--ghost" href="/work">All work <span class="btn__arrow">→</span></a></div><ul class="work__track" data-work-track data-count="{mode}">{projs}</ul></div></section>
<section class="studio theme-soft" id="studio-test"><div class="grid wrap"><h2 class="display studio__title" style="--m:4;--d:9">We're a small studio.</h2><div class="studio__text" style="--m:4;--d:4;--s:9"><p class="lede">Developers.</p></div><div class="studio__frame ph" style="--m:4;--d:12"></div></div></section>
<section class="team theme-light"><div class="wrap"><h2 class="h2 team__title">Who you'll work with.</h2><ul class="team__list">{team}</ul></div></section>
<section class="proc theme-electric" data-process><div class="grid wrap"><div class="proc__aside" style="--m:4;--d:5"><h2 class="h2">How Klyval works</h2><div class="proc__bar" aria-hidden="true"><i></i></div></div><ol class="proc__steps" style="--m:4;--d:6;--s:7">{steps}</ol></div></section>
<section class="cta theme-deep"><div class="grid-lines"></div><div class="grid wrap cta__inner"><h2 class="display cta__title" style="--m:4;--d:11" data-reveal><span class="mask"><span>Have something</span></span><span class="mask"><span>to build?</span></span></h2><p class="lede" style="--m:4;--d:5">Tell us.</p><div class="magnetic" style="--m:4;--d:6" data-magnetic>{btn('Start a project','/contact')}</div></div></section>
</main><footer class="site-footer theme-deep"><p class="wrap site-footer__copy">© footer</p></footer>{js}</body></html>'''
def simple(path):
    css = ''.join(f'<link rel="stylesheet" href="/assets/css/{c}.css">' for c in ['tokens','base','components','motion'])
    js = ''.join(f'<script src="/assets/js/{j}.js" defer></script>' for j in ['motion','nav','cursor','transitions'])
    return f'<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{path}</title><script src="/assets/js/boot.js"></script>{css}</head><body><header class="site-header site-header--dark" data-nav><a class="brand" href="/">K</a><button class="nav-toggle" aria-expanded="false" aria-controls="primary-nav"><span class="nav-toggle__text">Menu</span></button><nav class="nav" id="primary-nav"><ul class="nav__list"><li><a class="nav__link" href="/work">Work</a></li><li><a class="nav__link" href="/studio">Studio</a></li></ul></nav></header><main class="theme-deep" style="min-height:100vh;padding-top:8rem"><h1 class="display" data-reveal><span class="mask"><span>{path}</span></span></h1><p><a id="home" href="/">Home</a> <a id="hash" href="#x">hash</a> <a id="mail" href="mailto:a@b.test">mail</a> <a id="blank" href="/work" target="_blank">blank</a> <a id="file" href="/sitemap.xml">file</a> <a id="admin" href="/admin">admin</a> <a id="slow" href="/slow">slow</a></p></main>{js}</body></html>'
class H(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def do_GET(self):
        u = urllib.parse.urlparse(self.path)
        if u.path == '/':
            n = int(urllib.parse.parse_qs(u.query).get('n', ['3'])[0]); body = page(n).encode(); ct = 'text/html'
        elif u.path == '/slow':
            import time; time.sleep(9); body = simple('/slow').encode(); ct = 'text/html'
        elif u.path in ('/work', '/studio', '/services', '/contact'):
            body = simple(u.path).encode(); ct = 'text/html'
        else:
            f = os.path.join(ROOT, u.path.lstrip('/'))
            if not os.path.isfile(f): self.send_response(404); self.end_headers(); return
            body = open(f, 'rb').read(); ct = mimetypes.guess_type(f)[0] or 'application/octet-stream'
        self.send_response(200); self.send_header('Content-Type', ct); self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)
if __name__ == '__main__':
    http.server.ThreadingHTTPServer(('127.0.0.1', 8765), H).serve_forever()
