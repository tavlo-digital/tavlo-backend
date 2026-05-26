<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\DB;

class DatabaseHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            DB::table('application_logs')->insert([
                'level' => $record->level->getName(),
                'message' => mb_substr($record->message, 0, 65535),
                'context' => json_encode($this->sanitizeContext($record->context)),
                'channel' => $record->channel,
                'logged_at' => $record->datetime->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Prevent infinite loops if the database is unavailable
        }
    }

    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $sanitized[$key] = [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile() . ':' . $value->getLine(),
                    'trace' => array_slice(
                        array_map(fn ($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                            $value->getTrace()
                    ), 0, 10),
                ];
            } elseif (is_object($value)) {
                $sanitized[$key] = '[object: ' . get_class($value) . ']';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
