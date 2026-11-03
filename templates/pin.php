<?php
/** @var callable $t */
/** @var callable $e */
/** @var string $return */
/** @var ?string $error */
?>
<section class="card pin">
  <h2><?= $e($t('pin.title')) ?></h2>
  <p><?= $e($t('pin.prompt')) ?></p>
  <?php if ($error): ?><p class="bad"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/pin" class="row">
    <input type="hidden" name="return" value="<?= $e($return) ?>">
    <label><?= $e($t('pin.field')) ?> <input type="password" name="pin" autofocus autocomplete="current-password" required></label>
    <button type="submit" class="primary"><?= $e($t('pin.submit')) ?></button>
  </form>
</section>
