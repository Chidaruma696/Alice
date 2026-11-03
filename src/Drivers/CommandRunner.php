<?php

declare(strict_types=1);

namespace Alice\Drivers;

/**
 * Runs an external command without a shell (argv array) and returns
 * [exit code, stdout, stderr]. Drivers accept a replacement callable so tests
 * can assert the command line without running anything.
 */
final class CommandRunner
{
    /** @return array{0:int,1:string,2:string} */
    public static function run(array $argv, int $timeoutSeconds = 60): array
    {
        $proc = @proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return [127, '', 'cannot start ' . ($argv[0] ?? 'command')];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                return [(int) $status['exitcode'], $out, $err];
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                return [124, $out, trim($err . "\ncommand timed out after {$timeoutSeconds}s")];
            }
            usleep(20000);
        }
    }
}
