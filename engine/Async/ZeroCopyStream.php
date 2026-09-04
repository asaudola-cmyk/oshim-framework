<?php
declare(strict_types=1);

namespace Oshim\Async;

class ZeroCopyStream
{
    public static function streamFileToSocket($fileHandle, $socketHandle, ?int $length = null, int $offset = 0): int
    {
        if (!is_resource($fileHandle) || !is_resource($socketHandle)) {
            return 0;
        }

        if ($offset > 0) {
            @fseek($fileHandle, $offset);
        }

        $sent = 0;
        $maxChunk = 65536; // 64KB chunks

        if (function_exists('stream_copy_to_stream')) {
            $copied = @stream_copy_to_stream($fileHandle, $socketHandle, $length ?? -1);
            if ($copied !== false) {
                return (int)$copied;
            }
        }

        while (!feof($fileHandle)) {
            if ($length !== null && $sent >= $length) {
                break;
            }

            $toRead = ($length !== null) ? min($maxChunk, $length - $sent) : $maxChunk;
            $chunk = @fread($fileHandle, $toRead);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $written = @fwrite($socketHandle, $chunk);
            if ($written === false) {
                break;
            }
            $sent += $written;
        }

        return $sent;
    }

    public static function splicePipes($readPipe, $writePipe, int $length): int
    {
        if (function_exists('stream_copy_to_stream')) {
            $res = @stream_copy_to_stream($readPipe, $writePipe, $length);
            return $res !== false ? (int)$res : 0;
        }
        $data = @fread($readPipe, $length);
        if ($data !== false && $data !== '') {
            $w = @fwrite($writePipe, $data);
            return $w !== false ? $w : 0;
        }
        return 0;
    }
}
