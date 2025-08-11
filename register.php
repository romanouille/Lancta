<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $captcha  = $_POST['captcha'] ?? '';

  if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) $errors[] = 'Nom d’utilisateur invalide.';
  if (strlen($password) < 6) $errors[] = 'Mot de passe trop court.';
  if ($config['captcha_enabled'] && !captcha_check($captcha)) $errors[] = 'CAPTCHA invalide.';

  if (!$errors) {
    try {
      $stmt = $pdo->prepare('INSERT INTO users (username, username_canonical, password_hash, role, created_at) VALUES (?, ?, ?, "user", ?)');
      $stmt->execute([$username, canon($username), password_hash($password, PASSWORD_DEFAULT), date('c')]);
      redirect('login.php?registered=1');
    } catch (Exception $e) {
      $errors[] = 'Nom d’utilisateur déjà pris.';
    }
  }
  captcha_generate();
} else {
  captcha_generate();
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Inscription</h1>
<?php if ($errors): ?><div class="mb-3 p-3 bg-red-900/40 text-red-200 rounded"><?php foreach ($errors as $e) echo '<div>'.e($e).'</div>'; ?></div><?php endif; ?>
<form method="post" class="bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3 max-w-md">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
  <label class="grid gap-1">
    <span>Nom d’utilisateur</span>
    <input name="username" class="border border-slate-600 rounded p-2 bg-slate-900" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+"/>
  </label>
  <label class="grid gap-1">
    <span>Mot de passe</span>
    <input type="password" name="password" class="border border-slate-600 rounded p-2 bg-slate-900" required minlength="6"/>
  </label>
  <label class="grid gap-1">
    <span>CAPTCHA : <code><?= e($_SESSION['captcha_text'] ?? '') ?></code></span>
    <div class="flex items-center gap-2">
      <img src="captcha.php" alt="captcha" class="border border-slate-600 rounded"/>
      <input name="captcha" class="border border-slate-600 rounded p-2 bg-slate-900" required/>
    </div>
  </label>
  <button class="px-4 py-2 bg-blue-600 text-white rounded">Créer le compte</button>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>
