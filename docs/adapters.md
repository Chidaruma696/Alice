# Host adapters

An adapter lets a host system that "prints by opening a URL" keep doing exactly that. The sales system calls `http://alice/printer/imprime_nota.php?id_nota=42`; the adapter reads sale 42 from the host database, builds the ESC/P ticket, and Alice prints it on the printer of the station that opened the URL.

The repository ships the contract and `adapters/example/`. Your real adapter lives in `adapters/<office>/`, which git ignores, so credentials, table names, and company text never get committed.

## Contract

`adapters/<name>/adapter.php` must **return** an object implementing `Alice\Adapters\AdapterInterface`:

```php
interface AdapterInterface
{
    public function name(): string;      // shown in the panel
    public function routes(): array;     // 'printer/imprime_nota.php' => callable
    public function check(): ?string;    // null when fine, else an error message
}
```

Each route handler has this signature:

```php
function (array $params, Alice\Adapters\Context $ctx): Alice\Adapters\Document|string
```

- `$params`: query string merged with form/JSON fields (`?id_nota=42` → `['id_nota' => '42']`). The `printer` parameter is consumed by Alice.
- `$ctx`: `$ctx->stationIp`, `$ctx->printer` (the resolved printer record), `$ctx->app`, `$ctx->request`, `$ctx->dryRun()`, `$ctx->t('key')`.
- Return a `Document` to print, or a **string** to fail with that message (nothing is printed, a job is logged as `failed`, the result page shows the text). Exceptions are caught and treated the same way.

```php
Document::raw(string $bytes, string $title = '');                                  // sent untouched
Document::text(string $utf8, string $title = '', string $encoding = 'cp850', bool $formFeed = true);
```

Routes are registered under their declared path for both `GET` and `POST`, so legacy paths with `.php` keep working. Do not put a leading slash.

## Configuration

Keep settings in `adapters/<name>/config.php` returning an array, and load it from `adapter.php` (`require __DIR__ . '/config.php'`). The example uses:

```php
return [
    'db' => ['dsn' => 'mysql:host=127.0.0.1;dbname=sales;charset=latin1', 'user' => 'reader', 'password' => '', 'timeout' => 5],
    'width' => 40, 'encoding' => 'cp850', 'charset' => 12, 'page_length' => 0,
    'header' => ['SAMPLE STORE', 'Main Street 123', 'Tel. 555-0100'],
    'footer' => 'Thank you for your purchase',
];
```

The panel shows every adapter with the result of `check()`; a failing database keeps the API and other adapters printing.

## Creating an adapter

```
cp -r adapters/example adapters/office
```

Then edit `adapters/office/adapter.php`:

1. `name()` → `'office'`.
2. `routes()` → the host's URLs, e.g. `['printer/imprime_nota.php' => [$this, 'sale'], 'printer/imprime_edo_cuenta_cliente.php' => [$this, 'statement']]`.
3. `check()` → open the database with a short timeout and return `$e->getMessage()` on failure:
   ```php
   try { $this->pdo(); return null; } catch (Throwable $e) { return $e->getMessage(); }
   ```
4. One method per route that queries, builds the ticket, and returns `Document::raw($builder->build(), "sale $id")`.

Restart the service (or the `php -S` process) after adding or changing an adapter; adapters are loaded at start-up.

## Database access

The server is single-threaded: while a handler waits on a slow database, every station waits. Keep queries short and set a timeout:

```php
private function pdo(): PDO
{
    $db = $this->config['db'];
    return new PDO($db['dsn'], $db['user'], $db['password'], [
        PDO::ATTR_TIMEOUT => $db['timeout'],
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
```

The bundled PHP ships `pdo_mysql`, `mysqli`, and `pdo_sqlite`. For legacy `latin1` databases put `charset=latin1` in the DSN and convert strings to UTF-8 before handing them to the builder (`mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')`), or keep everything in UTF-8 if the host already does.

## Porting a legacy ESC/P ticket

Old code typically concatenates control bytes and padded strings:

```php
$t  = chr(27).'@';            // init
$t .= chr(27).'R'.chr(12);    // Latin-American character table
$t .= chr(14).'TIENDA'.chr(20)."\r\n";
$t .= str_pad($nombre, 30).str_pad($total, 10, ' ', STR_PAD_LEFT)."\r\n";
```

With `Alice\Escp\Builder` the same ticket is:

```php
use Alice\Escp\Builder;
use Alice\Escp\SpanishNumbers;

$b = new Builder(40, 'cp850');          // characters per line, printer code page
$b->init()->charset(Builder::CHARSET_LATIN_AMERICA);
$b->doubleWidth(true)->center('TIENDA')->doubleWidth(false);
$b->columns([$nombre, Builder::money($total)], [30, 10], ['left', 'right']);
$b->line('(' . SpanishNumbers::amount($total) . ')');  // CUATRO MIL ... PESOS 70/100 M.N.
$b->feed(2)->formFeed();
return Document::raw($b->build(), "nota $id");
```

Builder methods: `init`, `charset(n)`, `lineSpacing16`, `lineSpacing18`, `lineSpacing(n/216)`, `pageLength(lines)`, `pageLengthInches(n)`, `condensed(bool)`, `doubleWidth(bool)`, `bold(bool)`, `underline(bool)`, `text`, `line(text, align)`, `center`, `right`, `rule(char)`, `columns(cells, widths, aligns)` (width `0` = whatever is left), `feed(n)`, `formFeed`, `raw(bytes)`, `build`. Text is given in UTF-8 and transcoded as it is appended; padding counts characters, not bytes. `SpanishNumbers::amount()` writes amounts in words; pass `accents: false` for printers without the Latin table.

### Character sets

`ESC R n` selects the printer's international set (12 = Latin America, 7 = Spain, 0 = USA) and `cp850` is the matching PC code page for Spanish on Epson 9-pin printers: `ñ` is `0xA4`, `á` `0xA0`, `é` `0x82`. If your printer is set to the US table and you see wrong accents, try `cp437` (identical for most Spanish letters) or set the printer's default table in its DIP switches/menu. The `Builder` encoding and the `Document::text()` encoding only transcode letters; control bytes you append with `raw()` are never touched.

Form feed (`\f`) advances to the next page as defined by `pageLength()`; set the page length to your ticket height so the tear-off lands in the right place.

## Testing without a printer

Run Alice in dry-run (`ALICE_DRY_RUN=1`, or on Linux it is automatic), open the adapter URL, and inspect `data/spool/<job>.prn` with a hex viewer or compare it byte-for-byte with the file the old module produced. `data/last/<printer-key>.prn` always holds the last document sent to each printer; the panel's **Reprint last** sends it again.
