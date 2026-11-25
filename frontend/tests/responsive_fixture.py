from playwright.sync_api import sync_playwright
from pathlib import Path
import json
import os, base64
root=Path(__file__).resolve().parents[1]
results=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH') or None,headless=True)
 for width in [320,360,390,768,900,901,1024,1440]:
  for screen in ['home','checkout','dashboard']:
   page=browser.new_page(viewport={'width':width,'height':844},device_scale_factor=1)
   html=(root/'tests/fixtures/responsive.html').read_text().replace('<link rel="stylesheet" href="../../src/app/globals.css">','<style>'+(root/'src/app/globals.css').read_text()+'</style>').replace("new URLSearchParams(location.search).get('screen')",json.dumps(screen)).replace('../../public/hero.svg','data:image/svg+xml;base64,'+base64.b64encode((root/'public/hero.svg').read_bytes()).decode())
   page.set_content(html)
   page.wait_for_function("document.querySelector('#last-action') !== null")
   r=page.evaluate('''() => {const w=document.documentElement.clientWidth;const doc=document.documentElement.scrollWidth;const nav=document.querySelector('.bottom-nav');const visible=getComputedStyle(nav).display!=='none';const navRect=nav.getBoundingClientRect();const spills=[...document.querySelectorAll('.product-price,.metric,.record,.header-inner,.summary')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.className);window.scrollTo(0,document.documentElement.scrollHeight);const last=document.querySelector('#last-action').getBoundingClientRect();return {w,doc,navVisible:visible,navBottom:Math.round(navRect.bottom),lastClear:!visible||last.bottom<=navRect.top+1,spills}}''')
   r.update(screen=screen,width=width,pass_=r['doc']<=width+1 and r['navVisible']==(width<=900) and r['lastClear'] and not r['spills'])
   results.append(r);page.close()
 browser.close()
print(json.dumps(results,indent=2))
(root/'docs/responsive-fixture-results.json').write_text(json.dumps(results,indent=2))

raise SystemExit(0 if all(r["pass_"] for r in results) else 1)
