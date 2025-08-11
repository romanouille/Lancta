<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare('SELECT * FROM users WHERE username_canonical = ?');
  $stmt->execute([canon($username)]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password_hash'])) {
    $st = user_is_banned_or_kicked($pdo, (int)$user['id']);
    if ($st['banned']) {
      $errors[] = 'Accès interdit (kick/ban actif)';
    } else {
      $_SESSION['user'] = ['id'=>$user['id'],'username'=>$user['username'],'role'=>$user['role'],'avatar'=>$user['avatar']];
      redirect('index.php');
    }
  } else {
    $errors[] = 'Identifiants invalides.';
  }
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Connexion</h1>
<?php if (isset($_GET['registered'])): ?><div class="mb-3 p-3 bg-emerald-900/40 text-emerald-200 rounded">Compte créé, vous pouvez vous connecter.</div><?php endif; ?>
<?php if ($errors): ?><div class="mb-3 p-3 bg-red-900/40 text-red-200 rounded"><?php foreach ($errors as $e) echo '<div>'.e($e).'</div>'; ?></div><?php endif; ?>
<form method="post" class="bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3 max-w-md">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
  <label class="grid gap-1"><span>Nom d’utilisateur</span><input name="username" class="border border-slate-600 rounded p-2 bg-slate-900" required/></label>
  <label class="grid gap-1"><span>Mot de passe</span><input type="password" name="password" class="border border-slate-600 rounded p-2 bg-slate-900" required/></label>
  <button class="px-4 py-2 bg-slate-700 text-white rounded">Se connecter</button>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>
