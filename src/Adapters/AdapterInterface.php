<?php

declare(strict_types=1);

namespace Alice\Adapters;

/**
 * Contract for `adapters/<name>/adapter.php`, which must `return` an object
 * implementing this interface.
 */
interface AdapterInterface
{
    /** Short name shown in the panel (usually the directory name). */
    public function name(): string;

    /**
     * Map of URL path => handler. Paths are relative to the server root and
     * may contain dots and legacy extensions, e.g. "printer/imprime_nota.php".
     *
     * Handler signature: function (array $params, Context $ctx): Document|string
     * where $params are the merged query/form parameters, a Document is printed
     * through the normal print-jobs flow, and a returned string is an error
     * message (nothing is printed, a failed job is logged).
     *
     * @return array<string,callable>
     */
    public function routes(): array;

    /**
     * Connectivity check shown in the panel: return null when everything is
     * fine, otherwise a short error message (e.g. the database error).
     */
    public function check(): ?string;
}
