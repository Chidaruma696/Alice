<?php
/** @var \Alice\App $app */
/** @var callable $t */
/** @var callable $e */
/** @var string $path */
/** @var array<int,array{name:string,dir:string,loaded:bool,error:?string,routes:string[]}> $adapters */
?>
<section class="card result failed">
  <h2><?= $e($t('notfound.title')) ?></h2>
  <p class="bad"><code><?= $e($path) ?></code></p>
  <p><?= $e($t('notfound.explain')) ?></p>
  <?php $any = false; foreach ($adapters as $a): if (!$a['loaded'] || $a['routes'] === []) continue; $any = true; ?>
    <p><strong><?= $e($a['name']) ?></strong> (<code>adapters/<?= $e($a['dir']) ?></code>)</p>
    <ul>
      <?php foreach ($a['routes'] as $route): ?>
        <li><a href="<?= $e($route) ?>"><code><?= $e($route) ?></code></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
  <?php if (!$any): ?><p><?= $e($t('notfound.none')) ?></p><?php endif; ?>
  <?php foreach ($adapters as $a): if ($a['loaded']) continue; ?>
    <p class="bad"><strong><?= $e($a['dir']) ?></strong>: <?= $e($a['error'] ?? '') ?></p>
  <?php endforeach; ?>
  <p><small><?= $e($t('notfound.howto')) ?></small></p>
  <p class="buttons">
    <a class="button" href="/"><?= $e($t('result.panel')) ?></a>
  </p>
</section>
