<?php require 'inc/bootstrap.php';require 'inc/report.php';
// Called by the bot every hour. Sends the report when 24h passed since the last one.
if(!BOT_TOKEN||!hash_equals(hash('sha256',BOT_TOKEN.'|report'),(string)($_GET['t']??''))){http_response_code(403);exit('forbidden');}
$last=(int)q("SELECT v FROM bot_state WHERE k='report_last'")->fetchColumn();if($last&&time()-$last<86400-300)exit('not due');
q("REPLACE INTO bot_state(k,v) VALUES('report_last',?)",[time()]);[$from,$to,$label]=rep_range('24h');[$res,$name]=send_report($from,$to,$label);
logit('report_sent',$name.' → '.json_encode($res));echo 'sent '.json_encode($res);
