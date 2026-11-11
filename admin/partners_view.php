<?php require_once __DIR__.'/../inc/partner.php';
$edit=isset($_GET['edit'])?q("SELECT * FROM partners WHERE id=?",[(int)$_GET['edit']])->fetch():null;
$E=$edit?(clean_embed($edit['embed'])):['author_name'=>SERVER_NAME,'author_icon'=>SERVER_LOGO_URL,'title'=>'','url'=>'','description'=>'','color'=>'#00ff66','fields'=>[],'image'=>'','thumbnail'=>'','footer_text'=>SERVER_NAME.' • Partnership','footer_icon'=>''];?>
<div class="card"><h3><?=$edit?'Edit partner: '.h($edit['name']):'Add a partner'?></h3>
<p class="sub">Fill the embed on the left, the preview on the right updates instantly. On publish, the bot creates a room in the partner category and posts the message there.<?=$edit?' Saving updates the website and the Discord message.':''?></p>
<form method="post" id="pf" class="pb"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="a" value="<?=$edit?'partner_edit':'partner_add'?>"><?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?><input type="hidden" name="embed_json" id="ej">
<div class="pb-form">
<label>Partner server name</label><input name="name" id="pn" value="<?=h($edit['name']??'')?>" placeholder="Street Boys RP" required>
<label>Discord invite link</label><input name="invite" value="<?=h($edit['invite']??'')?>" placeholder="https://discord.gg/xxxx or a website link" required>
<?php if(!$edit):?><label>Owner Discord ID (gets the Partner role + access to the room)</label><input name="owner_id" id="po" placeholder="123456789012345678" required><?php else:?><p class="sub">Owner: <b><?=h($edit['owner_name'])?></b> (<?=h($edit['owner_id'])?>)</p><?php endif;?>
<details open><summary>Author</summary><label>Name</label><input data-k="author_name"><label>Icon URL</label><input data-k="author_icon"></details>
<details open><summary>Body</summary><label>Title</label><input data-k="title"><label>Title link (optional)</label><input data-k="url"><label>Description</label><textarea data-k="description"></textarea><label>Color</label><input type="color" data-k="color"></details>
<details open><summary>Fields</summary><div id="flds"></div><button type="button" class="btn sm ghost" id="addf">+ ADD FIELD</button></details>
<details><summary>Images</summary><label>Image URL</label><input data-k="image"><label>Thumbnail URL</label><input data-k="thumbnail"></details>
<details><summary>Footer</summary><label>Text</label><input data-k="footer_text"><label>Icon URL</label><input data-k="footer_icon"></details>
<button class="btn"><?=$edit?'SAVE CHANGES':'PUBLISH PARTNER'?></button><?php if($edit):?> <a class="btn ghost" href="?p=partners">CANCEL</a><?php endif;?>
</div>
<div class="pb-prev"><label>Preview</label><div class="dcmsg"><div id="pc1"></div><div id="pc2"><b>For : <span class="mention">@everyone</span></b></div><div id="pemb"></div><span class="dcbtn">Join Server</span></div></div></form>
<script>(()=>{const E=<?=json_encode($E,JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>,EMO=<?=json_encode(PARTNER_EMOJI,JSON_UNESCAPED_UNICODE)?>,OWN=<?=json_encode($edit?$edit['owner_name']:'')?>,ME=<?=json_encode($edit?($edit['created_by']===$me['id']?$me['username']:'creator'):$me['username'],JSON_UNESCAPED_UNICODE)?>;
const $=i=>document.getElementById(i),esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const md=t=>esc(t).replace(/\*\*(.+?)\*\*/gs,'<b>$1</b>').replace(/`([^`]+)`/g,'<code>$1</code>').replace(/(https?:\/\/[^\s<]+)/g,'<a>$1</a>').replace(/\n/g,'<br>');
const nu=u=>{u=(u||'').trim();return /^https?:\/\/\S+$/i.test(u)?u:(/^[\w-]+(\.[\w-]+)+(\/\S*)?$/.test(u)?'https://'+u:'')},ok=u=>nu(u)!=='';
const emo=s=>esc(s).replace(/&lt;a?:\w+:(\d+)&gt;/g,'<img class="emo" src="https://cdn.discordapp.com/emojis/$1.webp?size=32">');
function render(){const p=$('pemb'),c=E.color||'#00ff66';let h='<div class="dce" style="border-left-color:'+esc(c)+'">';
 if(ok(E.thumbnail))h+='<img class="dce-th" src="'+esc(nu(E.thumbnail))+'">';
 if(E.author_name)h+='<div class="dce-au">'+(ok(E.author_icon)?'<img src="'+esc(nu(E.author_icon))+'">':'')+'<span>'+esc(E.author_name)+'</span></div>';
 if(E.title)h+='<div class="dce-ti">'+(ok(E.url)?'<a>'+esc(E.title)+'</a>':esc(E.title))+'</div>';
 if(E.description)h+='<div class="dce-de">'+md(E.description)+'</div>';
 const fs=E.fields.filter(f=>f.name&&f.value);if(fs.length)h+='<div class="dce-fs">'+fs.map(f=>'<div class="dce-f'+(f.inline?' in':'')+'"><div class="dce-fn">'+esc(f.name)+'</div><div class="dce-fv">'+md(f.value)+'</div></div>').join('')+'</div>';
 if(ok(E.image))h+='<img class="dce-im" src="'+esc(nu(E.image))+'">';
 if(E.footer_text)h+='<div class="dce-ft">'+(ok(E.footer_icon)?'<img src="'+esc(nu(E.footer_icon))+'">':'')+'<span>'+esc(E.footer_text)+'</span></div>';
 p.innerHTML=h+'</div>';const o=$('po');$('pc1').innerHTML='<span class="mention">@'+esc(ME)+'</span> '+emo(EMO)+' <span class="mention">@'+esc(OWN||(o&&o.value?o.value:'owner'))+'</span>';}
document.querySelectorAll('#pf [data-k]').forEach(i=>{i.value=E[i.dataset.k]||'';i.addEventListener('input',()=>{E[i.dataset.k]=i.value;render()})});
function drawFields(){const w=$('flds');w.innerHTML='';E.fields.forEach((f,i)=>{const d=document.createElement('div');d.className='fld';
 d.innerHTML='<input placeholder="Field name"><textarea placeholder="Field value"></textarea><label class="inl"><input type="checkbox"> Inline</label><button type="button" class="btn sm red">✕</button>';
 const [n,v,ch,x]=[d.children[0],d.children[1],d.querySelector('input[type=checkbox]'),d.lastChild];n.value=f.name;v.value=f.value;ch.checked=!!f.inline;
 n.oninput=()=>{f.name=n.value;render()};v.oninput=()=>{f.value=v.value;render()};ch.onchange=()=>{f.inline=ch.checked;render()};x.onclick=()=>{E.fields.splice(i,1);drawFields();render()};w.appendChild(d)})}
$('addf').onclick=()=>{if(E.fields.length<25){E.fields.push({name:'',value:'',inline:false});drawFields()}};
$('po')&&$('po').addEventListener('input',render);
$('pf').addEventListener('submit',()=>{$('ej').value=JSON.stringify(E)});
drawFields();render();})();</script></div>
<div class="card"><h3>Partners <small>(<?=q("SELECT COUNT(*) FROM partners")->fetchColumn()?>)</small></h3>
<?php foreach(q("SELECT * FROM partners ORDER BY id DESC")->fetchAll() as $x):?><div class="row" style="border-bottom:1px solid var(--b);padding:10px 0"><div><b><?=h($x['name'])?></b><br><small>Owner: <?=h($x['owner_name'])?> · <a href="<?=h($x['invite'])?>" target="_blank" rel="noopener">invite</a> · <?=h($x['created_at'])?></small></div>
<div><a class="btn sm" href="?p=partners&edit=<?=$x['id']?>">EDIT</a> <?=F('partner_del',HID('id',$x['id']),'REMOVE')?></div></div><?php endforeach;?></div>
