<?php require 'inc/bootstrap.php';header('Content-Type: application/json; charset=utf-8');if(!user())exit('[]');
$t=[];foreach(['music',''] as $d){$dir=__DIR__.($d?"/$d":'');if(!is_dir($dir))continue;foreach(scandir($dir) as $n){if(!preg_match('/\.(mp3|ogg|wav|m4a|aac|flac)$/i',$n))continue;$t[]=['url'=>'/'.($d?"$d/":'').rawurlencode($n),'title'=>trim(preg_replace('/[_\s]+/',' ',pathinfo($n,PATHINFO_FILENAME)))];}}
usort($t,fn($a,$b)=>strnatcasecmp($a['title'],$b['title']));echo json_encode($t,JSON_UNESCAPED_UNICODE);
