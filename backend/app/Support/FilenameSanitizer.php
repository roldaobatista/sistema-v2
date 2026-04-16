<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sanitizes uploaded file names to prevent path traversal and other attacks.
 */
class FilenameSanitizer
{
    /**
     * Generate a safe filename using UUID + original extension.
     *
     * @param  string  $originalName  The original filename from the client.
     * @return string A safe filename like "a1b2c3d4-e5f6-7890-abcd-ef1234567890.pdf"
     */
    public static function safe(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Reject dangerous extensions
        $blocked = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'rb', 'js', 'jsp', 'asp', 'aspx'];
        if (in_array($extension, $blocked, true)) {
            $extension = 'blocked';
        }

        return Str::uuid().($extension ? ".{$extension}" : '');
    }

    /**
     * Sanitize the original filename, keeping it human-readable but safe.
     *
     * @param  string  $originalName  The original filename from the client.
     * @return string A sanitized filename like "my_report.pdf"
     */
    public static function sanitize(string $originalName): string
    {
        // Remove path components (prevents ../../etc/passwd)
        $name = basename($originalName);

        // Remove null bytes
        $name = str_replace("\0", '', $name);

        // Replace potentially dangerous characters
        $name = preg_replace('/[^\w\s\-\.]/', '_', $name);

        // Collapse multiple underscores/dots
        $name = preg_replace('/_{2,}/', '_', $name);
        $name = preg_replace('/\.{2,}/', '.', $name);

        // Trim and limit length
        $name = trim($name, ' ._');
        if (strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = substr(pathinfo($name, PATHINFO_FILENAME), 0, 190).($ext ? ".{$ext}" : '');
        }

        // Fallback if empty
        return $name ?: Str::uuid()->toString();
    }
}
