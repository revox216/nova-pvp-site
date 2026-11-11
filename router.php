<?php
// Yemna3 el nes mel wossoul lel fichiers 7asssa (database, config, code)
$p=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(preg_match('#^/(data|sql|inc|bot)(/|$)#i',$p)||preg_match('#\.(sqlite|sql|ini|bat|md|json)$#i',$p)||preg_match('#^/config\.php$#i',$p)){http_response_code(403);exit('Forbidden');}
return false;
