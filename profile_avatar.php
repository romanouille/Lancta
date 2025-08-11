<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');
check_csrf();

$uid = (int)$_SESSION['user']['id'];
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) redirect('profile.php?u=' . urlencode($_SESSION['user']['username']));

$tmp = $_FILES['avatar']['tmp_name'];
$info = getimagesize($tmp);
if (!$info) redirect('profile.php?u=' . urlencode($_SESSION['user']['username']));

$mime = $info['mime'];
if (!in_array($mime, ['image/png','image/jpeg'])) redirect('profile.php?u=' . urlencode($_SESSION['user']['username']));

$size = $config['avatar_size'];
$src = ($mime==='image/png') ? imagecreatefrompng($tmp) : imagecreatefromjpeg($tmp);
$w = imagesx($src); $h = imagesy($src);
$dst = imagecreatetruecolor($size, $size);
imagecopyresampled($dst, $src, 0,0, 0,0, $size,$size, $w,$h);

$path = $config['avatars_dir'] . '/user_' . $uid . '.png';
imagepng($dst, $path);
imagedestroy($src); imagedestroy($dst);

$url = 'uploads/avatars/' . 'user_' . $uid . '.png';
$stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$stmt->execute([$url, $uid]);
$_SESSION['user']['avatar'] = $url;

redirect('profile.php?u=' . urlencode($_SESSION['user']['username']));
