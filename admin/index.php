<?php require __DIR__.'/../inc/bootstrap.php';need_login();if(!perms()){http_response_code(403);exit('No access');}
$tabs=['staff'=>'Staff','shop'=>'Shop','applies'=>'Applies','apply_settings'=>'Apply Setup','tag_report'=>'Tag Report','warns'=>'Warns','partners'=>'Partners','suggestions'=>'Suggestions','tickets'=>'Tickets','cheaters'=>'Cheaters','report'=>'Report','logs'=>'Logs','access'=>'Access'];
$tabs=array_filter($tabs,fn($k)=>$k=='report'?is_owner():($k=='warns'?(can('warn')||can('warn_manager')):(can($k)||($k=='cheaters'&&can('cheaters_manage')))),ARRAY_FILTER_USE_KEY);$p=$_GET['p']??array_key_first($tabs);if(!isset($tabs[$p]))$p=array_key_first($tabs);
$me=user();$P=$_POST;$a=$P['a']??null;$qq=trim($_GET['q']??'');$like='%'.$qq.'%';
if($a){check_csrf();
 switch($a){
 case 'role_add':if(can('staff')){q("INSERT INTO staff_roles(name,color,sort,align) VALUES(?,?,?,?)",[$P['name'],$P['color'],(int)$P['sort'],$P['align']]);logit('staff_role_add',$P['name']);}break;
 case 'role_edit':if(can('staff')){q("UPDATE staff_roles SET name=?,color=?,sort=?,align=? WHERE id=?",[$P['name'],$P['color'],(int)$P['sort'],$P['align'],$P['id']]);logit('staff_role_edit',"{$P['name']} ({$P['align']})");}break;
 case 'role_del':if(can('staff')){q("DELETE FROM staff WHERE role_id=?",[$P['id']]);q("DELETE FROM staff_roles WHERE id=?",[$P['id']]);logit('staff_role_del','#'.$P['id']);}break;
 case 'staff_add':if(can('staff')){$d=discord('GET','/users/'.preg_replace('/\D/','',$P['did']));$n=$d['global_name']??$d['username']??'Unknown';
  q("INSERT INTO staff(discord_id,role_id,username,avatar) VALUES(?,?,?,?)",[$P['did'],$P['role_id'],$n,avatar_url($P['did'],$d['avatar']??null)]);logit('staff_add',"$n ({$P['did']})");}break;
 case 'staff_del':if(can('staff')){q("DELETE FROM staff WHERE id=?",[$P['id']]);logit('staff_del','#'.$P['id']);}break;
 case 'cat_add':if(can('shop')){q("INSERT INTO categories(name) VALUES(?)",[$P['name']]);logit('shop_cat_add',$P['name']);}break;
 case 'cat_del':if(can('shop')){q("DELETE FROM products WHERE category_id=?",[$P['id']]);q("DELETE FROM categories WHERE id=?",[$P['id']]);logit('shop_cat_del','#'.$P['id']);}break;
 case 'prod_add':if(can('shop')){$img=upload_img($_FILES['img']??[])?:$P['image'];q("INSERT INTO products(category_id,name,image,price,sale_percent) VALUES(?,?,?,?,?)",[$P['cat'],$P['name'],$img,$P['price'],min(99,max(0,(int)$P['sale']))]);logit('shop_prod_add',"{$P['name']} \${$P['price']} -{$P['sale']}%");}break;
 case 'prod_edit':if(can('shop')){q("UPDATE products SET name=?,category_id=?,price=?,sale_percent=? WHERE id=?",[$P['name'],$P['cat'],$P['price'],min(99,max(0,(int)$P['sale'])),$P['id']]);logit('shop_prod_edit',"#{$P['id']} {$P['name']} \${$P['price']} -{$P['sale']}%");}break;
 case 'prod_del':if(can('shop')){q("DELETE FROM products WHERE id=?",[$P['id']]);logit('shop_prod_del','#'.$P['id']);}break;
 case 'form_add':if(can('apply_settings')){$img=upload_img($_FILES['img']??[])?:trim($P['image']??'');q("INSERT INTO apply_forms(name,role_id,image) VALUES(?,?,?)",[$P['name'],$P['role_id'],$img]);logit('form_add',$P['name']);}break;
 case 'form_edit':if(can('apply_settings')){$img=upload_img($_FILES['img']??[])?:trim($P['image']??'');q("UPDATE apply_forms SET name=?,role_id=?,is_open=?,image=? WHERE id=?",[$P['name'],$P['role_id'],isset($P['open'])?1:0,$img,$P['id']]);logit('form_edit',"#{$P['id']} {$P['name']} role:{$P['role_id']}");}break;
 case 'form_del':if(can('apply_settings')){q("DELETE FROM apply_questions WHERE form_id=?",[$P['id']]);q("DELETE FROM apply_forms WHERE id=?",[$P['id']]);logit('form_del','#'.$P['id']);}break;
 case 'qs_add':if(can('apply_settings')){q("INSERT INTO apply_questions(form_id,question,sort) VALUES(?,?,?)",[$P['form'],$P['question'],(int)$P['sort']]);logit('question_add',$P['question']);}break;
 case 'qs_edit':if(can('apply_settings')){q("UPDATE apply_questions SET question=?,sort=? WHERE id=?",[$P['question'],(int)$P['sort'],$P['id']]);logit('question_edit','#'.$P['id']);}break;
 case 'qs_del':if(can('apply_settings')){q("DELETE FROM apply_questions WHERE id=?",[$P['id']]);logit('question_del','#'.$P['id']);}break;
 case 'app_accept':case 'app_reject':case 'app_revert':case 'app_del':if(can('applies')){
  $ap=q("SELECT a.*,f.role_id FROM applications a LEFT JOIN apply_forms f ON f.id=a.form_id WHERE a.id=?",[$P['id']])->fetch();if(!$ap)break;
  if($a=='app_accept'&&$ap['status']=='pending'){if($ap['role_id'])discord('PUT','/guilds/'.GUILD_ID.'/members/'.$ap['user_id'].'/roles/'.$ap['role_id']);
   q("UPDATE applications SET status='accepted',reviewed_by=?,reviewed_at=datetime('now'),reason=NULL WHERE id=?",[$me['username'],$ap['id']]);
   dm($ap['user_id'],['title'=>'✅ Apply Accepted','color'=>0x00ff66,'description'=>"Your **{$ap['form_name']}** application was accepted!",'footer'=>['text'=>SERVER_NAME]]);logit('apply_accept',"{$ap['username']} / {$ap['form_name']} (#{$ap['id']})");}
  if($a=='app_reject'&&$ap['status']=='pending'){$r=trim($P['reason']??'')?:'No reason provided';
   q("UPDATE applications SET status='rejected',reason=?,reviewed_by=?,reviewed_at=datetime('now') WHERE id=?",[$r,$me['username'],$ap['id']]);
   dm($ap['user_id'],['title'=>'❌ Apply Rejected','color'=>0xff3333,'description'=>"Your **{$ap['form_name']}** application was rejected.\n**Reason:** $r",'footer'=>['text'=>SERVER_NAME]]);logit('apply_reject',"{$ap['username']} / {$ap['form_name']} (#{$ap['id']}) reason: $r");}
  if($a=='app_revert'&&$ap['status']!='pending'){q("UPDATE applications SET status='pending',reason=NULL WHERE id=?",[$ap['id']]);logit('apply_revert',"{$ap['username']} (#{$ap['id']}) was {$ap['status']}");}
  if($a=='app_del'){q("DELETE FROM applications WHERE id=?",[$ap['id']]);logit('apply_delete',"{$ap['username']} / {$ap['form_name']} (#{$ap['id']})");}}break;
 case 'tag_send':if(can('tag_report')){
  $pid=preg_replace('/\D/','',$P['pid']??'');$why=trim($P['reason']??'');
  if(!$pid||$why===''){$_SESSION['flash']='Player ID and reason are required.';break;}
  if(!TAG_REPORT_CHANNEL_ID||!TAG_REASON_CHANNEL_ID||!TAG_REPORT_VOICE_ID){$_SESSION['flash']='Tag report channel IDs are missing in config.php.';break;}
  $last=q("SELECT CAST(strftime('%s','now') AS INTEGER)-CAST(strftime('%s',created_at) AS INTEGER) AS age FROM tag_reports WHERE admin_id=? OR player_id=? ORDER BY id DESC LIMIT 1",[$me['id'],$pid])->fetch();
  if($last&&$last['age']<60){$_SESSION['flash']='Cooldown: wait '.(60-$last['age']).' seconds before the next tag report.';break;}
  $r1=discord('POST','/channels/'.TAG_REPORT_CHANNEL_ID.'/messages',['content'=>"<@$pid> join <#".TAG_REPORT_VOICE_ID.">",'allowed_mentions'=>['users'=>[$pid]]]);
  if(empty($r1['id'])){$_SESSION['flash']='Discord error: '.json_encode($r1);break;}
  discord('POST','/channels/'.TAG_REASON_CHANNEL_ID.'/messages',['embeds'=>[['title'=>'🏷️ Tag Report','color'=>0x00ff66,'fields'=>[
   ['name'=>'Player','value'=>"<@$pid>",'inline'=>true],['name'=>'Player ID','value'=>"`$pid`",'inline'=>true],['name'=>"\u{200b}",'value'=>"\u{200b}",'inline'=>true],
   ['name'=>'Admin','value'=>"<@{$me['id']}>",'inline'=>true],['name'=>'Admin ID','value'=>"`{$me['id']}`",'inline'=>true],['name'=>"\u{200b}",'value'=>"\u{200b}",'inline'=>true],
   ['name'=>'Reason','value'=>mb_substr($why,0,1000)]],'timestamp'=>date('c'),'footer'=>['text'=>SERVER_NAME.' • Tag Report']]],'allowed_mentions'=>['parse'=>[]]]);
  q("INSERT INTO tag_reports(player_id,admin_id,admin_name,reason,created_at) VALUES(?,?,?,?,datetime('now'))",[$pid,$me['id'],$me['username'],$why]);
  logit('tag_report',"Player: $pid | Admin: {$me['id']} | Reason: $why");$_SESSION['ok']='Tag report sent ✅';}break;
 case 'cheater_edit':if(can('cheaters_manage')){q("UPDATE cheaters SET discord_name=?,mta_name=?,mta_id=?,proof=? WHERE id=?",[$P['discord_name'],$P['mta_name'],$P['mta_id'],$P['proof'],$P['id']]);logit('cheater_edit',"#{$P['id']} {$P['discord_name']} / MTA {$P['mta_name']} #{$P['mta_id']} / {$P['proof']}");}break;
 case 'cheater_del':if(can('cheaters_manage')){$c=q("SELECT * FROM cheaters WHERE id=?",[$P['id']])->fetch();if($c){q("DELETE FROM cheaters WHERE id=?",[$c['id']]);if(CHEATER_ROLE_ID)discord('DELETE','/guilds/'.GUILD_ID.'/members/'.$c['discord_id'].'/roles/'.CHEATER_ROLE_ID);logit('cheater_del',"{$c['discord_name']} ({$c['discord_id']}) / MTA {$c['mta_name']} #{$c['mta_id']}");}}break;
 case 'warn_add':if(can('warn')||can('warn_manager')){
  $pid=preg_replace('/\D/','',$P['pid']??'');$why=trim($P['reason']??'');$pf=trim($P['proof']??'');
  if(!$pid||$why===''||!is_url($pf)){$_SESSION['flash']='Player ID, reason and a proof link (http/https) are all required.';break;}
  if(!WARN_CHANNEL_ID){$_SESSION['flash']='WARN_CHANNEL_ID is missing in config.php.';break;}
  $d=discord('GET','/users/'.$pid)?:[];$pn=$d['global_name']??$d['username']??'Unknown';
  $r=discord('POST','/channels/'.WARN_CHANNEL_ID.'/messages',['embeds'=>[['author'=>['name'=>SERVER_NAME,'icon_url'=>SERVER_LOGO_URL],'thumbnail'=>['url'=>SERVER_LOGO_URL],'title'=>'⚠️ Player Warned','color'=>0xffaa00,'fields'=>[['name'=>'👮 Admin','value'=>"<@{$me['id']}>",'inline'=>true],['name'=>'🎮 Player','value'=>"<@$pid>",'inline'=>true],['name'=>'📋 Reason','value'=>mb_substr($why,0,1000)]],'image'=>['url'=>WARN_IMAGE_URL],'timestamp'=>date('c'),'footer'=>['text'=>SERVER_NAME.' • '.SITE_URL,'icon_url'=>SERVER_LOGO_URL]]]]);
  if(empty($r['id'])){$_SESSION['flash']='Discord error: '.json_encode($r);break;}
  q("INSERT INTO warns(player_id,player_name,admin_id,admin_name,reason,proof,created_at) VALUES(?,?,?,?,?,?,datetime('now'))",[$pid,$pn,$me['id'],$me['username'],$why,$pf]);
  logit('warn_add',"Player: $pn ($pid) | Reason: $why | Proof: $pf");$_SESSION['ok']='Warn sent ✅';}break;
 case 'warn_edit':if(can('warn_manager')){$pf=trim($P['proof']??'');$why=trim($P['reason']??'');if($why===''||!is_url($pf)){$_SESSION['flash']='Reason and a proof link are required.';break;}
  q("UPDATE warns SET reason=?,proof=? WHERE id=?",[$why,$pf,$P['id']]);logit('warn_edit',"Warn #{$P['id']} | Reason: $why | Proof: $pf");}break;
 case 'warn_del':if(can('warn_manager')){q("DELETE FROM warns WHERE id=?",[$P['id']]);logit('warn_del',"Warn #{$P['id']} removed");}break;
 case 'warn_clear':if(can('warn_manager')){$pid=preg_replace('/\D/','',$P['pid']??'');$n=q("SELECT COUNT(*) FROM warns WHERE player_id=?",[$pid])->fetchColumn();q("DELETE FROM warns WHERE player_id=?",[$pid]);logit('warn_clear',"All $n warns removed for $pid");}break;
 case 'partner_add':case 'partner_edit':case 'partner_del':require __DIR__.'/partners_actions.php';break;
 case 'acc_save':if(can('access')){$id=preg_replace('/\D/','',$P['did']);$pp=implode(',',array_intersect($P['perm']??[],all_perms()));
  q("REPLACE INTO access(discord_id,perms) VALUES(?,?)",[$id,$pp]);logit('access_set',"$id => $pp");}break;
 case 'acc_del':if(can('access')){q("DELETE FROM access WHERE discord_id=?",[$P['did']]);logit('access_del',$P['did']);}break;
 }header('Location: ?p='.urlencode($p));exit;}
function F($act,$fields,$btn='SAVE',$cls=''){$o='<form method="post" enctype="multipart/form-data" class="inl '.$cls.'"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="a" value="'.$act.'">'.$fields.'<button class="btn sm">'.$btn.'</button></form>';return $o;}
function I($n,$v='',$ph='',$t='text'){return '<input type="'.$t.'" name="'.$n.'" value="'.h($v).'" placeholder="'.h($ph).'">';}
function HID($n,$v){return '<input type="hidden" name="'.$n.'" value="'.h($v).'">';}
function SEL($n,$rows,$sel=null){$o='<select name="'.$n.'">';foreach($rows as $r)$o.='<option value="'.$r['id'].'"'.($r['id']==$sel?' selected':'').'>'.h($r['name']).'</option>';return $o.'</select>';}
function SB($p,$ph,$extra=''){global $qq;return '<form method="get" class="sb"><input type="hidden" name="p" value="'.h($p).'">'.$extra.'<input name="q" value="'.h($qq).'" placeholder="'.h($ph).'"><button class="btn sm">SEARCH</button>'.($qq!==''?'<a class="btn sm ghost" href="?p='.h($p).'">CLEAR</a>':'').'</form>';}
function ALSEL($s='left'){$o='<select name="align">';foreach(['left'=>'Left','center'=>'Center','right'=>'Right'] as $k=>$v)$o.='<option value="'.$k.'"'.($k==$s?' selected':'').'>'.$v.'</option>';return $o.'</select>';}
include __DIR__.'/../inc/header.php';?>
<?php if(!empty($_SESSION['flash'])):?><div class="card note bad"><?=h($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif;?>
<?php if(!empty($_SESSION['ok'])):?><div class="card note ok"><?=h($_SESSION['ok'])?></div><?php unset($_SESSION['ok']);endif;?>
<div class="adm"><div class="side"><?php foreach($tabs as $k=>$n):?><a href="?p=<?=$k?>" class="<?=$p==$k?'on':''?>"><?=$n?></a><?php endforeach;?></div><div>
<?php if($p=='staff'):$roles=q("SELECT * FROM staff_roles ORDER BY sort,id")->fetchAll();?>
<div class="card"><h3>Roles</h3><?=F('role_add',I('name','','Role name').I('color','#00ff66','','color').I('sort','0','Order','number').ALSEL('left'),'ADD ROLE')?>
<table><?php foreach($roles as $r):?><tr><td><?=F('role_edit',HID('id',$r['id']).I('name',$r['name']).I('color',$r['color'],'','color').I('sort',$r['sort'],'Order','number').ALSEL($r['align']??'left'),'SAVE')?></td><td><?=F('role_del',HID('id',$r['id']),'DELETE')?></td></tr><?php endforeach;?></table></div>
<div class="card"><h3>Add staff member</h3><?=F('staff_add',I('did','','Discord ID').SEL('role_id',$roles),'ADD')?>
<table><?php foreach(q("SELECT s.*,r.name rn FROM staff s LEFT JOIN staff_roles r ON r.id=s.role_id")->fetchAll() as $s):?><tr><td><img src="<?=h($s['avatar'])?>" width="30"></td><td><?=h($s['username'])?></td><td><?=h($s['discord_id'])?></td><td><?=h($s['rn'])?></td><td><?=F('staff_del',HID('id',$s['id']),'REMOVE')?></td></tr><?php endforeach;?></table></div>
<?php elseif($p=='shop'):$cats=q("SELECT * FROM categories")->fetchAll();?>
<div class="card"><h3>Categories</h3><?=F('cat_add',I('name','','Category name'),'ADD')?><table><?php foreach($cats as $c):?><tr><td><?=h($c['name'])?></td><td><?=F('cat_del',HID('id',$c['id']),'DELETE')?></td></tr><?php endforeach;?></table></div>
<div class="card"><h3>Add product</h3><?php if($cats)echo F('prod_add',SEL('cat',$cats).I('name','','Name').I('price','','Price','number').I('sale','0','Sale %','number').I('image','','Image URL (or upload)').'<input type="file" name="img">','ADD');?></div>
<div class="card"><h3>Products</h3><table><?php foreach(q("SELECT * FROM products ORDER BY id DESC")->fetchAll() as $x):?><tr><td><img src="<?=h($x['image'])?>" width="50"></td><td>
<?=F('prod_edit',HID('id',$x['id']).I('name',$x['name']).SEL('cat',$cats,$x['category_id']).'<input name="price" type="number" step="0.01" value="'.$x['price'].'" style="width:90px"><input name="sale" type="number" value="'.$x['sale_percent'].'" style="width:70px" title="Sale %">','SAVE')?></td>
<td><?=F('prod_del',HID('id',$x['id']),'DELETE')?></td></tr><?php endforeach;?></table></div>
<?php elseif($p=='applies'):$st=$_GET['s']??'pending';?><div class="card"><a class="btn sm ghost" href="?p=applies&s=pending">Pending</a><a class="btn sm ghost" href="?p=applies&s=accepted">Accepted</a><a class="btn sm ghost" href="?p=applies&s=rejected">Rejected</a><?=SB('applies','Search username / Discord ID','<input type="hidden" name="s" value="'.h($st).'">')?></div>
<?php foreach(q("SELECT * FROM applications WHERE status=? AND (?='' OR user_id=? OR username LIKE ?) ORDER BY id DESC LIMIT 100",[$st,$qq,$qq,$like])->fetchAll() as $x):?><div class="card"><div class="row"><b><?=h($x['username'])?> — <?=h($x['form_name'])?> <small>(<?=h($x['user_id'])?>)</small></b><span class="pill <?=$x['status']?>"><?=strtoupper($x['status'])?></span></div>
<?php foreach(json_decode($x['answers'],true)?:[] as $qa):?><p><b><?=h($qa['q'])?></b><br><?=nl2br(h($qa['a']))?></p><?php endforeach;?>
<?php if($x['status']!='pending'):?><p>By <b><?=h($x['reviewed_by'])?></b> <?=h($x['reviewed_at'])?><?=$x['reason']?' — Reason: '.h($x['reason']):''?></p><?=F('app_revert',HID('id',$x['id']),'REVERT TO PENDING')?>
<?php else:?><?=F('app_accept',HID('id',$x['id']),'ACCEPT')?> <?=F('app_reject',HID('id',$x['id']).I('reason','','Reason'),'REJECT','')?><?php endif;?> <?=F('app_del',HID('id',$x['id']),'DELETE')?></div><?php endforeach;?>
<?php elseif($p=='apply_settings'):?><div class="card"><h3>New apply</h3><?=F('form_add',I('name','','Apply name').I('role_id','','Role ID (given when accepted)').I('image','','Image URL (or upload)').'<input type="file" name="img">','ADD')?></div>
<?php foreach(q("SELECT * FROM apply_forms")->fetchAll() as $f):?><div class="card"><?=F('form_edit',HID('id',$f['id']).I('name',$f['name']).I('role_id',$f['role_id'],'Role ID').I('image',$f['image']??'','Image URL (or upload)').'<input type="file" name="img">'.'<label class="inl"><input type="checkbox" name="open" '.($f['is_open']?'checked':'').'> Open</label>','SAVE')?> <?=F('form_del',HID('id',$f['id']),'DELETE FORM')?>
<?php foreach(q("SELECT * FROM apply_questions WHERE form_id=? ORDER BY sort,id",[$f['id']])->fetchAll() as $x):?><div><?=F('qs_edit',HID('id',$x['id']).I('question',$x['question']).'<input name="sort" type="number" value="'.$x['sort'].'" style="width:70px">','SAVE')?> <?=F('qs_del',HID('id',$x['id']),'✕')?></div><?php endforeach;?>
<div><?=F('qs_add',HID('form',$f['id']).I('question','','New question').I('sort','0','','number'),'ADD QUESTION')?></div></div><?php endforeach;?>
<?php elseif($p=='tag_report'):?><div class="card"><h3>New tag report</h3><?=F('tag_send',I('pid','','Player Discord ID').I('reason','','Reason'),'SEND TAG REPORT')?><p class="sub">Cooldown: 60 seconds between tag reports.</p></div>
<div class="card"><h3>Recent tag reports</h3><?=SB('tag_report','Search Discord ID / admin / reason')?><table><tr><th>Date</th><th>Player ID</th><th>Admin</th><th>Reason</th></tr><?php foreach(q("SELECT * FROM tag_reports WHERE (?='' OR player_id=? OR admin_id=? OR admin_name LIKE ? OR reason LIKE ?) ORDER BY id DESC LIMIT 50",[$qq,$qq,$qq,$like,$like])->fetchAll() as $t):?><tr><td><?=h($t['created_at'])?></td><td><?=h($t['player_id'])?></td><td><?=h($t['admin_name'])?></td><td><?=h($t['reason'])?></td></tr><?php endforeach;?></table></div>
<?php elseif($p=='tickets'):if(isset($_GET['t'])&&($t=q("SELECT * FROM tickets WHERE id=?",[(int)$_GET['t']])->fetch())):?>
<div class="card"><a class="back" href="?p=tickets">← Back</a><h3><?=h($t['type'])?> — <?=h($t['username'])?></h3><p class="sub">Opened <?=h($t['created_at'])?> · Closed <?=h($t['closed_at'])?> by <?=h($t['closed_by'])?></p>
<?php foreach(json_decode($t['transcript']??'[]',true)?:[] as $m):?><div class="msg"><img src="<?=h($m['av'])?>"><div><b><?=h($m['a'])?></b> <small><?=h($m['t'])?></small><p><?=nl2br(h($m['c']))?></p><?php foreach($m['f'] as $u):if(strpos($u,'https://')===0):?><a href="<?=h($u)?>" target="_blank" rel="noopener">📎 attachment</a> <?php endif;endforeach;?><?=$m['e']?'<small>[embed]</small>':''?></div></div><?php endforeach;?></div>
<?php else:?><div class="card"><h3>Tickets</h3><?=SB('tickets','Search by Discord username')?><table><tr><th>#</th><th>Type</th><th>User</th><th>Status</th><th>Opened</th><th>Claimed by</th><th>Closed by</th><th></th></tr>
<?php foreach(q("SELECT id,type,username,status,created_at,closed_by,claimed_by FROM tickets WHERE username LIKE ? ORDER BY id DESC LIMIT 200",[$like])->fetchAll() as $t):?><tr><td><?=$t['id']?></td><td><?=h($t['type'])?></td><td><?=h($t['username'])?></td><td><span class="pill <?=$t['status']=='open'?'pending':'accepted'?>"><?=h($t['status'])?></span></td><td><?=h($t['created_at'])?></td><td><?=h($t['claimed_by'])?></td><td><?=h($t['closed_by'])?></td><td><?php if($t['status']=='closed'):?><a class="btn sm" href="?p=tickets&t=<?=$t['id']?>">TRANSCRIPT</a><?php endif;?></td></tr><?php endforeach;?></table></div><?php endif;?>
<?php elseif($p=='cheaters'):$mg=can('cheaters_manage');?><div class="card"><h3>Cheaters list</h3><p class="sub">Click a cheater to see all the info. Cheaters are added from the button in the Discord cheaters room.</p><?=SB('cheaters','Search Discord username / ID / MTA name')?>
<?php foreach(q("SELECT * FROM cheaters WHERE (?='' OR discord_id=? OR discord_name LIKE ? OR mta_name LIKE ? OR mta_id=?) ORDER BY id DESC",[$qq,$qq,$like,$like,$qq])->fetchAll() as $c):?>
<details class="chd"><summary><b><?=h($c['discord_name'])?></b><small><?=h($c['discord_id'])?></small></summary>
<div class="inf"><div><label>MTA username</label><?=h($c['mta_name'])?></div><div><label>MTA ID</label><?=h($c['mta_id'])?></div><div><label>Proof</label><?php if(preg_match('#^https?://#i',$c['proof'])):?><a href="<?=h($c['proof'])?>" target="_blank" rel="noopener">Open proof</a><?php else:?><?=h($c['proof'])?><?php endif;?></div><div><label>Added by</label><?=h($c['added_by_name'])?></div><div><label>Date</label><?=h($c['created_at'])?></div></div>
<?php if($mg):?><div class="inf"><?=F('cheater_edit',HID('id',$c['id']).I('discord_name',$c['discord_name']).I('mta_name',$c['mta_name']).I('mta_id',$c['mta_id']).I('proof',$c['proof']),'SAVE')?> <?=F('cheater_del',HID('id',$c['id']),'REMOVE')?></div><?php endif;?></details><?php endforeach;?></div>
<?php elseif($p=='report'&&is_owner()):?><div class="card"><h3>Website report</h3><p class="sub">A PDF with the statistics, notes and every action of the last 24 hours is sent to your Discord DM automatically every day (the bot must be online). Only the owner can see and download it.</p><a class="btn" href="/report.php">OPEN THE REPORT</a> <a class="btn ghost" href="/report.php?pdf=1">DOWNLOAD PDF (LAST 24H)</a></div>
<?php elseif($p=='partners'):include __DIR__.'/partners_view.php';?>
<?php elseif($p=='warns'):$gm=can('warn_manager');?>
<div class="card"><h3>Give a warn</h3><p class="sub">The reason and a proof link are both required. An embed is sent to the warn room on Discord.</p><?=F('warn_add',I('pid','','Player Discord ID').I('reason','','Reason').I('proof','','Proof link (https://...)'),'SEND WARN')?></div>
<?php if($gm):$by=[];foreach(q("SELECT * FROM warns WHERE (?='' OR player_id=? OR player_name LIKE ? OR admin_name LIKE ?) ORDER BY id DESC",[$qq,$qq,$like,$like])->fetchAll() as $w)$by[(string)$w['player_id']][]=$w;?>
<div class="card"><h3>Players with warns <small>(<?=count($by)?>)</small></h3><?=SB('warns','Search Discord ID / player username / admin username')?>
<?php foreach($by as $pid=>$ws):?><details class="chd"><summary><span><b><?=h($ws[0]['player_name'])?></b> <small>warned by <?=h($ws[0]['admin_name'])?></small></span><span class="bd warn"><?=count($ws)?> WARN<?=count($ws)>1?'S':''?></span></summary>
<div class="inf"><div><label>Player ID</label><?=h($pid)?></div><div><?=F('warn_clear',HID('pid',$pid),'REMOVE ALL WARNS')?></div></div>
<?php foreach($ws as $w):?><div class="inf wn"><div><label>Admin</label><?=h($w['admin_name'])?></div><div><label>Date</label><?=h($w['created_at'])?></div><div><label>Reason</label><?=h($w['reason'])?></div><div><label>Proof</label><a href="<?=h($w['proof'])?>" target="_blank" rel="noopener">Open proof</a></div>
<div class="full"><?=F('warn_edit',HID('id',$w['id']).I('reason',$w['reason']).I('proof',$w['proof']),'SAVE')?> <?=F('warn_del',HID('id',$w['id']),'REMOVE THIS WARN')?></div></div><?php endforeach;?></details><?php endforeach;?></div>
<?php endif;?>
<?php elseif($p=='suggestions'):?><div class="card"><h3>Suggestions</h3><p class="sub">Click a suggestion to see who accepted (upvoted) and who rejected (downvoted) it. Every vote is also in the Logs tab.</p><?=SB('suggestions','Search by number / author / text')?>
<?php foreach(q("SELECT s.*,(SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id=s.id AND vote=1) up,(SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id=s.id AND vote=-1) dn FROM suggestions s WHERE (?='' OR CAST(s.id AS TEXT)=? OR s.author_id=? OR s.author_name LIKE ? OR s.content LIKE ?) ORDER BY s.id DESC LIMIT 100",[$qq,ltrim($qq,'#'),$qq,$like,$like])->fetchAll() as $s):$vs=q("SELECT * FROM suggestion_votes WHERE suggestion_id=? ORDER BY id",[$s['id']])->fetchAll();?>
<details class="chd"><summary><span><b>#<?=$s['id']?></b> <?=h(mb_substr($s['content'],0,70))?></span><small>👍 <?=$s['up']?> · 👎 <?=$s['dn']?></small></summary>
<div class="inf"><div><label>Author</label><?=h($s['author_name'])?> <small><?=h($s['author_id'])?></small></div><div><label>Date</label><?=h($s['created_at'])?></div><div class="full"><label>Suggestion</label><?=nl2br(h($s['content']))?></div></div>
<div class="inf"><div><label>Accepted by (upvotes)</label><?php foreach($vs as $v)if($v['vote']==1):?><?=h($v['username'])?> <small><?=h($v['created_at'])?></small><br><?php endif;?></div><div><label>Rejected by (downvotes)</label><?php foreach($vs as $v)if($v['vote']==-1):?><?=h($v['username'])?> <small><?=h($v['created_at'])?></small><br><?php endif;?></div></div></details><?php endforeach;?></div>
<?php elseif($p=='logs'):?><div class="card"><?=SB('logs','Discord ID: shows everything this person did')?><table><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr><?php foreach(q("SELECT * FROM logs WHERE (?='' OR actor_id=? OR details LIKE ? OR actor_name LIKE ?) ORDER BY id DESC LIMIT 300",[$qq,$qq,$like,$like])->fetchAll() as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['actor_name'])?></td><td><b><?=h($l['action'])?></b></td><td><?=h($l['details'])?></td></tr><?php endforeach;?></table></div>
<?php elseif($p=='access'):
$pi=['staff'=>['Staff','Staff roles and members'],'shop'=>['Shop','Categories and products'],'applies'=>['Applies','Accept / reject applications'],'apply_settings'=>['Apply Setup','Forms and questions'],'tag_report'=>['Tag Report','Send tag reports'],'tickets'=>['Tickets','Ticket transcripts'],'cheaters'=>['Cheaters (view)','See the cheaters list'],'cheaters_manage'=>['Cheaters (manage)','Edit, remove and add cheaters'],'logs'=>['Logs','Site logs'],'access'=>['Access','Give access to others']];
$chips=function($sel=[]) use($pi){$o='<div class="chips">';foreach(all_perms() as $x){[$n,$d]=$pi[$x]??[$x,''];$o.='<label class="chip"><input type="checkbox" name="perm[]" value="'.$x.'" '.(in_array($x,$sel)?'checked':'').'><span><b>'.h($n).'</b><small>'.h($d).'</small></span></label>';}return $o.'</div>';};?>
<div class="card"><h3>Give access</h3><p class="sub">Enter a Discord ID and choose what this person can do in the dashboard.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="a" value="acc_save"><?=I('did','','Discord ID')?><?=$chips()?><button class="btn">SAVE ACCESS</button></form></div>
<h3 class="role">People with access</h3><div class="acc-grid">
<?php foreach(q("SELECT * FROM access")->fetchAll() as $x):$u2=q("SELECT username,avatar FROM users WHERE id=?",[$x['discord_id']])->fetch();
 if(!$u2){$d=discord('GET','/users/'.$x['discord_id']);if(!empty($d['id']))$u2=['username'=>$d['global_name']??$d['username'],'avatar'=>avatar_url($d['id'],$d['avatar']??null)];}
 $u2=$u2?:['username'=>'Unknown user','avatar'=>avatar_url($x['discord_id'],null)];?>
<div class="card acc"><div class="acc-h"><img src="<?=h($u2['avatar'])?>"><div><b><?=h($u2['username'])?></b><small><?=h($x['discord_id'])?></small></div></div>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="a" value="acc_save"><?=HID('did',$x['discord_id'])?><?=$chips(explode(',',$x['perms']))?><button class="btn sm">UPDATE</button></form> <?=F('acc_del',HID('did',$x['discord_id']),'REMOVE ALL')?></div>
<?php endforeach;?></div><?php endif;?>
</div></div><?php include __DIR__.'/../inc/footer.php';
