import asyncio
from playwright.async_api import async_playwright
BASE='http://127.0.0.1:8765'
SKIP="sessionStorage.setItem('kv-intro','1')"
CAP="""(()=>{window.__arrive=null;document.addEventListener('DOMContentLoaded',()=>{window.__arrive=document.documentElement.className})})()"""
async def cls(pg): return await pg.evaluate("document.documentElement.className")
async def overlay(pg): return await pg.evaluate("(()=>{const c=getComputedStyle(document.body,'::after');return [c.visibility,c.clipPath]})()")
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(); R=[]
    def rec(n,ok,extra=''): R.append((n,ok)); print(('PASS  ' if ok else 'FAIL  ')+n+(' '+str(extra) if extra else ''))
    ctx=await b.new_context(viewport={'width':1440,'height':900}); await ctx.add_init_script(SKIP); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.5)
    await pg.click('.nav__link[href="/work"]'); await asyncio.sleep(.1); c=await cls(pg)
    rec('click adds pt-out immediately', 'pt-out' in c, c)
    await pg.wait_for_url('**/work'); await asyncio.sleep(.05)
    arrive=await pg.evaluate('window.__arrive'); rec('next page starts covered (pt-in at DOMContentLoaded)', 'pt-in' in (arrive or ''), arrive)
    await asyncio.sleep(1.0); c=await cls(pg); ov=await overlay(pg)
    rec('overlay fully lifted after ~1s (no pt-* classes)', not any(k in c for k in ('pt-in','pt-out','pt-reveal')), c); rec('overlay hidden', ov[0]=='hidden', ov)
    t=await pg.evaluate("document.querySelector('h1 .mask>span').getBoundingClientRect().height>0"); rec('hero text revealed on new page', t)
    # input never blocked: elementFromPoint at the link is the link, not the overlay
    await pg.goto(BASE+'/studio'); await asyncio.sleep(1.2)
    # modifier click, hash, mailto, target=_blank, file, admin: none intercepted
    for sel,label,mods in [('#home','ctrl-click',['Control']),('#hash','hash link',[]),('#mail','mailto',[]),('#file','file link',[]),('#admin','admin link',[])]:
        await pg.goto(BASE+'/studio'); await asyncio.sleep(1.0)
        try:
            async with pg.expect_navigation(timeout=800): 
                await pg.click(sel, modifiers=mods)
        except Exception: pass
        c=await cls(pg); rec(f'{label}: not intercepted (no pt-out)', 'pt-out' not in c, c)
    # back button (bfcache) never leaves the cover
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.6); await pg.click('.nav__link[href="/studio"]'); await pg.wait_for_url('**/studio'); await asyncio.sleep(1.0)
    await pg.go_back(); await asyncio.sleep(1.0); c=await cls(pg); ov=await overlay(pg)
    rec('after Back: no stuck overlay', not any(k in c for k in ('pt-in','pt-out','pt-reveal')) and ov[0]=='hidden', (c,ov))
    # failsafe: navigation that never completes lifts the cover
    await pg.goto(BASE+'/studio'); await asyncio.sleep(1.0)
    await pg.route('**/work', lambda r: asyncio.ensure_future(asyncio.sleep(30)))
    await pg.evaluate("document.querySelector('.nav__link[href=\"/work\"]').click()"); await asyncio.sleep(4.6); c=await cls(pg)
    rec('failsafe lifts cover if navigation stalls', 'pt-out' not in c, c)
    await ctx.close()
    # first visit: intro and transition do not stack
    ctx=await b.new_context(viewport={'width':1440,'height':900}); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); c=await cls(pg); rec('first visit: intro active, no pt-in', 'intro-active' in c and 'pt-in' not in c, c)
    await asyncio.sleep(5.6); c=await cls(pg); rec('intro done and clean', 'intro-active' not in c and 'pt-' not in c, c)
    await ctx.close()
    # reduced motion
    ctx=await b.new_context(viewport={'width':1440,'height':900},reduced_motion='reduce'); await ctx.add_init_script(SKIP); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.3)
    await pg.click('.nav__link[href="/work"]'); c=await cls(pg); rec('reduced motion: no pt-out on click', 'pt-out' not in c, c)
    await pg.wait_for_url('**/work'); a=await pg.evaluate('window.__arrive'); rec('reduced motion: no pt-in on arrival', 'pt-in' not in (a or ''), a)
    ov=await pg.evaluate("getComputedStyle(document.body,'::after').display"); rec('reduced motion: overlay display none', ov=='none', ov)
    await ctx.close()
    # mobile
    ctx=await b.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True); await ctx.add_init_script(SKIP); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.4); await pg.tap('.nav-toggle'); await asyncio.sleep(.5); await pg.tap('.nav__link[href="/studio"]'); await pg.wait_for_url('**/studio'); await asyncio.sleep(1.2)
    c=await cls(pg); rec('mobile: menu link transition completes cleanly', 'pt-' not in c and 'nav-open' not in (await pg.evaluate('document.body.className')), c)
    await ctx.close(); await b.close()
    print(f"\n{sum(1 for _,o in R if o)} passed, {sum(1 for _,o in R if not o)} failed")
asyncio.run(main())
