<?php require 'inc/bootstrap.php';need_login();include 'inc/header.php';
$cats=q("SELECT c.*,(SELECT COUNT(*) FROM products WHERE category_id=c.id) n FROM categories c")->fetchAll();$prods=q("SELECT * FROM products ORDER BY id DESC")->fetchAll();?>
<h2 class="title">SHOP</h2><p class="sub">Pick an item and open a Discord ticket — staff will deliver it directly.</p>
<div class="tabs"><button class="tab on" data-c="all">ALL <i><?=count($prods)?></i></button><?php foreach($cats as $c):?><button class="tab" data-c="<?=$c['id']?>"><?=h($c['name'])?> <i><?=$c['n']?></i></button><?php endforeach;?></div>
<div class="grid p"><?php foreach($prods as $p):$f=final_price($p);?>
<div class="card prod" data-c="<?=$p['category_id']?>"><div class="img" style="background-image:url('<?=h($p['image'])?>')"><?php if($p['sale_percent']>0):?><span class="sale">-<?=$p['sale_percent']?>%</span><?php endif;?></div>
<h4><?=h($p['name'])?></h4><div class="row"><div class="price"><?php if($p['sale_percent']>0):?><s>$<?=$p['price']+0?></s> <?php endif;?>$<?=$f+0?></div><button class="btn sm" onclick="buy(<?=$p['id']?>,'<?=h(addslashes($p['name']))?>','$<?=$f+0?>')">BUY</button></div></div><?php endforeach;?></div>
<div class="modal" id="m"><div class="card"><h3>CHOOSE A PAYMENT METHOD <span onclick="m.classList.remove('open')" style="float:right;cursor:pointer">✕</span></h3><p id="mi"></p>
<?php foreach(['D17'=>'Pay with your D17 app/card.','Flouci'=>'Pay with the Flouci app.','Bank Transfer'=>'Direct bank transfer (RIB shared in your ticket).','PayPal'=>'Pay with PayPal.'] as $k=>$v):?>
<label class="opt"><input type="radio" name="pm" value="<?=$k?>"> <b><?=$k?></b><small><?=$v?></small></label><?php endforeach;?>
<button class="btn" onclick="confirmBuy('<?=csrf()?>')">CONFIRM & OPEN TICKET</button></div></div><div id="toast"></div>
<?php include 'inc/footer.php';
