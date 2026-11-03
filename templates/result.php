<?php
/** @var \Alice\App $app */
/** @var callable $t */
/** @var callable $e */
/** @var bool $ok */
/** @var ?string $message */
/** @var ?array $printer */
/** @var ?array $job */
/** @var string $retry_url */
/** @var string $change_url */
/** @var bool $dry_run */
?>
<section class="card result <?= $ok ? 'ok' : 'failed' ?>">
  <?php if ($ok): ?>
    <h2><?= $e($t($dry_run ? 'result.sent_dry' : 'result.sent', $printer['name'] ?? '')) ?></h2>
    <?php if ($job): ?><p><small><?= $e($t('result.job')) ?>: <code><?= $e($job['id']) ?></code> · <?= $e($job['bytes']) ?> bytes</small></p><?php endif; ?>
  <?php else: ?>
    <h2><?= $e($t('result.failed')) ?></h2>
    <p class="bad"><?= $e($message) ?></p>
    <?php if ($printer): ?><p><small><?= $e($t('result.printer')) ?>: <?= $e($printer['name']) ?> (<code><?= $e($printer['key']) ?></code>)</small></p><?php endif; ?>
  <?php endif; ?>
  <p class="buttons">
    <a class="button primary" href="<?= $e($retry_url) ?>"><?= $e($t('result.again')) ?></a>
    <a class="button" href="<?= $e($change_url) ?>"><?= $e($t('result.change')) ?></a>
    <a class="button" href="/"><?= $e($t('result.panel')) ?></a>
  </p>
</section>
