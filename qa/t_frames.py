import asyncio, time
from playwright.async_api import async_playwright
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(); ctx=await b.new_context(viewport={'width':1440,'height':900}); pg=await ctx.new_page()
    await pg.goto('http://127.0.0.1:8765/?n=3',wait_until='commit'); t0=time.time(); k=0
    while time.time()-t0<3.7:
        t=time.time()-t0
        if 1.45<t<2.25 or 2.85<t<3.6: await pg.screenshot(path=f'shots/f_{k:02d}_{int(t*1000)}.png',clip={'x':0,'y':380,'width':1440,'height':520}); k+=1
        else: await asyncio.sleep(.05)
    await b.close()
asyncio.run(main())
