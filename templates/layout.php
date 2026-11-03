<?php
/** @var \Alice\App $app */
/** @var callable $t */
/** @var callable $e */
/** @var string $content */
$lang = $app->i18n->lang;
$returnUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e(($title ?? 'Alice') === 'Alice' ? 'Alice' : ($title ?? '') . ' · Alice') ?></title>
<link rel="stylesheet" href="/alice.css">
</head>
<body>
<header class="top">
  <a class="brand" href="/">Alice</a>
  <span class="tagline"><?= $e($t('app.tagline')) ?></span>
  <nav>
    <?php if ($app->auth->pinConfigured()): ?>
      <a href="/logout"><?= $e($t('nav.logout')) ?></a>
    <?php endif; ?>
    <a href="/docs/api.md" title="docs/api.md"><?= $e($t('nav.api')) ?></a>
    <a href="/lang/<?= $e($t('lang.switch_code')) ?>?return=<?= $e(rawurlencode($returnUri)) ?>"><?= $e($t('lang.switch')) ?></a>
  </nav>
</header>
<main>
<?= $content ?>
</main>
<footer>Alice <?= $e(\Alice\App::VERSION) ?> · MIT · <?= $e($app->hostName()) ?></footer>
<script src="/alice.js"></script>
</body>
</html>
