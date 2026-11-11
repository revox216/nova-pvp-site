<?php require 'inc/bootstrap.php'; if(user()){header('Location: /index.php');exit;}
$url='https://discord.com/api/oauth2/authorize?'.http_build_query(['client_id'=>DISCORD_CLIENT_ID,'redirect_uri'=>SITE_URL.'/callback.php','response_type'=>'code','scope'=>'identify','prompt'=>'consent']);?>
<!doctype html><html><head><meta charset="utf-8"><title>NOVA_PVP</title><link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/style.css"></head>
<body class="center"><div class="card login"><img src="/assets/logo.png" width="320"><p>Sign in with Discord to continue</p><a class="btn" href="<?=h($url)?>">LOGIN WITH DISCORD</a></div></body></html>
