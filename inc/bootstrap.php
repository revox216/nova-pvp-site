<?php
ob_start();error_reporting(E_ALL&~E_DEPRECATED);
session_start();require __DIR__.'/../config.php';
defined('DEV_NAME')||define('DEV_NAME','Developer');defined('DEV_DISCORD_ID')||define('DEV_DISCORD_ID','');defined('WHITELIST_CHANNEL_ID')||define('WHITELIST_CHANNEL_ID','');
foreach(['TAG_REPORT_CHANNEL_ID','TAG_REPORT_VOICE_ID','TAG_REASON_CHANNEL_ID','CHEATER_ROLE_ID'] as $_k)defined($_k)||define($_k,'');
function is_owner(){return in_array(user()['id']??'',OWNER_IDS);}
function dlog_events(){return ['channel_create'=>'Channel created','channel_delete'=>'Channel deleted','channel_update'=>'Channel updated (name / topic / category)','role_create'=>'Role created','role_delete'=>'Role deleted','role_update'=>'Role updated (name / color / permissions)','member_join'=>'Member joined','member_leave'=>'Member left','member_ban'=>'Member banned','member_unban'=>'Member unbanned','member_update'=>'Member updated (nickname / roles)','message_delete'=>'Message deleted','message_edit'=>'Message edited','voice_state'=>'Voice join / leave / move'];}
defined('STAFF_WARN_CHANNEL_ID')||define('STAFF_WARN_CHANNEL_ID','1564281525304827996');defined('WARN_CHANNEL_ID')||define('WARN_CHANNEL_ID','');
defined('PARTNER_CATEGORY_ID')||define('PARTNER_CATEGORY_ID','1566052265272279040');defined('PARTNER_ROLE_ID')||define('PARTNER_ROLE_ID','');defined('PARTNER_EMOJI')||define('PARTNER_EMOJI','🤝');defined('PARTNER_PING_EVERYONE')||define('PARTNER_PING_EVERYONE',true);
defined('SERVER_LOGO_URL')||define('SERVER_LOGO_URL',SITE_URL.'/assets/logo.png');defined('WARN_IMAGE_URL')||define('WARN_IMAGE_URL',SITE_URL.'/assets/warn.png');
function norm_url($u){$u=trim((string)$u);if($u!==''&&!preg_match('#^[a-z][a-z0-9+.-]*://#i',$u)&&preg_match('#^[\w-]+(\.[\w-]+)+(/\S*)?$#',$u))$u='https://'.$u;return $u;}
function is_url($u){return (bool)preg_match('#^https?://\S+$#i',$u);}
function all_perms(){return array_values(array_unique(array_merge(PERMS,['tag_report','tickets','cheaters','cheaters_manage','warn','warn_manager','suggestions','discord_stats','partners'])));}
@mkdir(__DIR__.'/../data');$pdo=new PDO('sqlite:'.__DIR__.'/../data/nova.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users(id TEXT PRIMARY KEY,username TEXT,avatar TEXT,last_login TEXT);
CREATE TABLE IF NOT EXISTS access(discord_id TEXT PRIMARY KEY,perms TEXT);
CREATE TABLE IF NOT EXISTS staff_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,color TEXT DEFAULT '#00ff66',sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS staff(id INTEGER PRIMARY KEY AUTOINCREMENT,discord_id TEXT,role_id INTEGER,username TEXT,avatar TEXT);
CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT);
CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY AUTOINCREMENT,category_id INTEGER,name TEXT,image TEXT,price REAL,sale_percent INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT,user_id TEXT,username TEXT,product_name TEXT,price REAL,method TEXT,channel_id TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS apply_forms(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,role_id TEXT,is_open INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS apply_questions(id INTEGER PRIMARY KEY AUTOINCREMENT,form_id INTEGER,question TEXT,sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS applications(id INTEGER PRIMARY KEY AUTOINCREMENT,form_id INTEGER,form_name TEXT,user_id TEXT,username TEXT,answers TEXT,status TEXT DEFAULT 'pending',reason TEXT,reviewed_by TEXT,reviewed_at TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS logs(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id TEXT,actor_name TEXT,action TEXT,details TEXT,created_at TEXT);
SQL
);
if(!in_array('image',array_column($pdo->query('PRAGMA table_info(apply_forms)')->fetchAll(),'name')))$pdo->exec('ALTER TABLE apply_forms ADD COLUMN image TEXT');
$pdo->exec("CREATE TABLE IF NOT EXISTS discord_logs(event TEXT PRIMARY KEY,enabled INTEGER DEFAULT 0,channel_id TEXT,color TEXT DEFAULT '#00ff66')");
$pdo->exec('CREATE TABLE IF NOT EXISTS tickets(id INTEGER PRIMARY KEY AUTOINCREMENT,channel_id TEXT,user_id TEXT,username TEXT,type TEXT,status TEXT,created_at TEXT,closed_at TEXT,closed_by TEXT,transcript TEXT);CREATE TABLE IF NOT EXISTS cheaters(id INTEGER PRIMARY KEY AUTOINCREMENT,discord_id TEXT UNIQUE,discord_name TEXT,mta_name TEXT,mta_id TEXT,proof TEXT,added_by TEXT,added_by_name TEXT,created_at TEXT);CREATE TABLE IF NOT EXISTS bot_state(k TEXT PRIMARY KEY,v TEXT)');
$pdo->exec('PRAGMA busy_timeout=8000;PRAGMA journal_mode=WAL');
$pdo->exec("CREATE TABLE IF NOT EXISTS warns(id INTEGER PRIMARY KEY AUTOINCREMENT,player_id TEXT,player_name TEXT,admin_id TEXT,admin_name TEXT,reason TEXT,proof TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS staff_warns(id INTEGER PRIMARY KEY AUTOINCREMENT,staff_id TEXT,staff_name TEXT,manager_id TEXT,manager_name TEXT,reason TEXT,proof TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS absences(id INTEGER PRIMARY KEY AUTOINCREMENT,staff_id TEXT,staff_name TEXT,reason TEXT,start_date TEXT,end_date TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS suggestions(id INTEGER PRIMARY KEY AUTOINCREMENT,message_id TEXT,channel_id TEXT,author_id TEXT,author_name TEXT,content TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS suggestion_votes(id INTEGER PRIMARY KEY AUTOINCREMENT,suggestion_id INTEGER,user_id TEXT,username TEXT,vote INTEGER,created_at TEXT,UNIQUE(suggestion_id,user_id));
CREATE TABLE IF NOT EXISTS activity(discord_id TEXT,day TEXT,messages INTEGER DEFAULT 0,voice INTEGER DEFAULT 0,PRIMARY KEY(discord_id,day));
CREATE TABLE IF NOT EXISTS members(discord_id TEXT PRIMARY KEY,username TEXT,avatar TEXT);
CREATE TABLE IF NOT EXISTS invite_joins(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id TEXT,inviter_id TEXT,code TEXT,joined_at TEXT,left_at TEXT);
CREATE INDEX IF NOT EXISTS ij_inviter ON invite_joins(inviter_id);
CREATE TABLE IF NOT EXISTS invite_baseline(inviter_id TEXT PRIMARY KEY,uses INTEGER);
CREATE TABLE IF NOT EXISTS partners(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,invite TEXT,owner_id TEXT,owner_name TEXT,embed TEXT,channel_id TEXT,message_id TEXT,created_by TEXT,created_at TEXT);");
if(!in_array('public',array_column($pdo->query('PRAGMA table_info(staff)')->fetchAll(),'name')))$pdo->exec('ALTER TABLE staff ADD COLUMN public INTEGER DEFAULT 1');
if(!in_array('claimed_by',array_column($pdo->query('PRAGMA table_info(tickets)')->fetchAll(),'name')))$pdo->exec('ALTER TABLE tickets ADD COLUMN claimed_by TEXT');
$pdo->exec('CREATE TABLE IF NOT EXISTS tag_reports(id INTEGER PRIMARY KEY AUTOINCREMENT,player_id TEXT,admin_id TEXT,admin_name TEXT,reason TEXT,created_at TEXT)');
if(!in_array('align',array_column($pdo->query('PRAGMA table_info(staff_roles)')->fetchAll(),'name')))$pdo->exec("ALTER TABLE staff_roles ADD COLUMN align TEXT DEFAULT 'left'"); // tables yetsan3ou wa7dhom
function q($s,$p=[]){global $pdo;$x=$pdo->prepare($s);$x->execute($p);return $x;}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function user(){return $_SESSION['user']??null;}
function need_login(){if(!user()){header('Location: /login.php');exit;}}
function perms(){$u=user();if(!$u)return[];if(in_array($u['id'],OWNER_IDS))return all_perms();$r=q("SELECT perms FROM access WHERE discord_id=?",[$u['id']])->fetch();return $r?array_values(array_filter(explode(',',$r['perms']))):[];}
function can($p){return in_array($p,perms());}
function csrf(){return $_SESSION['csrf']??($_SESSION['csrf']=bin2hex(random_bytes(16)));}
function check_csrf(){if(!hash_equals(csrf(),$_POST['csrf']??'')){http_response_code(403);exit('CSRF');}}
function logit($a,$d=''){$u=user();q("INSERT INTO logs(actor_id,actor_name,action,details,created_at) VALUES(?,?,?,?,datetime('now'))",[$u['id']??'system',$u['username']??'system',$a,$d]);}
function discord($m,$p,$d=null){$c=curl_init('https://discord.com/api/v10'.$p);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>SSL_VERIFY,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_USERAGENT=>'DiscordBot (nova, 1.0)',CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bot '.BOT_TOKEN,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$d!==null?json_encode($d):'']);$r=curl_exec($c);return json_decode($r,true);}
function dm($uid,$embed){$ch=discord('POST','/users/@me/channels',['recipient_id'=>$uid]);if(empty($ch['id']))return false;return discord('POST','/channels/'.$ch['id'].'/messages',['embeds'=>[$embed]]);}
function avatar_url($id,$hash){return $hash?"https://cdn.discordapp.com/avatars/$id/$hash.png?size=128":"https://cdn.discordapp.com/embed/avatars/".(((int)$id>>22)%6).".png";}
function final_price($p){return round($p['price']*(100-(int)$p['sale_percent'])/100,2);}
function mta_status(){$c=sys_get_temp_dir().'/mta_'.md5(MTA_IP.MTA_PORT).'.json';if(is_file($c)&&time()-filemtime($c)<30)return json_decode(file_get_contents($c),true);
$r=['online'=>false,'players'=>0,'max'=>MTA_SLOTS];$s=@stream_socket_client('udp://'.MTA_IP.':'.(MTA_PORT+123),$e,$m,2);
if($s){stream_set_timeout($s,2);fwrite($s,'s');$d=@fread($s,4096);if($d&&substr($d,0,4)=='EYE1'){$o=4;$f=[];for($i=0;$i<9;$i++){$l=ord($d[$o]);$f[]=substr($d,$o+1,$l-1);$o+=$l;}$r=['online'=>true,'players'=>(int)$f[7],'max'=>(int)$f[8]];}}
@file_put_contents($c,json_encode($r));return $r;}
function discord_info(){$c=sys_get_temp_dir().'/dc_'.GUILD_ID.'.json';if(is_file($c)&&time()-filemtime($c)<60)return json_decode(file_get_contents($c),true);
$g=discord('GET','/guilds/'.GUILD_ID.'?with_counts=true');if(empty($g['id']))return null;
$r=['name'=>$g['name'],'icon'=>!empty($g['icon'])?"https://cdn.discordapp.com/icons/{$g['id']}/{$g['icon']}.png?size=128":null,'members'=>$g['approximate_member_count']??0,'online'=>$g['approximate_presence_count']??0];
@file_put_contents($c,json_encode($r));return $r;}
function upload_img($f){if(empty($f['name']))return null;
if(($f['error']??4)!==0){$_SESSION['flash']='Upload failed (code '.$f['error'].'): file too big or invalid.';return null;}
$e=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));if(!in_array($e,['png','jpg','jpeg','webp','gif'])){$_SESSION['flash']='Only png, jpg, webp, gif are allowed.';return null;}
$d=__DIR__.'/../uploads';@mkdir($d,0777,true);$n=bin2hex(random_bytes(8)).'.'.$e;
if(!move_uploaded_file($f['tmp_name'],"$d/$n")){$_SESSION['flash']='Could not save the file in the uploads folder.';return null;}
return '/uploads/'.$n;}
