<?php
require __DIR__ . '/functions.php';
header('Content-Type: image/png');
$w=160; $h=50;
$im = imagecreatetruecolor($w,$h);
$bg = imagecolorallocate($im, 20, 24, 31);
imagefilledrectangle($im,0,0,$w,$h,$bg);
for($i=0;$i<12;$i++){
  $c = imagecolorallocate($im, rand(40,120), rand(40,120), rand(40,120));
  imageline($im, rand(0,$w), rand(0,$h), rand(0,$w), rand(0,$h), $c);
}
for($i=0;$i<300;$i++){
  $c = imagecolorallocate($im, rand(100,200), rand(100,200), rand(100,200));
  imagesetpixel($im, rand(0,$w-1), rand(0,$h-1), $c);
}
$text = $_SESSION['captcha_text'] ?? 'ABCDE';
for($i=0; $i<strlen($text); $i++){
  $ch = $text[$i];
  $x = 15 + $i*25 + rand(-2,2);
  $y = 15 + rand(0,10);
  $col = imagecolorallocate($im, rand(180,255), rand(180,255), rand(180,255));
  imagestring($im, rand(3,5), $x, $y, $ch, $col);
}
imagepng($im);
imagedestroy($im);
