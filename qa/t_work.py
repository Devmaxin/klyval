import asyncio, json
from playwright.async_api import async_playwright
SKIP="sessionStorage.setItem('kv-intro','1')"
async def info(pg):
    return await pg.evaluate("""()=>{const s=document.querySelector('[data-work]'),t=s.querySelector('[data-work-track]'),ps=[...s.querySelectorAll('.proj')];
      const r=s.getBoundingClientRect(),vw=document.documentElement.clientWidth;
      return {pinned:s.classList.contains('is-pinned'),secH:Math.round(r.height),top:Math.round(r.top),
        first:Math.round(ps[0].getBoundingClientRect().left),lastR:Math.round(ps.at(-1).getBoundingClientRect().right),vw,
        tr:t.style.transform||'-',wp:s.style.getPropertyValue('--wp')||'-',y:Math.round(scrollY)}}""")
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch()
    for vp in [(1440,900),(1280,720)]:
     for n in [1,2,3,5]:
      ctx=await b.new_context(viewport={'width':vp[0],'height':vp[1]}); await ctx.add_init_script(SKIP); pg=await ctx.new_page()
      await pg.goto(f'http://127.0.0.1:8765/?n={n}'); await asyncio.sleep(.6)
      top=await pg.evaluate("document.querySelector('[data-work]').getBoundingClientRect().top+scrollY")
      i0=await info(pg); res={'vp':vp,'n':n,'pinned':i0['pinned'],'secH':i0['secH']}
      if i0['pinned']:
        H=i0['secH']; samples=[]
        for f in [0,.02,.25,.5,.75,.98,1.0]:
            await pg.evaluate(f"window.scrollTo({{top:{top}+{f}*({H}-innerHeight),behavior:'instant'}})"); await asyncio.sleep(.12)
            x=await info(pg); samples.append((f,x['tr'],x['first'],x['lastR']))
        res['samples']=samples
        # release: scroll past; section should scroll away normally, next section visible
        await pg.evaluate(f"window.scrollTo({{top:{top}+({H}-innerHeight)+300,behavior:'instant'}})"); await asyncio.sleep(.12)
        res['after_release_sec_top']=(await info(pg))['top']
        # wheel stuck test: 10 wheel ticks inside pinned zone; scrollY must increase every tick
        await pg.evaluate(f"window.scrollTo({{top:{top}+50,behavior:'instant'}})"); await asyncio.sleep(.1); ys=[]
        await pg.mouse.move(700,450)
        for _ in range(8): await pg.mouse.wheel(0,120); await asyncio.sleep(.08); ys.append(await pg.evaluate('Math.round(scrollY)'))
        res['wheel_scrollY']=ys
      else:
        pr=await pg.evaluate("(()=>{const ps=[...document.querySelectorAll('.proj')].map(p=>Math.round(p.getBoundingClientRect().width));const ys=[...document.querySelectorAll('.proj')].map(p=>Math.round(p.getBoundingClientRect().top));return {widths:ps,tops:ys,cols:getComputedStyle(document.querySelector('[data-work-track]')).gridTemplateColumns}})()")
        res['fallback']=pr
      print(json.dumps(res)); 
      if n==5 and vp[0]==1440:
        await pg.evaluate(f"window.scrollTo({{top:{top}+.5*({i0['secH']}-innerHeight),behavior:'instant'}})"); await asyncio.sleep(.3); await pg.screenshot(path='shots/work5_mid.png')
        # keyboard: tab until last project link focused
        await pg.evaluate(f"window.scrollTo({{top:{top}-200,behavior:'instant'}})"); await asyncio.sleep(.2)
        await pg.evaluate("document.querySelector('.work__head a').focus()")
        for _ in range(8):
            await pg.keyboard.press('Tab'); await asyncio.sleep(.15)
            f=await pg.evaluate("(()=>{const a=document.activeElement;const r=a.getBoundingClientRect();return [a.getAttribute('href'),Math.round(r.left),Math.round(r.right),document.documentElement.clientWidth]})()")
            print('  tab ->',f)
        await pg.screenshot(path='shots/work5_kbd.png')
        print('  cursor pointer-events:',await pg.evaluate("getComputedStyle(document.querySelector('.cursor')).pointerEvents"))
      await ctx.close()
    # mobile + reduced
    for label,kw in [('mobile',dict(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)),('reduced-desktop',dict(viewport={'width':1440,'height':900},reduced_motion='reduce'))]:
      ctx=await b.new_context(**kw); await ctx.add_init_script(SKIP); pg=await ctx.new_page(); await pg.goto('http://127.0.0.1:8765/?n=5'); await asyncio.sleep(.6)
      x=await info(pg); lay=await pg.evaluate("(()=>({cols:getComputedStyle(document.querySelector('[data-work-track]')).gridTemplateColumns,secH:Math.round(document.querySelector('[data-work]').getBoundingClientRect().height),overflowX:document.documentElement.scrollWidth>document.documentElement.clientWidth}))()")
      print(label,'pinned:',x['pinned'],lay)
      await ctx.close()
    await b.close()
asyncio.run(main())
