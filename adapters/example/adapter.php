<?php

declare(strict_types=1);

/**
 * Example host adapter: prints a fixed sample ticket.
 *
 * URL:  /printer/sample.php?n=1          (n = sample number shown on the ticket)
 *       /printer/sample.php?n=0          returns an error, to see the failure page
 *
 * Copy this directory to adapters/<office>/ and replace the handler bodies
 * with the queries and layout of the real host system. See docs/adapters.md.
 */

use Alice\Adapters\AdapterInterface;
use Alice\Adapters\Context;
use Alice\Adapters\Document;
use Alice\Escp\Builder;
use Alice\Escp\SpanishNumbers;

return new class (require __DIR__ . '/config.php') implements AdapterInterface {
    public function __construct(private array $config)
    {
    }

    public function name(): string
    {
        return 'example';
    }

    public function routes(): array
    {
        return [
            'printer/sample.php' => [$this, 'sample'],
        ];
    }

    public function check(): ?string
    {
        // A real adapter opens its database here, e.g.:
        //   new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 5]);
        // and returns $e->getMessage() on failure.
        return null;
    }

    /** @return Document|string */
    public function sample(array $params, Context $ctx): Document|string
    {
        $n = (int) ($params['n'] ?? 1);
        if ($n <= 0) {
            return "sample $n not found";
        }
        $cfg = $this->config;
        $b = new Builder((int) $cfg['width'], (string) $cfg['encoding']);
        $b->init()->charset((int) $cfg['charset']);
        if (!empty($cfg['page_length'])) {
            $b->pageLength((int) $cfg['page_length']);
        }
        $b->doubleWidth(true)->center($cfg['header'][0] ?? 'SAMPLE')->doubleWidth(false);
        foreach (array_slice($cfg['header'], 1) as $line) {
            $b->center($line);
        }
        $b->rule('=');
        $b->columns(['Ticket', sprintf('%06d', $n)], [20, 0], ['left', 'right']);
        $b->columns(['Date', date('Y-m-d H:i')], [20, 0], ['left', 'right']);
        $b->columns(['Station', $ctx->stationIp], [20, 0], ['left', 'right']);
        $b->rule();
        $items = [
            ['Café americano', 2, 35.00],
            ['Pan dulce', 3, 12.50],
            ['Jugo de naranja', 1, 45.00],
        ];
        $total = 0.0;
        $b->bold(true)->columns(['Qty', 'Item', 'Amount'], [4, 0, 10], ['left', 'left', 'right'])->bold(false);
        foreach ($items as [$name, $qty, $price]) {
            $amount = $qty * $price;
            $total += $amount;
            $b->columns([$qty, $name, Builder::money($amount)], [4, 0, 10], ['left', 'left', 'right']);
        }
        $b->rule();
        $b->bold(true)->columns(['TOTAL', Builder::money($total)], [0, 12], ['left', 'right'])->bold(false);
        $b->line('(' . SpanishNumbers::amount($total) . ')');
        $b->feed();
        $b->center((string) ($cfg['footer'] ?? ''));
        $b->feed(2)->formFeed();

        return Document::raw($b->build(), "sample $n");
    }
};
