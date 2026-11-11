// Soft navigation: only <main> changes, so the music never stops between pages
(()=>{async function go(url,push){try{const r=await fetch(url,{credentials:'same-origin'});
 if(!r.ok||!(r.headers.get('content-type')||'').includes('text/html'))throw 0;
 const d=new DOMParser().parseFromString(await r.text(),'text/html'),nm=d.querySelector('main'),nl=d.querySelector('nav .links');if(!nm||!nl)throw 0;
 document.title=d.title;document.querySelector('nav .links').innerHTML=nl.innerHTML;const m=document.querySelector('main');m.innerHTML=nm.innerHTML;
 m.querySelectorAll('script').forEach(o=>{const s=document.createElement('script');s.text=o.textContent;o.replaceWith(s)});
 if(push)history.pushState({},'',r.url);scrollTo(0,0);if(window.initPage)initPage();}catch(e){location.href=url}}
document.addEventListener('click',e=>{const a=e.target.closest('a');if(!a||e.defaultPrevented||e.button||e.metaKey||e.ctrlKey||e.shiftKey||a.target||a.hasAttribute('download'))return;
 const u=new URL(a.href,location.href);if(u.origin!==location.origin||/\/(logout|login)\.php$/.test(u.pathname)||(u.hash&&u.pathname===location.pathname))return;e.preventDefault();go(u.href,true)});
addEventListener('popstate',()=>go(location.href,false));
document.addEventListener('submit',e=>{const f=e.target;if((f.method||'get').toLowerCase()!=='get'||e.defaultPrevented)return;e.preventDefault();const u=new URL(f.action||location.href,location.href);u.search=new URLSearchParams(new FormData(f)).toString();go(u.href,true)});})();
