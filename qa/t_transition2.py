import asyncio
from playwright.async_api import async_playwright
BASE='http://127.0.0.1:8765'; SKIP="sessionStorage.setItem('kv-intro','1')"
CAP="""(()=>{window.__arrive=null;document.addEventListener('DOMContentLoaded',()=>{window.__arrive=document.documentElement.className})})()"""
cls=lambda pg: pg.evaluate("document.documentElement.className")
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(); R=[]
    def rec(n,ok,x=''): R.append(ok); print(('PASS  ' if ok else 'FAIL  ')+n+(' '+str(x) if x else ''))
    ctx=await b.new_context(viewport={'width':1440,'height':900}); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); c=await cls(pg); rec('first visit: intro active and no pt-in', 'intro-active' in c and 'pt-in' not in c, c)
    await asyncio.sleep(5.4); c=await cls(pg); rec('intro finished, classes clean', 'intro-active' not in c and 'pt-' not in c, c); await ctx.close()
    ctx=await b.new_context(viewport={'width':1440,'height':900},reduced_motion='reduce'); await ctx.add_init_script(SKIP); await ctx.add_init_script(CAP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.3)
    await pg.click('.nav__link[href="/work"]'); c=await cls(pg); rec('reduced motion: no pt-out on click', 'pt-out' not in c, c)
    await pg.wait_for_url('**/work'); a=await pg.evaluate('window.__arrive'); rec('reduced motion: no pt-in on arrival', 'pt-in' not in (a or ''), a)
    d=await pg.evaluate("getComputedStyle(document.body,'::after').display"); rec('reduced motion: overlay display none', d=='none', d); await ctx.close()
    ctx=await b.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True); await ctx.add_init_script(SKIP); pg=await ctx.new_page()
    await pg.goto(BASE+'/?n=3'); await asyncio.sleep(.4); await pg.tap('.nav-toggle'); await asyncio.sleep(.5); await pg.tap('.nav__link[href="/studio"]'); await pg.wait_for_url('**/studio'); await asyncio.sleep(1.2)
    c=await cls(pg); bc=await pg.evaluate('document.body.className'); rec('mobile: menu link transition completes cleanly', 'pt-' not in c and 'nav-open' not in bc, (c,bc)); await ctx.close(); await b.close()
    print(f"\n{sum(R)} passed, {len(R)-sum(R)} failed")
asyncio.run(main())
