<?php
// ---- Daily website report: data, notes, tiny PDF writer, Discord DM delivery ----
const REP_TZ='Africa/Tunis';
function rep_loc($utc,$f='d/m H:i'){try{return (new DateTime($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(REP_TZ))->format($f);}catch(Exception $e){return (string)$utc;}}
function rep_range($r){$u=new DateTimeZone('UTC');$tz=new DateTimeZone(REP_TZ);$now=new DateTime('now',$u);$fmt=fn($d)=>$d->setTimezone($u)->format('Y-m-d H:i:s');
 if($r==='today'){$s=new DateTime('today',$tz);return [$fmt($s),$fmt(clone $now),'Today'];}
 if($r==='yesterday'){$s=new DateTime('yesterday',$tz);$e=new DateTime('today',$tz);return [$fmt($s),$fmt($e),'Yesterday'];}
 if($r==='7d'){$s=(clone $now)->modify('-7 days');return [$fmt($s),$fmt(clone $now),'Last 7 days'];}
 $s=(clone $now)->modify('-24 hours');return [$fmt($s),$fmt(clone $now),'Last 24 hours'];}
function report_data($from,$to){$P=[$from,$to];$c=fn($sql,$p=null)=>(int)q($sql,$p??$P)->fetchColumn();$W="created_at>? AND created_at<=?";$d=['from'=>$from,'to'=>$to];
 $d['actions']=$c("SELECT COUNT(*) FROM logs WHERE $W");$d['logins']=$c("SELECT COUNT(*) FROM logs WHERE action='login' AND $W");$d['active']=$c("SELECT COUNT(DISTINCT actor_id) FROM logs WHERE actor_id!='system' AND $W");
 $d['users_total']=(int)q("SELECT COUNT(*) FROM users")->fetchColumn();
 $d['by_action']=q("SELECT action,COUNT(*) c FROM logs WHERE $W GROUP BY action ORDER BY c DESC LIMIT 20",$P)->fetchAll();
 $d['by_user']=q("SELECT actor_name n,actor_id i,COUNT(*) c FROM logs WHERE actor_id!='system' AND action!='login' AND $W GROUP BY actor_id ORDER BY c DESC LIMIT 10",$P)->fetchAll();
 $d['logs']=q("SELECT * FROM logs WHERE $W AND action!='login' ORDER BY id DESC LIMIT 400",$P)->fetchAll();
 $o=q("SELECT COUNT(*) n,COALESCE(SUM(price),0) s FROM orders WHERE $W",$P)->fetch();$d['orders']=(int)$o['n'];$d['revenue']=(float)$o['s'];
 $d['top_products']=q("SELECT product_name n,COUNT(*) c FROM orders WHERE $W GROUP BY product_name ORDER BY c DESC LIMIT 3",$P)->fetchAll();
 $d['apps_new']=$c("SELECT COUNT(*) FROM applications WHERE $W");$d['apps_ok']=$c("SELECT COUNT(*) FROM applications WHERE status='accepted' AND reviewed_at>? AND reviewed_at<=?");$d['apps_no']=$c("SELECT COUNT(*) FROM applications WHERE status='rejected' AND reviewed_at>? AND reviewed_at<=?");
 $d['apps_pending']=$c("SELECT COUNT(*) FROM applications WHERE status='pending'",[]);$d['apps_old']=$c("SELECT COUNT(*) FROM applications WHERE status='pending' AND created_at<=?",[$from]);
 $d['t_open']=$c("SELECT COUNT(*) FROM tickets WHERE $W");$d['t_closed']=$c("SELECT COUNT(*) FROM tickets WHERE closed_at>? AND closed_at<=?");$d['t_now']=$c("SELECT COUNT(*) FROM tickets WHERE status='open'",[]);$d['t_old']=$c("SELECT COUNT(*) FROM tickets WHERE status='open' AND created_at<=?",[$from]);
 $d['cheaters']=$c("SELECT COUNT(*) FROM cheaters WHERE $W");$d['warns']=$c("SELECT COUNT(*) FROM warns WHERE $W");$d['warn_players']=$c("SELECT COUNT(DISTINCT player_id) FROM warns WHERE $W");$d['tags']=$c("SELECT COUNT(*) FROM tag_reports WHERE $W");
 $d['sug']=$c("SELECT COUNT(*) FROM suggestions WHERE $W");$d['votes']=$c("SELECT COUNT(*) FROM suggestion_votes WHERE $W");$d['partners']=$c("SELECT COUNT(*) FROM partners WHERE $W");
 $a=gmdate('Y-m-d',strtotime($from.' UTC'));$b=gmdate('Y-m-d',strtotime($to.' UTC'));$ac=q("SELECT COALESCE(SUM(messages),0) m,COALESCE(SUM(voice),0) v FROM activity WHERE day>=? AND day<=?",[$a,$b])->fetch();
 $d['msgs']=(int)$ac['m'];$d['voice']=round($ac['v']/3600,1);$d['days']=[$a,$b];$d['joins']=$c("SELECT COUNT(*) FROM invite_joins WHERE joined_at>? AND joined_at<=?");$d['leaves']=$c("SELECT COUNT(*) FROM invite_joins WHERE left_at>? AND left_at<=?");
 $span=max(3600,strtotime($to.' UTC')-strtotime($from.' UTC'));$unit=$span>172800?86400:3600;$n=max(1,min(31,(int)ceil($span/$unit)));$bk=array_fill(0,$n,0);$t0=strtotime($from.' UTC');
 foreach(q("SELECT created_at FROM logs WHERE action!='login' AND $W",$P)->fetchAll(PDO::FETCH_COLUMN) as $t){$i=(int)floor((strtotime($t.' UTC')-$t0)/$unit);if($i>=0)$bk[min($n-1,$i)]++;}
 $d['chart']=['values'=>$bk,'labels'=>array_map(fn($i)=>rep_loc(gmdate('Y-m-d H:i:s',$t0+$i*$unit),$unit==3600?'H':'d/m'),array_keys($bk)),'unit'=>$unit];
 $d['notes']=report_notes($d);return $d;}
function report_notes($d){$n=[];$ok=fn($t)=>$n[]=['ok',$t];$w=function($t)use(&$n){$n[]=['warn',$t];};$i=function($t)use(&$n){$n[]=['info',$t];};
 if(!$d['actions'])$w('No action was logged on the website during this period.');else{
  $ch=$d['chart'];$m=max($ch['values']);if($m>0){$k=array_search($m,$ch['values']);$i('Busiest '.($ch['unit']==3600?'hour: '.$ch['labels'][$k].':00':'day: '.$ch['labels'][$k]).' with '.$m.' actions.');}
  if($d['by_user'])$i('Most active member: '.$d['by_user'][0]['n'].' ('.$d['by_user'][0]['c'].' actions).');}
 $d['active']?$i($d['active'].' different member(s) were active on the website ('.$d['logins'].' login(s)).'):$w('Nobody logged in to the website.');
 if($d['orders'])$ok($d['orders'].' shop order(s), total $'.number_format($d['revenue'],2).($d['top_products']?'. Best seller: '.$d['top_products'][0]['n'].'.':'')); else $i('No shop order in this period.');
 if($d['apps_old'])$w($d['apps_old'].' application(s) have been waiting for more than the whole period without an answer.');elseif($d['apps_pending'])$i($d['apps_pending'].' application(s) are pending.');
 if($d['t_old'])$w($d['t_old'].' ticket(s) are still open since before this period.');
 if($d['cheaters'])$w($d['cheaters'].' new cheater(s) were added to the list.');
 if($d['warns'])$w($d['warns'].' warn(s) were given to '.$d['warn_players'].' player(s).');
 $rep=q("SELECT player_name,COUNT(*) c FROM warns GROUP BY player_id HAVING c>=3 ORDER BY c DESC LIMIT 3")->fetchAll();foreach($rep as $r)$w($r['player_name'].' has '.$r['c'].' warns in total.');
 if(!$d['msgs']&&!$d['voice'])$w('The bot recorded no Discord activity: check that it was online during this period.');else $i('Discord: '.number_format($d['msgs']).' messages and '.$d['voice'].' voice hours (UTC days '.$d['days'][0].' to '.$d['days'][1].'), '.$d['joins'].' join(s), '.$d['leaves'].' leave(s).');
 $bf=json_decode((string)q("SELECT v FROM bot_state WHERE k='bf_status'")->fetchColumn(),true);if($bf&&($bf['state']??'')==='running')$i('The server history import is still running, Discord numbers are not final.');
 $nr=(int)q("SELECT COUNT(*) FROM discord_logs WHERE enabled=1 AND (channel_id IS NULL OR channel_id='')")->fetchColumn();if($nr)$w($nr.' Discord log event(s) are enabled without a room.');
 if($d['sug'])$i($d['sug'].' new suggestion(s) and '.$d['votes'].' vote(s).');if($d['partners'])$ok($d['partners'].' new partner(s).');
 return $n;}

class MiniPDF{
 const W=595,H=842,M=40;public $pages=[];public $cur='';public $y=0;private $title;
 private static $w=[278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
 function __construct($t){$this->title=$t;$this->page();}
 function enc($s){$s=(string)$s;$c=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s);return $c===false?preg_replace('/[^\x20-\x7e]/','?',$s):$c;}
 function esc($s){return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$this->enc($s));}
 function tw($s,$sz,$b=false){$t=0;foreach(str_split($this->enc($s)) as $ch){$o=ord($ch);$t+=($o>=32&&$o<=126)?self::$w[$o-32]:556;}return $t*$sz/1000*($b?1.06:1);}
 function page(){if($this->cur!=='')$this->pages[]=$this->cur;$this->cur='';$this->rect(0,self::H-36,self::W,36,[0.02,0.12,0.06]);$this->text(self::M,self::H-23,$this->title,11,true,[0,1,0.4]);$this->y=self::H-62;}
 function col($c){return sprintf('%.3F %.3F %.3F',$c[0],$c[1],$c[2]);}
 function text($x,$y,$s,$sz=10,$b=false,$c=[0.1,0.1,0.1]){$this->cur.='BT /F'.($b?2:1).' '.$sz.' Tf '.$this->col($c).' rg '.sprintf('%.2F %.2F',$x,$y).' Td ('.$this->esc($s).") Tj ET\n";}
 function rect($x,$y,$w,$h,$c){$this->cur.=$this->col($c).' rg '.sprintf('%.2F %.2F %.2F %.2F',$x,$y,$w,$h)." re f\n";}
 function ensure($h){if($this->y-$h<50)$this->page();}
 function wrap($s,$w,$sz,$b=false){$out=[];foreach(preg_split('/\R/',(string)$s) as $para){$line='';foreach(preg_split('/\s+/',trim($para)) as $word){if($word==='')continue;
   while($this->tw($word,$sz,$b)>$w&&mb_strlen($word)>1){$k=mb_strlen($word);while($k>1&&$this->tw(mb_substr($word,0,$k),$sz,$b)>$w)$k--;if($line!==''){$out[]=$line;$line='';}$out[]=mb_substr($word,0,$k);$word=mb_substr($word,$k);}
   $t=$line===''?$word:$line.' '.$word;if($this->tw($t,$sz,$b)<=$w)$line=$t;else{$out[]=$line;$line=$word;}}$out[]=$line;}return $out?:[''];}
 function h1($s){$this->ensure(34);$this->text(self::M,$this->y,$s,18,true,[0.02,0.3,0.15]);$this->y-=26;}
 function h2($s){$this->ensure(72);$this->y-=8;$this->rect(self::M,$this->y-4,3,16,[0,0.8,0.35]);$this->text(self::M+10,$this->y,$s,12.5,true,[0.05,0.2,0.1]);$this->y-=22;}
 function para($s,$sz=10,$c=[0.2,0.2,0.2],$ind=0){foreach($this->wrap($s,self::W-2*self::M-$ind,$sz) as $l){$this->ensure($sz+4);$this->text(self::M+$ind,$this->y,$l,$sz,false,$c);$this->y-=$sz+3.5;}}
 function cards($items,$per=4){$gap=8;$w=(self::W-2*self::M-$gap*($per-1))/$per;foreach(array_chunk($items,$per) as $row){$this->ensure(52);$x=self::M;foreach($row as $it){$this->rect($x,$this->y-38,$w,44,[0.93,0.97,0.94]);$this->rect($x,$this->y-38,3,44,[0,0.8,0.35]);$this->text($x+10,$this->y-14,(string)$it[1],15,true,[0.02,0.25,0.12]);$this->text($x+10,$this->y-30,strtoupper($it[0]),7.5,false,[0.4,0.4,0.4]);$x+=$w+$gap;}$this->y-=54;}}
 function bars($vals,$labels,$h=70){$this->ensure($h+34);$n=count($vals);$gw=self::W-2*self::M;$bw=$gw/$n;$mx=max(1,max($vals));$base=$this->y-$h;$this->rect(self::M,$base,$gw,0.6,[0.6,0.6,0.6]);
  foreach($vals as $i=>$v){$bh=$v?max(1.5,$v/$mx*($h-12)):0;$this->rect(self::M+$i*$bw+1.2,$base,max(1,$bw-2.4),$bh,[0,0.72,0.32]);if($v)$this->text(self::M+$i*$bw+1.5,$base+$bh+2,(string)$v,6.5,false,[0.3,0.3,0.3]);
   if($n<=24||$i%2==0)$this->text(self::M+$i*$bw+1.5,$base-9,(string)$labels[$i],6.5,false,[0.4,0.4,0.4]);}$this->y=$base-24;}
 function table($cols,$rows,$max=2){$tw=array_sum(array_column($cols,1));$x0=self::M;$this->ensure(30);$x=$x0;$this->rect($x0,$this->y-4,$tw,16,[0.02,0.2,0.1]);
  foreach($cols as $c){$this->text($x+4,$this->y,$c[0],8.5,true,[1,1,1]);$x+=$c[1];}$this->y-=18;$alt=false;
  foreach($rows as $r){$cells=[];$lines=1;foreach($cols as $i=>$c){$l=$this->wrap($r[$i]??'',$c[1]-8,8.5);if(count($l)>$max){$l=array_slice($l,0,$max);$l[$max-1]=rtrim($l[$max-1]).'...';}$cells[]=$l;$lines=max($lines,count($l));}
   $rh=$lines*10.5+5;if($this->y-$rh<50){$this->page();$x=$x0;$this->rect($x0,$this->y-4,$tw,16,[0.02,0.2,0.1]);foreach($cols as $c){$this->text($x+4,$this->y,$c[0],8.5,true,[1,1,1]);$x+=$c[1];}$this->y-=18;}
   if($alt)$this->rect($x0,$this->y-$rh+8,$tw,$rh,[0.95,0.97,0.96]);$x=$x0;foreach($cells as $i=>$l){foreach($l as $k=>$t)$this->text($x+4,$this->y-$k*10.5,$t,8.5,false,[0.15,0.15,0.15]);$x+=$cols[$i][1];}$this->y-=$rh;$alt=!$alt;}$this->y-=6;}
 function output(){$this->pages[]=$this->cur;$this->cur='';$n=count($this->pages);foreach($this->pages as $i=>&$p)$p.='BT /F1 8 Tf 0.5 0.5 0.5 rg '.sprintf('%.2F',self::W/2-20).' 24 Td (Page '.($i+1).' / '.$n.") Tj ET\n";unset($p);
  $o=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
  foreach($this->pages as $i=>$c){$o[5+2*$i]="<< /Length ".strlen($c)." >>\nstream\n".$c."endstream";$o[6+2*$i]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.(5+2*$i).' 0 R >>';$kids[]=(6+2*$i).' 0 R';}
  $o[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.$n.' >>';ksort($o);$out="%PDF-1.4\n";$off=[];foreach($o as $k=>$v){$off[$k]=strlen($out);$out.="$k 0 obj\n$v\nendobj\n";}
  $x=strlen($out);$out.="xref\n0 ".(count($o)+1)."\n0000000000 65535 f \n";foreach($o as $k=>$v)$out.=sprintf("%010d 00000 n \n",$off[$k]);return $out."trailer\n<< /Size ".(count($o)+1)." /Root 1 0 R >>\nstartxref\n$x\n%%EOF";}
}
function report_pdf($d,$label){$p=new MiniPDF(SERVER_NAME.' - Website report');
 $p->h1('Website report');$p->para($label.': '.rep_loc($d['from'],'d/m/Y H:i').' to '.rep_loc($d['to'],'d/m/Y H:i').' ('.REP_TZ.')  -  generated '.rep_loc(gmdate('Y-m-d H:i:s'),'d/m/Y H:i'),9.5,[0.4,0.4,0.4]);
 $p->h2('Summary');$p->cards([['Actions',$d['actions']],['Active members',$d['active']],['Logins',$d['logins']],['Site members',$d['users_total']],['Shop orders',$d['orders']],['Revenue','$'.number_format($d['revenue'],2)],['Applications',$d['apps_new']],['Tickets opened',$d['t_open']],['New cheaters',$d['cheaters']],['Warns given',$d['warns']],['Tag reports',$d['tags']],['Suggestions',$d['sug']],['Discord messages',number_format($d['msgs'])],['Voice hours',$d['voice']],['Joins',$d['joins']],['Leaves',$d['leaves']]]);
 $p->h2('Activity '.($d['chart']['unit']==3600?'per hour':'per day'));$p->bars($d['chart']['values'],$d['chart']['labels']);
 $p->h2('Notes');foreach($d['notes'] as $nt)$p->para(['ok'=>'[+] ','warn'=>'[!] ','info'=>'[i] '][$nt[0]].$nt[1],10,$nt[0]==='warn'?[0.7,0.25,0.1]:[0.2,0.2,0.2]);
 $p->h2('What happened, by type');$p->table([['Action',230],['Count',60]],array_map(fn($r)=>[$r['action'],$r['c']],$d['by_action']),1);
 $p->h2('Most active members');$p->table([['Member',230],['Discord ID',150],['Actions',60]],array_map(fn($r)=>[$r['n'],$r['i'],$r['c']],$d['by_user']),1);
 $p->h2('Applications and tickets');$p->para('Applications: '.$d['apps_new'].' new, '.$d['apps_ok'].' accepted, '.$d['apps_no'].' rejected, '.$d['apps_pending'].' pending now.   Tickets: '.$d['t_open'].' opened, '.$d['t_closed'].' closed, '.$d['t_now'].' open now.');
 $p->h2('Full activity log ('.count($d['logs']).($d['actions']>count($d['logs'])+$d['logins']?' latest':'').' entries)');
 $p->table([['Time',62],['Member',92],['Action',92],['Details',269]],array_map(fn($r)=>[rep_loc($r['created_at']),$r['actor_name'],$r['action'],$r['details']],$d['logs']),2);return $p->output();}
function discord_file($channelId,$payload,$path,$name){$c=curl_init('https://discord.com/api/v10/channels/'.$channelId.'/messages');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_SSL_VERIFYPEER=>SSL_VERIFY,CURLOPT_USERAGENT=>'DiscordBot (nova, 1.0)',CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>['Authorization: Bot '.BOT_TOKEN],CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>['payload_json'=>json_encode($payload),'files[0]'=>new CURLFile($path,'application/pdf',$name)]]);return json_decode((string)curl_exec($c),true);}
function report_save($d,$label){$dir=__DIR__.'/../data/reports';@mkdir($dir,0777,true);$name='nova-report-'.rep_loc($d['to'],'Y-m-d_Hi').'.pdf';$path=$dir.'/'.$name;file_put_contents($path,report_pdf($d,$label));return [$path,$name];}
function send_report($from,$to,$label){$d=report_data($from,$to);[$path,$name]=report_save($d,$label);$res=[];
 foreach(OWNER_IDS as $id){$dm=discord('POST','/users/@me/channels',['recipient_id'=>$id]);if(empty($dm['id'])){$res[$id]='no DM channel';continue;}
  $r=discord_file($dm['id'],['content'=>'📊 **'.SERVER_NAME.' website report** ('.$label.')','embeds'=>[['color'=>0x00ff66,'description'=>'**'.$d['actions'].'** actions · **'.$d['active'].'** active members · **'.$d['orders'].'** orders ($'.number_format($d['revenue'],2).') · **'.$d['cheaters'].'** cheaters · **'.$d['warns'].'** warns'."\n".'The full PDF is attached.','footer'=>['text'=>SERVER_NAME]]]],$path,$name);$res[$id]=empty($r['id'])?'failed: '.json_encode($r):'sent';}
 return [$res,$name];}
