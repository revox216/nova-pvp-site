<?php require 'inc/bootstrap.php';
if(empty($_GET['code']))exit(header('Location: /login.php'));
function http($url,$o){$c=curl_init($url);curl_setopt_array($c,$o+[CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>SSL_VERIFY,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'DiscordBot (nova, 1.0)']);$r=curl_exec($c);return [json_decode((string)$r,true),curl_error($c),(string)$r];}
function fail($step,$e,$raw){exit('<pre style="padding:20px;font-size:15px">Login failed at: '.h($step)."\ncurl error: ".h($e?:'none')."\nDiscord says: ".h($raw)."\nSITE_URL: ".h(SITE_URL)."\n\n(Send this text to the developer, then retry: /login.php)</pre>");}
[$t,$e,$raw]=http('https://discord.com/api/oauth2/token',[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>DISCORD_CLIENT_ID,'client_secret'=>DISCORD_CLIENT_SECRET,'grant_type'=>'authorization_code','code'=>$_GET['code'],'redirect_uri'=>SITE_URL.'/callback.php'])]);
if(empty($t['access_token']))fail('token exchange',$e,$raw);
[$d,$e,$raw]=http('https://discord.com/api/users/@me',[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$t['access_token']]]);
if(empty($d['id']))fail('reading your profile',$e,$raw);
$name=$d['global_name']??$d['username'];$av=avatar_url($d['id'],$d['avatar']??null);
q("REPLACE INTO users VALUES(?,?,?,datetime('now'))",[$d['id'],$name,$av]);
session_regenerate_id(true);$_SESSION['user']=['id'=>$d['id'],'username'=>$name,'avatar'=>$av];logit('login','');
session_write_close();header('Location: /index.php');
