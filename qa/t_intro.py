import asyncio, json, time
from playwright.async_api import async_playwright
URL='http://127.0.0.1:8765/?n=3'
REC="""()=>{window.__st=[];const el=document.querySelector('[data-intro]');if(!el)return;const t0=performance.now();
 window.__st.push(['pre',0]);new MutationObserver(()=>window.__st.push([el.dataset.stage,Math.round(performance.now()-t0)])).observe(el,{attributes:true,attributeFilter:['data-stage']});
 new MutationObserver(()=>{if(!document.documentElement.classList.contains('intro-active'))window.__st.push(['gone',Math.round(performance.now()-t0)])}).observe(document.documentElement,{attributes:true,attributeFilter:['class']});}"""
async def run():
  async with async_playwright() as p:
    b=await p.chromium.launch()
    for name,vp,mobile in [('desktop',{'width':1440,'height':900},False),('mobile',{'width':390,'height':844},True)]:
      ctx=await b.new_context(viewport=vp,is_mobile=mobile,has_touch=mobile); pg=await ctx.new_page()
      errs=[]; pg.on('pageerror',lambda e:errs.append(str(e)))
      await pg.goto(URL,wait_until='commit'); await pg.evaluate(REC)
      t0=time.time(); n=0
      while time.time()-t0<5.4:
        await asyncio.sleep(0.25); 
        if n%2==0 or name=='desktop': await pg.screenshot(path=f'shots/{name}_{int((time.time()-t0)*1000):04d}.png')
        n+=1
      print(name,'stages',await pg.evaluate('window.__st'),'errors',errs)
      # layout jump check
      m=await pg.evaluate("()=>({sh:document.documentElement.scrollHeight,cw:document.documentElement.clientWidth,h1:document.querySelector('.hero__title').getBoundingClientRect().top,inert:document.querySelector('main').inert,gone:!document.querySelector('[data-intro]')})")
      print(name,'after',m)
      # once per session
      await pg.reload(); await asyncio.sleep(0.4)
      print(name,'reload: intro-active =',await pg.evaluate("document.documentElement.classList.contains('intro-active')"),'overlay display =',await pg.evaluate("getComputedStyle(document.querySelector('[data-intro]')).display"))
      await ctx.close()
    # skip button + Esc, desktop
    for how in ['click','esc']:
      ctx=await b.new_context(viewport={'width':1440,'height':900}); pg=await ctx.new_page()
      await pg.goto(URL,wait_until='commit'); await pg.evaluate(REC); await asyncio.sleep(0.7)
      t=time.time()
      if how=='click': await pg.click('[data-intro-skip]')
      else: await pg.keyboard.press('Escape')
      await asyncio.sleep(0.05); st=await pg.evaluate("document.querySelector('[data-intro]').dataset.stage")
      await pg.wait_for_function("!document.documentElement.classList.contains('intro-active')"); print('skip',how,'stage 50ms after =',st,'| overlay fully gone after',round((time.time()-t)*1000),'ms',
        '| main inert:',await pg.evaluate("document.querySelector('main').inert"),'| hero revealed:',await pg.evaluate("document.querySelector('[data-reveal]').classList.contains('is-in')"))
      await ctx.close()
    # reduced motion, no-JS
    ctx=await b.new_context(viewport={'width':1440,'height':900},reduced_motion='reduce'); pg=await ctx.new_page(); await pg.goto(URL)
    print('reduced: intro-active',await pg.evaluate("document.documentElement.classList.contains('intro-active')"),'overlay',await pg.evaluate("getComputedStyle(document.querySelector('[data-intro]')).display"),
      'hero span transform',await pg.evaluate("getComputedStyle(document.querySelector('.hero__title .mask>span')).transform"),'is-in',await pg.evaluate("document.querySelector('[data-reveal]').classList.contains('is-in')"))
    await pg.screenshot(path='shots/reduced.png'); await ctx.close()
    ctx=await b.new_context(viewport={'width':1440,'height':900},java_script_enabled=False); pg=await ctx.new_page(); await pg.goto(URL)
    print('noJS: overlay',await pg.evaluate("getComputedStyle(document.querySelector('[data-intro]')).display") if False else 'n/a (eval disabled)')
    await pg.screenshot(path='shots/nojs.png'); 
    h=await pg.locator('.hero__title .mask>span').first.bounding_box(); w=await pg.locator('.work__track').first.bounding_box(); print('noJS hero text box',h,'| work track',w)
    await ctx.close(); await b.close()
asyncio.run(run())
