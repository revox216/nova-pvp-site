<?php
// ---- Partnership helpers: embed sanitising, Discord payload, HTML rendering ----
function embed_ok($e){return $e['title']!==''||$e['description']!==''||$e['fields']||$e['author_name']!==''||$e['image']!=='';}
function clean_embed($j){$d=json_decode((string)$j,true);if(!is_array($d))$d=[];
 $s=fn($v,$n)=>mb_substr(trim((string)$v),0,$n);$u=function($v){$v=norm_url($v);return is_url($v)?mb_substr($v,0,2000):'';};
 $f=[];foreach((is_array($d['fields']??null)?$d['fields']:[]) as $x){if(!is_array($x)||count($f)>=25)continue;$n=$s($x['name']??'',256);$v=$s($x['value']??'',1024);if($n===''||$v==='')continue;$f[]=['name'=>$n,'value'=>$v,'inline'=>!empty($x['inline'])];}
 return ['author_name'=>$s($d['author_name']??'',256),'author_icon'=>$u($d['author_icon']??''),'title'=>$s($d['title']??'',256),'url'=>$u($d['url']??''),'description'=>$s($d['description']??'',4000),
  'color'=>preg_match('/^#[0-9a-f]{6}$/i',$d['color']??'')?$d['color']:'#00ff66','fields'=>$f,'image'=>$u($d['image']??''),'thumbnail'=>$u($d['thumbnail']??''),'footer_text'=>$s($d['footer_text']??'',2000),'footer_icon'=>$u($d['footer_icon']??'')];}
function embed_payload($e){$o=['color'=>hexdec(ltrim($e['color'],'#'))];
 if($e['author_name']!==''){$o['author']=['name'=>$e['author_name']];if($e['author_icon'])$o['author']['icon_url']=$e['author_icon'];}
 if($e['title']!==''){$o['title']=$e['title'];if($e['url'])$o['url']=$e['url'];}
 if($e['description']!=='')$o['description']=$e['description'];if($e['fields'])$o['fields']=$e['fields'];
 if($e['image'])$o['image']=['url'=>$e['image']];if($e['thumbnail'])$o['thumbnail']=['url'=>$e['thumbnail']];
 if($e['footer_text']!==''){$o['footer']=['text'=>$e['footer_text']];if($e['footer_icon'])$o['footer']['icon_url']=$e['footer_icon'];}
 return $o;}
function partner_content($p){return '<@'.$p['creator_id'].'> '.PARTNER_EMOJI.' <@'.$p['owner_id'].">\n**For : @everyone**";}
function partner_message($p,$e){return ['content'=>partner_content($p),'embeds'=>[embed_payload($e)],
 'components'=>[['type'=>1,'components'=>[['type'=>2,'style'=>5,'label'=>'Join Server','url'=>$p['invite']]]]],
 'allowed_mentions'=>['parse'=>PARTNER_PING_EVERYONE?['users','everyone']:['users']]];}
function md($t){$t=h($t);$t=preg_replace('/\*\*(.+?)\*\*/s','<b>$1</b>',$t);$t=preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s','<i>$1</i>',$t);$t=preg_replace('/`([^`]+)`/','<code>$1</code>',$t);
 $t=preg_replace('#(https?://[^\s<]+)#','<a href="$1" target="_blank" rel="noopener">$1</a>',$t);return nl2br($t);}
function render_embed($e){$o='<div class="dce" style="border-left-color:'.h($e['color']).'">';
 if($e['thumbnail'])$o.='<img class="dce-th" src="'.h($e['thumbnail']).'" alt="">';
 if($e['author_name']!=='')$o.='<div class="dce-au">'.($e['author_icon']?'<img src="'.h($e['author_icon']).'" alt="">':'').'<span>'.h($e['author_name']).'</span></div>';
 if($e['title']!=='')$o.='<div class="dce-ti">'.($e['url']?'<a href="'.h($e['url']).'" target="_blank" rel="noopener">'.h($e['title']).'</a>':h($e['title'])).'</div>';
 if($e['description']!=='')$o.='<div class="dce-de">'.md($e['description']).'</div>';
 if($e['fields']){$o.='<div class="dce-fs">';foreach($e['fields'] as $f)$o.='<div class="dce-f'.($f['inline']?' in':'').'"><div class="dce-fn">'.h($f['name']).'</div><div class="dce-fv">'.md($f['value']).'</div></div>';$o.='</div>';}
 if($e['image'])$o.='<img class="dce-im" src="'.h($e['image']).'" alt="">';
 if($e['footer_text']!=='')$o.='<div class="dce-ft">'.($e['footer_icon']?'<img src="'.h($e['footer_icon']).'" alt="">':'').'<span>'.h($e['footer_text']).'</span></div>';
 return $o.'</div>';}
