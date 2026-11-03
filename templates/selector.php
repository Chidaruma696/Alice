<?php
/** @var \Alice\App $app */
/** @var callable $t */
/** @var callable $e */
/** @var array<string,array> $printers */
/** @var string $return */
/** @var ?array $station */
?>
<section class="card selector">
  <h2><?= $e($t('selector.title')) ?></h2>
  <?php if ($printers === []): ?>
    <p class="notice"><?= $e($t('selector.none')) ?></p>
    <p><a class="button" href="/"><?= $e($t('selector.open_panel')) ?></a></p>
  <?php else: ?>
    <p><?= $e($t('selector.prompt', $station['ip'] ?? '')) ?></p>
    <div class="choices">
      <?php foreach ($printers as $p): ?>
        <form method="post" action="/select">
          <input type="hidden" name="printer" value="<?= $e($p['key']) ?>">
          <input type="hidden" name="return" value="<?= $e($return) ?>">
          <button type="submit" class="choice"><?= $e($p['name']) ?><br><small><?= $e($p['destination']) ?></small></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
