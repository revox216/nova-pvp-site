<?php
function F($act,$fields,$btn='SAVE',$cls=''){return '<form method="post" enctype="multipart/form-data" class="inl '.$cls.'"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="a" value="'.$act.'">'.$fields.'<button class="btn sm">'.$btn.'</button></form>';}
function I($n,$v='',$ph='',$t='text'){return '<input type="'.$t.'" name="'.$n.'" value="'.h($v).'" placeholder="'.h($ph).'">';}
function HID($n,$v){return '<input type="hidden" name="'.$n.'" value="'.h($v).'">';}
function deny($msg){http_response_code(403);include __DIR__.'/header.php';echo '<div class="card center-t"><h3>NO ACCESS</h3><p>'.h($msg).'</p></div>';include __DIR__.'/footer.php';exit;}
function flash_html(){$o='';if(!empty($_SESSION['ok'])){$o.='<div class="card note ok">'.h($_SESSION['ok']).'</div>';unset($_SESSION['ok']);}if(!empty($_SESSION['flash'])){$o.='<div class="card note bad">'.h($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}return $o;}
