<?php require 'inc/bootstrap.php';require 'inc/partner.php';need_login();include 'inc/header.php';?>
<div class="sp"><h2 class="title">PARTNERS</h2><p class="sub">The servers we are proudly partnered with.</p>
<div class="pgrid"><?php foreach(q("SELECT * FROM partners ORDER BY id DESC")->fetchAll() as $p):$e=clean_embed($p['embed']);?>
<section class="card pc"><div class="pc-h"><div><h3><?=h($p['name'])?></h3><small>Owner: <?=h($p['owner_name'])?></small></div><a class="btn sm" href="<?=h($p['invite'])?>" target="_blank" rel="noopener">JOIN SERVER</a></div><?=render_embed($e)?></section>
<?php endforeach;?></div><?php if(!q("SELECT 1 FROM partners")->fetch()):?><div class="card center-t"><h3>NO PARTNERS YET</h3><p>Check back soon.</p></div><?php endif;?></div>
<?php include 'inc/footer.php';
