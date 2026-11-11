<?php require 'inc/bootstrap.php';need_login();
if(!is_owner()){http_response_code(403);include 'inc/header.php';echo '<div class="card center-t"><h3>OWNER ONLY</h3><p>You do not have access to this page.</p></div>';include 'inc/footer.php';exit;}
$ev=dlog_events();
$groups=['Channels'=>['📁','Create, delete and edit rooms',['channel_create','channel_delete','channel_update']],'Roles'=>['🎭','Role changes',['role_create','role_delete','role_update']],
 'Members'=>['👥','Joins, leaves, bans and profile changes',['member_join','member_leave','member_ban','member_unban','member_update']],'Messages'=>['💬','Deleted and edited messages',['message_delete','message_edit']],'Voice'=>['🎙️','Voice room activity',['voice_state']]];
if($_SERVER['REQUEST_METHOD']=='POST'){check_csrf();
 foreach($ev as $k=>$l){$ch=preg_replace('/\D/','',$_POST['ch'][$k]??'');$col=preg_match('/^#[0-9a-f]{6}$/i',$_POST['col'][$k]??'')?$_POST['col'][$k]:'#00ff66';
  q("REPLACE INTO discord_logs(event,enabled,channel_id,color) VALUES(?,?,?,?)",[$k,isset($_POST['en'][$k])?1:0,$ch,$col]);}
 logit('discord_logs_save','Discord logs settings updated');if(isset($_SERVER['HTTP_X_AJAX'])){echo 'saved';exit;}$noroom=count(array_filter(q("SELECT 1 x FROM discord_logs WHERE enabled=1 AND (channel_id IS NULL OR channel_id='')")->fetchAll()));$_SESSION['ok']='Settings saved ✅'.($noroom?" — $noroom enabled event(s) still need a room.":'');
 $t=$_POST['test']??'';if(isset($ev[$t])){$s=q("SELECT * FROM discord_logs WHERE event=?",[$t])->fetch();
  if($s&&$s['channel_id']){$r=discord('POST','/channels/'.$s['channel_id'].'/messages',['embeds'=>[['title'=>'🧪 Test log — '.$ev[$t],'description'=>'This is a test embed sent from the website.','color'=>hexdec(ltrim($s['color'],'#')),'timestamp'=>date('c')]]]);
   $_SESSION['ok'].=empty($r['id'])?' — but the test failed (check the bot permissions in that room).':' — test embed sent.';}else $_SESSION['flash']='Choose a room first to send a test.';}
 header('Location: /discord-logs.php');exit;}
$dl=[];foreach(q("SELECT * FROM discord_logs")->fetchAll() as $r)$dl[$r['event']]=$r;
$all=discord('GET','/guilds/'.GUILD_ID.'/channels');$cats=[];$chs=[];
if(is_array($all)&&isset($all[0])){foreach($all as $c)if($c['type']==4)$cats[$c['id']]=$c['name'];usort($all,fn($a,$b)=>$a['position']<=>$b['position']);
 foreach($all as $c)if(in_array($c['type'],[0,5])){$pre=(!empty($c['parent_id'])&&isset($cats[$c['parent_id']]))?$cats[$c['parent_id']].' / ':'';$chs[]=['id'=>$c['id'],'label'=>$pre.'#'.$c['name']];}}
$on=count(array_filter($dl,fn($s)=>$s['enabled']));
include 'inc/header.php';?>
<div class="dl-head"><div><h2 class="title">DISCORD LOGS</h2><p class="sub">Choose which server events are logged, where, and how the embed looks.</p></div>
<div class="dl-stats"><div><b><?=$on?></b><small>ENABLED</small></div><div><b><?=count($ev)?></b><small>EVENTS</small></div><div><b><?=count($chs)?></b><small>ROOMS</small></div></div></div>
<?php if(!empty($_SESSION['ok'])):?><div class="card note ok"><?=h($_SESSION['ok'])?></div><?php unset($_SESSION['ok']);endif;?>
<?php if(!empty($_SESSION['flash'])):?><div class="card note bad"><?=h($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif;?>
<?php if(!$chs):?><div class="card note bad">Could not load the rooms. Check BOT_TOKEN and GUILD_ID in config.php.</div><?php endif;?>
<form method="post" id="dlf" autocomplete="off"><input type="hidden" name="csrf" value="<?=csrf()?>">
<?php foreach($groups as $gn=>[$ic,$desc,$keys]):?><section class="card dgroup"><div class="dg-title"><span class="dg-ic"><?=$ic?></span><div><h3><?=h($gn)?></h3><small><?=h($desc)?></small></div></div>
<?php foreach($keys as $k):$s=$dl[$k]??['enabled'=>0,'channel_id'=>'','color'=>'#00ff66'];?>
<div class="drow <?=$s['enabled']?'is-on':'is-off'?>" style="--c:<?=h($s['color'])?>"><label class="sw"><input type="checkbox" name="en[<?=$k?>]" <?=$s['enabled']?'checked':''?>><i></i></label>
<div class="dname"><b><?=h($ev[$k])?></b><small><?=$k?></small> <span class="bd <?=$s['enabled']?($s['channel_id']?'on':'warn'):'off'?>"><?=$s['enabled']?($s['channel_id']?'ENABLED':'NO ROOM'):'DISABLED'?></span></div>
<select name="ch[<?=$k?>]"><option value="">— choose a room —</option><?php foreach($chs as $c):?><option value="<?=h($c['id'])?>" <?=$c['id']==$s['channel_id']?'selected':''?>><?=h($c['label'])?></option><?php endforeach;?></select>
<label class="col"><input type="color" name="col[<?=$k?>]" value="<?=h($s['color'])?>"><span class="prev" style="border-color:<?=h($s['color'])?>">embed</span></label>
<button class="btn sm ghost" name="test" value="<?=$k?>">TEST</button></div><?php endforeach;?></section><?php endforeach;?>
<div class="savebar"><span>Every change is saved automatically. No bot restart needed.</span><button class="btn">SAVE ALL</button></div></form>
<script>(()=>{const f=document.getElementById('dlf');let t;const save=()=>{clearTimeout(t);t=setTimeout(()=>{fetch('/discord-logs.php',{method:'POST',headers:{'X-Ajax':'1'},body:new FormData(f)}).then(r=>r.text()).then(x=>toast(x==='saved'?'Saved ✅':'Could not save')).catch(()=>toast('Could not save'))},400)};f.querySelectorAll('.drow input,.drow select').forEach(x=>x.addEventListener('change',save))})();document.querySelectorAll('.drow').forEach(r=>{const sw=r.querySelector('.sw input'),c=r.querySelector('.col input'),bd=r.querySelector('.bd'),sel=r.querySelector('select');const upd=()=>{r.classList.toggle('is-on',sw.checked);r.classList.toggle('is-off',!sw.checked);r.style.setProperty('--c',c.value);r.querySelector('.prev').style.borderColor=c.value;bd.className='bd '+(sw.checked?(sel.value?'on':'warn'):'off');bd.textContent=sw.checked?(sel.value?'ENABLED':'NO ROOM'):'DISABLED'};[sw,c,sel].forEach(x=>x.addEventListener('input',upd));sel.addEventListener('change',upd);upd()})</script>
<?php include 'inc/footer.php';
