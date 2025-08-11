<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');

$forums = $pdo->query('SELECT id, name FROM forums ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$selected_forum_id = (int)($_GET['forum_id'] ?? ($forums[0]['id'] ?? 0));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $forum_id = (int)$_POST['forum_id'];
  $captcha = $_POST['captcha'] ?? '';
  $poll_question = trim($_POST['poll_question'] ?? '');
  $poll_options = array_filter(array_map('trim', $_POST['poll_options'] ?? []));

  $st = user_is_banned_or_kicked($pdo, (int)$_SESSION['user']['id']);
  if ($st['banned']) $errors[]='Action interdite (kick/ban actif)';
  if ($title === '' || $content === '') $errors[] = 'Veuillez remplir tous les champs.';
  if ($forum_id === 0) $errors[] = 'Veuillez choisir un forum.';
  if ($config['captcha_enabled'] && !captcha_check($captcha)) $errors[] = 'CAPTCHA invalide.';
  if ($poll_question !== '' && count($poll_options) < 2) $errors[] = 'Une question de sondage nécessite au moins 2 options.';

  if (!$errors) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null; $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
    $stmt = $pdo->prepare('INSERT INTO topics (forum_id, user_id, title, content, created_at, source_ip, source_port) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$forum_id, $_SESSION['user']['id'], $title, $content, date('c'), $ip, $port]);
    $topic_id = (int)$pdo->lastInsertId();

    $mentions = extract_mentions($pdo, $content);
    foreach ($mentions as $m) { if ($m['id'] != $_SESSION['user']['id']) add_notification($pdo, (int)$m['id'], 'mention', ['topic_id'=>$topic_id,'by'=>$_SESSION['user']['username'],'title'=>$title]); }

    if ($poll_question !== '') {
      $pdo->prepare('INSERT INTO polls (topic_id, question) VALUES (?, ?)')->execute([$topic_id, $poll_question]);
      $ins = $pdo->prepare('INSERT INTO poll_options (topic_id, text) VALUES (?, ?)');
      foreach ($poll_options as $opt) $ins->execute([$topic_id, $opt]);
    }

    redirect('view_topic.php?id=' . $topic_id);
  } else {
    captcha_generate(); // regenerate for redisplay on failed POST
  }
} else {
  captcha_generate(); // only on GET
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Nouveau topic</h1>
<?php if ($errors): ?><div class="mb-3 p-3 bg-red-900/40 text-red-200 rounded"><?php foreach ($errors as $e) echo '<div>'.e($e).'</div>'; ?></div><?php endif; ?>
<form method="post" class="bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
  <label class="grid gap-1"><span>Forum</span><select name="forum_id" class="border border-slate-600 rounded p-2 bg-slate-900" required><?php foreach ($forums as $f): ?><option value="<?= $f['id'] ?>" <?= $selected_forum_id==$f['id']?'selected':'' ?>><?= e($f['name']) ?></option><?php endforeach; ?></select></label>
  <label class="grid gap-1"><span>Titre</span><input name="title" class="border border-slate-600 rounded p-2 bg-slate-900" required/></label>
  <label class="grid gap-1"><span>Contenu (Markdown + @mentions)</span><textarea name="content" id="content" class="border border-slate-600 rounded p-2 min-h-[180px] bg-slate-900 js-mention-target" required></textarea></label>

  <details class="bg-slate-900 border border-slate-700 rounded p-3">
    <summary class="cursor-pointer">Ajouter un sondage</summary>
    <div class="grid gap-2 mt-2">
      <input name="poll_question" placeholder="Question du sondage" class="border border-slate-600 rounded p-2 bg-slate-900"/>
      <?php for($i=0;$i<5;$i++): ?><input name="poll_options[]" placeholder="Option <?= $i+1 ?>" class="border border-slate-600 rounded p-2 bg-slate-900"/><?php endfor; ?>
    </div>
  </details>
  
<script src="https://risibank.fr/downloads/web-api/risibank.js"></script>
<script>
function openRisiBank() {

  RisiBank.activate({

    // Use default options for Overlay + Dark
    // Other defaults are all combinations of Overlay/Modal/Frame and Light/Dark/LightClassic/DarkClassic, e.g. RisiBank.Defaults.Frame.LightClassic
    ...RisiBank.Defaults.Overlay.Dark,

    // Add selected image (risibank) to specified text area
    onSelectMedia: RisiBank.Actions.addRisiBankImageLink('#content'),
  });
}
</script>
<div class="container">

    <!-- Button to trigger the overlay mode -->
    <a class="risibank-image"
       title="Ajouter un média RisiBank"
       onclick="openRisiBank(); return false;"
       href="javascript:void(0);"
    >
        <img src="https://risibank.fr/banner.png">
    </a>
</div>

  <label class="grid gap-1"><span>CAPTCHA : <code><?= e($_SESSION['captcha_text'] ?? '') ?></code></span>
    <div class="flex items-center gap-2"><img src="captcha.php" class="border border-slate-600 rounded"/><input name="captcha" class="border border-slate-600 rounded p-2 bg-slate-900" required/></div>
  </label>

  <button class="px-4 py-2 bg-blue-600 text-white rounded">Publier</button>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>
