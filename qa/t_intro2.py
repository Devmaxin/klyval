import asyncio, time
from playwright.async_api import async_playwright
URL='http://127.0.0.1:8765/?n=3'
INIT="""(()=>{window.__st=[];const t0=performance.now();
 document.addEventListener('DOMContentLoaded',()=>{const el=document.querySelector('[data-intro]');
  new MutationObserver(()=>window.__st.push([el.dataset.stage,Math.round(performance.now()-t0)])).observe(el,{attributes:true,attributeFilter:['data-stage']});
  new MutationObserver(()=>{if(!document.documentElement.classList.contains('intro-active'))window.__st.push(['overlay-removed',Math.round(performance.now()-t0)])}).observe(document.documentElement,{attributes:true,attributeFilter:['class']});
  window.__m=[];const snap=l=>{const h=document.querySelector('.hero__title');window.__m.push([l,Math.round(performance.now()-t0),document.documentElement.scrollHeight,document.documentElement.clientWidth,Math.round(h.getBoundingClientRect().top*10)/10,Math.round(document.querySelector('.hero__lede').getBoundingClientRect().top*10)/10])};
  setInterval(()=>snap('t'),200)});})()"""
async def run():
  async with async_playwright() as p:
    b=await p.chromium.launch()
    for name,vp,mob in [('desktop',{'width':1440,'height':900},False),('mobile',{'width':390,'height':844},True)]:
      ctx=await b.new_context(viewport=vp,is_mobile=mob,has_touch=mob); await ctx.add_init_script(INIT); pg=await ctx.new_page()
      await pg.goto(URL); await asyncio.sleep(5.8)
      st=await pg.evaluate('window.__st'); m=await pg.evaluate('window.__m')
      print(name,'stages(ms):',st)
      keys={(x[2],x[3]) for x in m}; print(name,'distinct (scrollHeight,clientWidth) over whole run:',keys)
      tops={(x[4],x[5]) for x in m}; print(name,'hero title/lede top values over run:',sorted(tops))
      await ctx.close()
    await b.close()
asyncio.run(run())
