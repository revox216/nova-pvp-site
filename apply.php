<?php require 'inc/bootstrap.php';need_login();$u=user();$msg='';$err='';
$f=isset($_GET['f'])?q("SELECT * FROM apply_forms WHERE id=? AND is_open=1",[(int)$_GET['f']])->fetch():null;
if($f&&$_SERVER['REQUEST_METHOD']=='POST'){check_csrf();
 if(q("SELECT 1 FROM applications WHERE form_id=? AND user_id=? AND status='pending'",[$f['id'],$u['id']])->fetch())$err='You already have a pending application for this form.';
 else{$qs=q("SELECT * FROM apply_questions WHERE form_id=? ORDER BY sort,id",[$f['id']])->fetchAll();$a=[];foreach($qs as $x)$a[]=['q'=>$x['question'],'a'=>trim($_POST['q'.$x['id']]??'')];
 q("INSERT INTO applications(form_id,form_name,user_id,username,answers,created_at) VALUES(?,?,?,?,?,datetime('now'))",[$f['id'],$f['name'],$u['id'],$u['username'],json_encode($a,JSON_UNESCAPED_UNICODE)]);
 logit('apply_submit',"Form: {$f['name']}");$msg='Application submitted! You will receive the result by DM from the bot. 🎉';$f=null;}}
include 'inc/header.php';?>
<h2 class="title">APPLY</h2>
<?php if($msg):?><div class="card note ok"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="card note bad"><?=h($err)?></div><?php endif;?>
<?php if($f):$qs=q("SELECT * FROM apply_questions WHERE form_id=? ORDER BY sort,id",[$f['id']])->fetchAll();?>
<a class="back" href="/apply.php">← Back to positions</a>
<form method="post" class="card form"><h3 class="ftitle"><?=h($f['name'])?></h3><p class="sub"><?=count($qs)?> questions · Please answer honestly and in detail.</p>
<input type="hidden" name="csrf" value="<?=csrf()?>">
<?php foreach($qs as $i=>$x):?><div class="q"><label><span class="num"><?=sprintf('%02d',$i+1)?></span><?=h($x['question'])?></label><textarea name="q<?=$x['id']?>" required></textarea></div><?php endforeach;?>
<button class="btn">SUBMIT APPLICATION</button></form>
<?php else:?>
<p class="sub">Choose a position, answer the questions and wait for the result in your Discord DMs.</p>
<?php $fs=q("SELECT f.*,(SELECT COUNT(*) FROM apply_questions WHERE form_id=f.id) n FROM apply_forms f WHERE is_open=1")->fetchAll();
if(!$fs):?><div class="card center-t"><h3>APPLICATIONS ARE CURRENTLY CLOSED</h3><p>Please check back later.</p></div>
<?php else:?><div class="grid"><?php foreach($fs as $x):?><a class="card ap" href="?f=<?=$x['id']?>"><?php if(!empty($x['image'])):?><div class="aimg" style="background-image:url('<?=h($x['image'])?>')"></div><?php else:?><div class="ico">📋</div><?php endif;?><h3><?=h($x['name'])?></h3><small><?=$x['n']?> questions</small><span class="btn sm">APPLY NOW</span></a><?php endforeach;?></div><?php endif;?>
<?php $my=q("SELECT * FROM applications WHERE user_id=? ORDER BY id DESC",[$u['id']])->fetchAll();if($my):?><h3 class="role">My applications</h3>
<?php foreach($my as $a):?><div class="card myap"><div><b><?=h($a['form_name'])?></b><small><?=h($a['created_at'])?></small><?php if($a['status']=='rejected'&&$a['reason']):?><p class="why">Reason: <?=h($a['reason'])?></p><?php endif;?></div><span class="pill <?=$a['status']?>"><?=strtoupper($a['status'])?></span></div><?php endforeach;endif;endif;?>
<?php include 'inc/footer.php';
