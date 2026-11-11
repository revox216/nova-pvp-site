(()=>{const $=id=>document.getElementById(id),mp=$('mp');if(!mp)return;
fetch('/music.php').then(r=>r.json()).then(T=>{if(!T.length)return;mp.hidden=false;
const a=new Audio(),pt=$('pt'),pn2=$('pn2'),pv=$('pv'),pm=$('pm'),pl=$('pl'),pps=[$('pp'),$('pp2')];let i=0,v=0.5;a.preload='none';
try{const s=localStorage.getItem('nova_vol');if(s!==null)v=+s}catch(e){}
a.volume=v;pv.value=Math.round(v*100);
const icon=()=>{pm.textContent=(a.muted||a.volume===0)?'🔇':a.volume<.5?'🔉':'🔊'};
function load(n,play){i=(n+T.length)%T.length;a.src=T[i].url;pt.textContent=pn2.textContent=T[i].title;pt.title=T[i].title;[...pl.children].forEach((li,k)=>li.classList.toggle('on',k===i));if(play)a.play().catch(()=>{})}
T.forEach((t,k)=>{const li=document.createElement('li');li.textContent=(k+1)+'. '+t.title;li.onclick=()=>load(k,true);pl.appendChild(li)});
const toggle=()=>a.paused?a.play().catch(()=>{}):a.pause();pps.forEach(b=>b.onclick=toggle);
$('pn').onclick=()=>load(i+1,true);$('pb').onclick=()=>load(i-1,true);
a.onplay=()=>pps.forEach(b=>b.textContent='⏸');a.onpause=()=>pps.forEach(b=>b.textContent='▶');a.onended=()=>load(i+1,true);
a.onerror=()=>{pt.textContent='Cannot play this file'};
pv.oninput=()=>{a.volume=pv.value/100;a.muted=false;icon();try{localStorage.setItem('nova_vol',a.volume)}catch(e){}};
pm.onclick=()=>{a.muted=!a.muted;icon()};
$('plb').onclick=e=>{e.stopPropagation();mp.classList.toggle('open')};document.addEventListener('click',e=>{if(!mp.contains(e.target))mp.classList.remove('open')});
// keep state if a full page reload happens (forms): resume the same song, same second
const save=()=>{try{sessionStorage.setItem('nova_mp',JSON.stringify({i,t:a.currentTime,p:!a.paused&&!a.ended}))}catch(e){}};setInterval(save,1000);addEventListener('pagehide',save);
let st=null;try{st=JSON.parse(sessionStorage.getItem('nova_mp')||'null')}catch(e){}
load(st&&T[st.i]?st.i:0,false);icon();
if(st&&st.p){a.addEventListener('loadedmetadata',()=>{try{a.currentTime=st.t}catch(e){}},{once:true});a.play().catch(()=>{})}
}).catch(()=>{})})();
