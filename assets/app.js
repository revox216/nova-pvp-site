let cur=null;const $=s=>document.querySelector(s);
function copy(t){navigator.clipboard.writeText(t);toast('Copied!')}
function toast(t){const e=$('#toast')||Object.assign(document.body.appendChild(document.createElement('div')),{id:'toast'});e.textContent=t;e.className='show';setTimeout(()=>e.className='',3500)}
function buy(id,n,p){cur=id;$('#mi').innerHTML='<b>'+n+'</b> — '+p;$('#m').classList.add('open')}
function confirmBuy(c){const m=document.querySelector('input[name=pm]:checked');if(!m)return toast('Choose a payment method');
const f=new FormData();f.append('id',cur);f.append('method',m.value);f.append('csrf',c);
fetch('/buy.php',{method:'POST',body:f}).then(r=>r.json()).then(r=>{toast(r.msg);if(r.ok)$('#m').classList.remove('open')}).catch(()=>toast('Error'))}
function initPage(){document.querySelectorAll('.tab').forEach(t=>t.onclick=()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));t.classList.add('on');
document.querySelectorAll('.prod').forEach(p=>p.style.display=(t.dataset.c=='all'||p.dataset.c==t.dataset.c)?'':'none')});
}
initPage();
// 5 second cooldown on every action button
(()=>{const T=new WeakMap();document.addEventListener('click',e=>{const b=e.target.closest('button.btn,button[type=submit]');if(!b||b.closest('.mp'))return;
const n=Date.now(),u=T.get(b)||0;if(u>n){e.preventDefault();e.stopImmediatePropagation();toast('Please wait '+Math.ceil((u-n)/1000)+'s');return}
T.set(b,n+5000);b.classList.add('cd');setTimeout(()=>b.classList.remove('cd'),5000)},true)})();
