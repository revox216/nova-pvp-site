<?php require 'inc/bootstrap.php';need_login();$s=mta_status();$d=GUILD_ID?discord_info():null;include 'inc/header.php';
$wl=WHITELIST_CHANNEL_ID?'https://discord.com/channels/'.GUILD_ID.'/'.WHITELIST_CHANNEL_ID:null;?>
<section class="hero"><img src="/assets/logo.png" class="biglogo"><h1>GEAR UP. RUN THE CITY.</h1>
<a class="btn" href="<?=h(DISCORD_INVITE)?>" target="_blank">JOIN DISCORD</a><?php if($wl):?><a class="btn ghost" href="<?=h($wl)?>" target="_blank">GET WHITELISTED</a><?php endif;?></section>
<section class="card status"><div><span class="pill <?=$s['online']?'on':'off'?>"><?=$s['online']?'ONLINE':'OFFLINE'?></span><h3><?=h(SERVER_NAME)?></h3>
<div class="row"><span>PLAYER COUNT</span><b><?=$s['players']?> / <?=$s['max']?></b></div><div class="bar"><i style="width:<?=$s['max']?min(100,$s['players']/$s['max']*100):0?>%"></i></div></div>
<div><label>SERVER IP</label><code>mtasa://<?=h(MTA_IP)?>:<?=MTA_PORT?></code><button class="btn" onclick="copy('mtasa://<?=h(MTA_IP)?>:<?=MTA_PORT?>')">COPY SERVER IP</button></div></section>
<?php if($d):?><section class="card dc"><?php if($d['icon']):?><img src="<?=h($d['icon'])?>"><?php endif;?>
<div class="dci"><h3><?=h($d['name'])?></h3><p><span class="dot on"></span><b><?=number_format($d['online'])?></b> Online &nbsp; <span class="dot"></span><b><?=number_format($d['members'])?></b> Members</p></div>
<div><a class="btn" href="<?=h(DISCORD_INVITE)?>" target="_blank">JOIN SERVER</a><?php if($wl):?><a class="btn ghost" href="<?=h($wl)?>" target="_blank">WHITELIST ROOM</a><?php endif;?></div></section><?php endif;?>
<?php include 'inc/footer.php';
