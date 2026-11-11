<?php require 'inc/bootstrap.php';need_login();include 'inc/header.php';
function sec($r,$al){?><div class="al-<?=$al?>"><h3 class="role" style="color:<?=h($r['color'])?>"><?=h($r['name'])?></h3><div class="grid c"><?php foreach($r['m'] as $x):?>
<div class="card person" style="border-color:<?=h($r['color'])?>"><img src="<?=h($x['avatar'])?>"><b><?=h($x['username'])?></b><small style="color:<?=h($r['color'])?>"><?=h($r['name'])?></small></div><?php endforeach;?></div></div><?php }
$blocks=[];
foreach(q("SELECT * FROM staff_roles ORDER BY sort,id")->fetchAll() as $r){
 $r['m']=q("SELECT * FROM staff WHERE role_id=?",[$r['id']])->fetchAll();if(!$r['m'])continue;
 $al=in_array($r['align']??'',['left','center','right'])?$r['align']:'left';
 if($al=='center'){$blocks[]=['c',$r];continue;}
 if(!$blocks||end($blocks)[0]!='s')$blocks[]=['s',['left'=>[],'right'=>[]]];
 $blocks[count($blocks)-1][1][$al][]=$r;}?>
<div class="sp"><h2 class="title">STAFF</h2>
<?php foreach($blocks as $b):if($b[0]=='c'):sec($b[1],'center');else:?>
<div class="split"><div><?php foreach($b[1]['left'] as $r)sec($r,'left');?></div><div><?php foreach($b[1]['right'] as $r)sec($r,'right');?></div></div>
<?php endif;endforeach;?></div>
<?php include 'inc/footer.php';
