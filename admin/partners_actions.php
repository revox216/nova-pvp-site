<?php // included inside the POST switch of admin/index.php ($P, $me, $a are available)
require_once __DIR__.'/../inc/partner.php';
if(!can('partners'))return;
$fail=function($m){$_SESSION['flash']=$m;};
if($a==='partner_del'){$row=q("SELECT * FROM partners WHERE id=?",[$P['id']??0])->fetch();if(!$row)return;
 if($row['channel_id'])discord('DELETE','/channels/'.$row['channel_id']);
 q("DELETE FROM partners WHERE id=?",[$row['id']]);
 if(PARTNER_ROLE_ID&&!q("SELECT 1 FROM partners WHERE owner_id=?",[$row['owner_id']])->fetch())discord('DELETE','/guilds/'.GUILD_ID.'/members/'.$row['owner_id'].'/roles/'.PARTNER_ROLE_ID);
 logit('partner_del',"{$row['name']} (owner {$row['owner_id']})");$_SESSION['ok']='Partner removed (the room and the partner role were removed too).';return;}
$e=clean_embed($P['embed_json']??'');$name=mb_substr(trim($P['name']??''),0,100);$inv=norm_url($P['invite']??'');
if($name===''||!is_url($inv)||strlen($inv)>500)return $fail('Enter the partner server name and a valid link (a Discord invite or a website, up to 500 characters).');
if(!embed_ok($e))return $fail('The embed is empty: add at least a title, a description or a field.');
if($a==='partner_add'){
 $oid=preg_replace('/\D/','',$P['owner_id']??'');if(!$oid)return $fail('Enter the Discord ID of the partner server owner.');
 if(!GUILD_ID||!PARTNER_CATEGORY_ID)return $fail('GUILD_ID / PARTNER_CATEGORY_ID is missing in config.php.');
 $od=discord('GET','/users/'.$oid)?:[];$on=$od['global_name']??$od['username']??'Unknown';
 $inGuild=!empty((discord('GET','/guilds/'.GUILD_ID.'/members/'.$oid)?:[])['user']);
 $ow=[['id'=>GUILD_ID,'type'=>0,'allow'=>'66560','deny'=>'2048']];if($inGuild)$ow[]=['id'=>$oid,'type'=>1,'allow'=>'68608'];
  $ch=discord('POST','/guilds/'.GUILD_ID.'/channels',['name'=>$name,'type'=>0,'parent_id'=>PARTNER_CATEGORY_ID,'topic'=>'Partner: '.$name,'permission_overwrites'=>$ow]);
 if(empty($ch['id']))return $fail('Could not create the room: '.json_encode($ch));
 $pr=['name'=>$name,'invite'=>$inv,'owner_id'=>$oid,'creator_id'=>$me['id']];$msg=discord('POST','/channels/'.$ch['id'].'/messages',partner_message($pr,$e));
 if(empty($msg['id'])){discord('DELETE','/channels/'.$ch['id']);return $fail('Could not post the embed: '.json_encode($msg));}
 $role=($inGuild&&PARTNER_ROLE_ID)?discord('PUT','/guilds/'.GUILD_ID.'/members/'.$oid.'/roles/'.PARTNER_ROLE_ID):null;
 q("INSERT INTO partners(name,invite,owner_id,owner_name,embed,channel_id,message_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,datetime('now'))",[$name,$inv,$oid,$on,json_encode($e,JSON_UNESCAPED_UNICODE),$ch['id'],$msg['id'],$me['id']]);
 logit('partner_add',"$name | owner $on ($oid) | $inv");
 $_SESSION['ok']="✅ Partner added: room <#{$ch['id']}> created and the embed posted.".(!$inGuild?' ⚠️ The owner is not in your server, so he got no role and no access to the room.':(PARTNER_ROLE_ID?'':' (PARTNER_ROLE_ID is not set in config.php, so no role was given.)'));return;}
if($a==='partner_edit'){$row=q("SELECT * FROM partners WHERE id=?",[$P['id']??0])->fetch();if(!$row)return $fail('Partner not found.');
 q("UPDATE partners SET name=?,invite=?,embed=? WHERE id=?",[$name,$inv,json_encode($e,JSON_UNESCAPED_UNICODE),$row['id']]);
 $r=($row['channel_id']&&$row['message_id'])?discord('PATCH','/channels/'.$row['channel_id'].'/messages/'.$row['message_id'],partner_message(['owner_id'=>$row['owner_id'],'creator_id'=>$row['created_by'],'invite'=>$inv],$e)):null;
 logit('partner_edit',"$name (#{$row['id']})");
 if(empty($r['id']))$_SESSION['flash']='Saved on the website, but the Discord message could not be updated (was it deleted?): '.json_encode($r);else $_SESSION['ok']='✅ Partner updated on the website and in the Discord room.';}
