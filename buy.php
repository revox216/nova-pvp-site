<?php require 'inc/bootstrap.php';header('Content-Type: application/json');if(!user())exit(json_encode(['ok'=>0,'msg'=>'Login required']));check_csrf();
$p=q("SELECT * FROM products WHERE id=?",[(int)$_POST['id']])->fetch();$m=$_POST['method']??'';
if(!$p||!in_array($m,['D17','Flouci','Bank Transfer','PayPal']))exit(json_encode(['ok'=>0,'msg'=>'Invalid request']));
if(($_SESSION['last_buy']??0)>time()-20)exit(json_encode(['ok'=>0,'msg'=>'Please wait a bit before placing a new order']));
$u=user();$price=final_price($p);$code=substr(bin2hex(random_bytes(4)),0,7);
$slug=substr(preg_replace('/[^a-z0-9]+/','-',strtolower($u['username'])),0,20)?:$u['id'];
$ow=[['id'=>GUILD_ID,'type'=>0,'deny'=>'1024'],['id'=>$u['id'],'type'=>1,'allow'=>'3072']];foreach(TICKET_STAFF_ROLE_IDS as $r)$ow[]=['id'=>$r,'type'=>0,'allow'=>'3072'];
$ch=discord('POST','/guilds/'.GUILD_ID.'/channels',['name'=>'ticket-'.$slug,'type'=>0,'parent_id'=>SHOP_CATEGORY_ID,'permission_overwrites'=>$ow]);
if(empty($ch['id']))exit(json_encode(['ok'=>0,'msg'=>'The bot could not create the ticket. Check its permissions and the config.']));
$img=(strpos($p['image'],'http')===0)?$p['image']:SITE_URL.$p['image'];
discord('POST','/channels/'.$ch['id'].'/messages',['content'=>'<@'.$u['id'].'>','embeds'=>[['title'=>'New Purchase — Order Confirmed','color'=>0x00ff66,'description'=>"Hello <@{$u['id']}>, thank you for your order!\nA member of staff will process your purchase here. Please follow the payment instructions for the method you selected.",
'fields'=>[['name'=>'Item','value'=>'`'.$p['name'].'`'],['name'=>'Price','value'=>'`$'.($price+0).'`','inline'=>true],['name'=>'Payment Method','value'=>'`'.$m.'`','inline'=>true],['name'=>'Order ID','value'=>'`'.$code.'`','inline'=>true],['name'=>'Buyer','value'=>"Member: <@{$u['id']}>\nID: `{$u['id']}`"]],'thumbnail'=>['url'=>$img],'image'=>['url'=>$img],'footer'=>['text'=>SERVER_NAME.' • Shop Ticket System']]],'components'=>[['type'=>1,'components'=>[['type'=>2,'style'=>4,'label'=>'Close','custom_id'=>'ticket_close','emoji'=>['name'=>'🔒']],['type'=>2,'style'=>1,'label'=>'Claim','custom_id'=>'ticket_claim','emoji'=>['name'=>'🙋']],['type'=>2,'style'=>2,'label'=>'Send DM','custom_id'=>'ticket_dm','emoji'=>['name'=>'✉️']]]]]]);
q("INSERT INTO orders(code,user_id,username,product_name,price,method,channel_id,created_at) VALUES(?,?,?,?,?,?,?,datetime('now'))",[$code,$u['id'],$u['username'],$p['name'],$price,$m,$ch['id']]);
q("INSERT INTO tickets(channel_id,user_id,username,type,status,created_at) VALUES(?,?,?,?,'open',datetime('now'))",[$ch['id'],$u['id'],$u['username'],'shop']);
logit('shop_buy',"Item: {$p['name']} | Price: \${$price} | Method: $m | Order: $code | Ticket: #{$ch['id']}");$_SESSION['last_buy']=time();
echo json_encode(['ok'=>1,'msg'=>'Purchase request sent! Check Discord for your ticket.']);
