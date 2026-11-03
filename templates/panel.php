<?php
/** @var \Alice\App $app */
/** @var callable $t */
/** @var callable $e */
/** @var array $status */
/** @var array $installed */
/** @var array<string,array> $printers */
/** @var array<string,array> $stations */
/** @var ?string $default_printer */
/** @var array $jobs */
/** @var bool $authorised */
/** @var string $client_ip */
$fmtTime = static fn (?string $iso): string => $iso ? date('Y-m-d H:i:s', strtotime($iso) ?: 0) : '';
$printerName = static fn (?string $key) => $key === null ? '' : ($printers[$key]['name'] ?? $key);
$pinSet = $app->auth->pinConfigured();
?>
<div class="js-strings" hidden
     data-working="<?= $e($t('ui.working')) ?>"
     data-saved="<?= $e($t('ui.saved')) ?>"
     data-error="<?= $e($t('ui.error', '%s')) ?>"
     data-sent="<?= $e($t('ui.sent', '%s')) ?>"
     data-added="<?= $e($t('printers.added', '%s')) ?>"
     data-confirm-remove-printer="<?= $e($t('printers.confirm_remove', '%s')) ?>"
     data-confirm-remove-station="<?= $e($t('stations.confirm_remove', '%s')) ?>"></div>

<?php if ($pinSet && !$authorised): ?>
<div class="notice locked">
  <?= $e($t('pin.locked')) ?> <a class="button small" href="/pin?return=%2F"><?= $e($t('pin.unlock')) ?></a>
</div>
<?php endif; ?>

<section id="status" class="card">
  <h2><?= $e($t('status.title')) ?></h2>
  <dl class="grid">
    <dt><?= $e($t('status.version')) ?></dt><dd><?= $e($status['version']) ?> · PHP <?= $e($status['php']) ?></dd>
    <dt><?= $e($t('status.host')) ?></dt><dd><code><?= $e($status['host']) ?></code></dd>
    <dt><?= $e($t('status.ips')) ?></dt><dd><?= $status['ips'] ? implode(' · ', array_map(fn ($ip) => '<code>' . $e($ip) . '</code>', $status['ips'])) : '—' ?></dd>
    <dt><?= $e($t('status.port')) ?></dt><dd><code><?= $e($status['port']) ?></code></dd>
    <dt><?= $e($t('status.mode')) ?></dt>
    <dd>
      <?php if ($status['dry_run']): ?><span class="tag warn"><?= $e($t('status.dry_run')) ?></span><?php else: ?><span class="tag ok"><?= $e($t('status.live')) ?></span><?php endif; ?>
      <?php if ($status['windows']): ?>
        <button class="small" data-action="set-dry-run" data-value="<?= $status['dry_run'] ? 'false' : 'true' ?>"><?= $e($t('status.toggle_dry_run', $status['dry_run'] ? $t('status.live') : $t('status.dry_run'))) ?></button>
      <?php endif; ?>
    </dd>
    <dt><?= $e($t('status.spool')) ?></dt><dd><code><?= $e($status['data_dir'] . DIRECTORY_SEPARATOR . 'spool') ?></code> — <?= $status['spool_writable'] ? $e($t('status.writable')) : '<strong class="bad">' . $e($t('status.not_writable')) . '</strong>' ?></dd>
  </dl>
  <p class="hint"><?= $e($t('status.station_hint', $status['host'] . ($status['port'] != 80 ? ':' . $status['port'] : ''), ($status['ips'][0] ?? '127.0.0.1') . ($status['port'] != 80 ? ':' . $status['port'] : ''))) ?>
  <?= $e($t('status.you', $client_ip)) ?></p>
</section>

<section id="printers" class="card">
  <h2><?= $e($t('printers.registered')) ?></h2>
  <?php if ($printers === []): ?>
    <p class="empty"><?= $e($t('printers.none_registered')) ?></p>
  <?php else: ?>
  <table>
    <thead><tr><th><?= $e($t('printers.name')) ?></th><th><?= $e($t('printers.key')) ?></th><th><?= $e($t('printers.destination')) ?></th><th><?= $e($t('printers.status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($printers as $p): ?>
      <tr data-printer="<?= $e($p['key']) ?>">
        <td><strong><?= $e($p['name']) ?></strong><?php if ($p['key'] === $default_printer): ?> <span class="tag">default</span><?php endif; ?></td>
        <td><code><?= $e($p['key']) ?></code></td>
        <td><span class="tag kind"><?= $e($t('printers.kind.' . $p['kind'])) ?></span> <code><?= $e($p['destination']) ?></code></td>
        <td>
          <?php if ($p['last_error']): ?>
            <span class="tag bad"><?= $e($t('printers.status.error')) ?></span> <small><?= $e($fmtTime($p['last_error_at'])) ?></small><br><small class="bad"><?= $e($p['last_error']) ?></small>
          <?php elseif ($p['last_job_at']): ?>
            <span class="tag ok"><?= $e($t('printers.status.ok')) ?></span> <small><?= $e($t('printers.last_job', $fmtTime($p['last_job_at']))) ?></small>
          <?php else: ?>
            <small><?= $e($t('printers.status.never')) ?></small>
          <?php endif; ?>
        </td>
        <td class="actions">
          <button class="small" data-action="test" data-key="<?= $e($p['key']) ?>"><?= $e($t('printers.test')) ?></button>
          <button class="small" data-action="reprint" data-key="<?= $e($p['key']) ?>"><?= $e($t('printers.reprint')) ?></button>
          <button class="small danger" data-action="remove-printer" data-key="<?= $e($p['key']) ?>" data-name="<?= $e($p['name']) ?>"><?= $e($t('printers.remove')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="row">
    <label><?= $e($t('printers.default')) ?>
      <select data-action="set-default">
        <option value=""><?= $e($t('printers.default_none')) ?></option>
        <?php foreach ($printers as $p): ?>
          <option value="<?= $e($p['key']) ?>"<?= $p['key'] === $default_printer ? ' selected' : '' ?>><?= $e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <span class="hint"><?= $e($t('printers.default_hint')) ?></span>
  </div>

  <h3><?= $e($t('printers.installed')) ?> <a class="button small" href="/api/printers/installed?refresh=1" data-action="refresh-installed"><?= $e($t('printers.refresh')) ?></a></h3>
  <p class="hint"><?= $e($t('printers.installed_hint')) ?></p>
  <?php if (!$installed['ok']): ?>
    <p class="notice"><?= $e($installed['warning']) ?></p>
  <?php elseif ($installed['printers'] === []): ?>
    <p class="empty"><?= $e($t('printers.none_installed')) ?></p>
  <?php else: ?>
  <table>
    <thead><tr><th><?= $e($t('printers.name')) ?></th><th><?= $e($t('printers.port')) ?></th><th><?= $e($t('printers.shared')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($installed['printers'] as $ip): ?>
      <tr>
        <td><?= $e($ip['name']) ?></td>
        <td><code><?= $e($ip['port']) ?></code></td>
        <td><?= $e($ip['shared'] ? $t('printers.yes') : $t('printers.no')) ?></td>
        <td class="actions">
          <?php if ($ip['registered']): ?>
            <small><?= $e($t('printers.added', $ip['registered'])) ?></small>
          <?php else: ?>
            <button class="small primary" data-action="add-installed" data-name="<?= $e($ip['name']) ?>"><?= $e($t('printers.add')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h3><?= $e($t('printers.manual')) ?></h3>
  <p class="hint"><?= $e($t('printers.manual_hint')) ?></p>
  <form class="row" data-form="add-printer">
    <label><?= $e($t('printers.manual_name')) ?> <input name="name" required></label>
    <label><?= $e($t('printers.manual_destination')) ?> <input name="destination" required placeholder="<?= $e($t('printers.manual_destination_placeholder')) ?>"></label>
    <button class="primary" type="submit"><?= $e($t('printers.manual_submit')) ?></button>
  </form>
</section>

<section id="stations" class="card">
  <h2><?= $e($t('stations.title')) ?></h2>
  <p class="hint"><?= $e($t('stations.hint')) ?></p>
  <?php if ($stations === []): ?>
    <p class="empty"><?= $e($t('stations.none')) ?></p>
  <?php else: ?>
  <table>
    <thead><tr><th><?= $e($t('stations.ip')) ?></th><th><?= $e($t('stations.name')) ?></th><th><?= $e($t('stations.printer')) ?></th><th><?= $e($t('stations.last_seen')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($stations as $s): ?>
      <tr data-station="<?= $e($s['ip']) ?>">
        <td><code><?= $e($s['ip']) ?></code><?php if ($s['ip'] === $client_ip): ?> <span class="tag">you</span><?php endif; ?></td>
        <td><input data-field="name" value="<?= $e($s['name']) ?>" placeholder="<?= $e($t('stations.name_placeholder')) ?>"></td>
        <td>
          <select data-field="printer">
            <option value=""><?= $e($t('stations.default_option')) ?></option>
            <?php foreach ($printers as $p): ?>
              <option value="<?= $e($p['key']) ?>"<?= ($s['printer'] ?? null) === $p['key'] ? ' selected' : '' ?>><?= $e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><small><?= $e($fmtTime($s['last_seen'] ?? null)) ?></small></td>
        <td class="actions"><button class="small danger" data-action="remove-station" data-ip="<?= $e($s['ip']) ?>"><?= $e($t('stations.remove')) ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <form class="row" data-form="add-station">
    <label><?= $e($t('stations.add_ip')) ?> <input name="ip" required placeholder="172.18.1.50"></label>
    <label><?= $e($t('stations.name')) ?> <input name="name" placeholder="<?= $e($t('stations.name_placeholder')) ?>"></label>
    <button type="submit"><?= $e($t('stations.add')) ?></button>
  </form>
</section>

<section id="jobs" class="card">
  <h2><?= $e($t('jobs.title')) ?></h2>
  <?php if ($jobs === []): ?>
    <p class="empty"><?= $e($t('jobs.none')) ?></p>
  <?php else: ?>
  <table class="jobs">
    <thead><tr><th><?= $e($t('jobs.time')) ?></th><th><?= $e($t('jobs.station')) ?></th><th><?= $e($t('jobs.printer')) ?></th><th><?= $e($t('jobs.doc')) ?></th><th><?= $e($t('jobs.bytes')) ?></th><th><?= $e($t('jobs.outcome')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $j): ?>
      <tr>
        <td><small><?= $e($fmtTime($j['time'] ?? null)) ?></small></td>
        <td><code><?= $e($j['station'] ?? '') ?></code> <small><?= $e($stations[$j['station'] ?? '']['name'] ?? '') ?></small></td>
        <td><?= $e($printerName($j['printer'] ?? null)) ?></td>
        <td><?= $e($j['title'] ?? '') ?></td>
        <td><?= $e($j['bytes'] ?? 0) ?></td>
        <td>
          <?php if (($j['outcome'] ?? '') === 'sent'): ?>
            <span class="tag ok"><?= $e($t('jobs.sent')) ?></span><?php if (!empty($j['dry_run'])): ?> <span class="tag warn"><?= $e($t('jobs.dry')) ?></span><?php endif; ?>
          <?php else: ?>
            <span class="tag bad"><?= $e($t('jobs.failed')) ?></span> <small class="bad"><?= $e($j['error'] ?? '') ?></small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section id="adapters" class="card">
  <h2><?= $e($t('adapters.title')) ?></h2>
  <p class="hint"><?= $e($t('adapters.hint')) ?></p>
  <?php if ($status['adapters'] === []): ?>
    <p class="empty"><?= $e($t('adapters.none')) ?></p>
  <?php else: ?>
  <ul class="adapters">
    <?php foreach ($status['adapters'] as $a): ?>
      <li>
        <strong><?= $e($a['name']) ?></strong> <code>adapters/<?= $e($a['dir']) ?></code>
        <?php if ($a['ok']): ?><span class="tag ok"><?= $e($t('adapters.connected')) ?></span>
        <?php else: ?><span class="tag bad"><?= $e($t('adapters.not_connected')) ?></span> <small class="bad"><?= $e($a['error']) ?></small><?php endif; ?>
        <?php if ($a['routes']): ?><br><small><?= $e($t('adapters.routes')) ?>: <?= implode(' · ', array_map(fn ($r) => '<a href="' . $e($r) . '"><code>' . $e($r) . '</code></a>', $a['routes'])) ?></small><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section id="pin" class="card">
  <h2><?= $e($t('pin.section')) ?></h2>
  <p class="hint"><?= $e($t($pinSet ? 'pin.hint_set' : 'pin.hint_unset')) ?></p>
  <form class="row" data-form="set-pin">
    <label><?= $e($t('pin.new')) ?> <input name="pin" type="password" minlength="4" autocomplete="new-password" required></label>
    <button type="submit"><?= $e($t('pin.set')) ?></button>
    <?php if ($pinSet): ?><button type="button" class="danger" data-action="clear-pin"><?= $e($t('pin.clear')) ?></button><?php endif; ?>
  </form>
</section>
