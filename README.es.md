[English](README.md) · [Español](README.es.md)

# Alice

Servidor de impresión en red local para impresoras de tickets ESC/P. Corre como servicio de Windows en la PC que tiene la impresora; las demás PCs de la oficina imprimen llamando a una URL y Alice manda cada trabajo a la impresora asignada a esa caja. Sin compartir impresoras, sin tocar el `hosts` de las cajas, sin Laragon.

Estado: **0.1.0 en desarrollo** — código completo, falta la verificación en Windows (ver [`CHANGELOG.md`](CHANGELOG.md)).

## Inicio rápido

**PC de la oficina (Windows):** ejecuta `Alice-<versión>-setup.exe`; se abre la ventana de control "Alice" (icono en la bandeja, Iniciar/Detener estilo Laragon); pulsa **Abrir panel**, pulsa **Agregar** junto a la impresora de tickets y luego **Página de prueba**. Detalles en [`docs/install.md`](docs/install.md) (en inglés).

**Desarrollo (cualquier sistema):**

```sh
php -S 0.0.0.0:8080 -t public public/router.php   # simulación automática en Linux/macOS; ALICE_DRY_RUN=1 en Windows
php tests/run.php                                   # pruebas
```

## La API en cinco líneas

```sh
curl -X POST http://alice/api/print -H 'Content-Type: application/json' -d '{"text": "HOLA\n"}'
# → {"ok": true, "job": "20260101-120000-a1b2c3", "printer": "front-desk", "bytes": 7, "dry_run": false}
curl -X POST http://alice/api/print -d '{"raw": "<bytes ESC/P en base64>", "printer": "almacen"}'
curl http://alice/api/jobs
```

Las URLs del sistema de ventas (`/printer/imprime_nota.php?id_nota=42`) siguen funcionando mediante un adaptador pequeño que se escribe una vez; ver [`docs/adapters.md`](docs/adapters.md). Referencia completa: [`docs/api.md`](docs/api.md).

## Cómo funciona

- Una **estación** es una IP cliente. La primera vez que imprime aparece en el panel; asígnale una impresora o deja que use la predeterminada. Una estación sin impresora recibe una sola vez una página para elegir.
- Una **impresora** es `local` (instalada en el servidor, sin compartir: Alice escribe bytes RAW por el spooler de Windows), `unc` (`\\PC\Recurso`) o `port` (`LPT1`, `COM1`).
- Cada trabajo tiene su propio archivo de spool y su línea en el registro; el último documento de cada impresora se puede reimprimir desde el panel.
- Todo vive en JSON legible bajo `data/`.

## Especificación

El código implementa el cambio `alice-print-server` de [`openspec/`](openspec/): [propuesta](openspec/changes/alice-print-server/proposal.md), [especificaciones](openspec/changes/alice-print-server/specs/), [diseño](openspec/changes/alice-print-server/design.md), [tareas](openspec/changes/alice-print-server/tasks.md).

## Nombre

Alice Margatroid, de Touhou Project, de Team Shanghai Alice. Es una herramienta de fan sin relación con Team Shanghai Alice.

## Licencia

[MIT](LICENSE).
