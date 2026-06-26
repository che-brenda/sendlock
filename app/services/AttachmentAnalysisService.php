<?php

namespace App\Services;

/**
 * Attachment analysis (filename-level). Deep content/sandbox inspection is a
 * later real driver; for now we flag dangerous executable extensions, macro-
 * enabled Office documents, archives, and double-extension disguises.
 *
 * @see analyze() accepts an array of attachment filenames.
 */
class AttachmentAnalysisService
{
    private const DANGEROUS = ['exe', 'scr', 'bat', 'cmd', 'com', 'js', 'vbs', 'jar', 'msi', 'ps1', 'hta'];

    private const MACRO_DOCS = ['docm', 'xlsm', 'pptm', 'dotm'];

    private const ARCHIVES = ['zip', 'rar', '7z', 'gz', 'iso'];

    private const MAX_SCORE = 50;

    /**
     * @param  string[]  $filenames
     */
    public static function analyze(array $filenames): array
    {
        $score = 0;
        $findings = [];

        foreach ($filenames as $name) {
            $name = strtolower(trim((string) $name));

            if ($name === '') {
                continue;
            }

            $parts = explode('.', $name);
            $ext = end($parts);

            // Double extension disguise, e.g. invoice.pdf.exe
            if (count($parts) >= 3 && in_array($ext, array_merge(self::DANGEROUS, self::ARCHIVES), true)) {
                $score += 35;
                $findings[] = 'Attachment uses a double extension: '.$name;

                continue;
            }

            if (in_array($ext, self::DANGEROUS, true)) {
                $score += 40;
                $findings[] = 'Dangerous executable attachment: '.$name;
            } elseif (in_array($ext, self::MACRO_DOCS, true)) {
                $score += 30;
                $findings[] = 'Macro-enabled document attachment: '.$name;
            } elseif (in_array($ext, self::ARCHIVES, true)) {
                $score += 15;
                $findings[] = 'Archive attachment (contents unscanned): '.$name;
            }
        }

        return ['score' => min($score, self::MAX_SCORE), 'findings' => $findings];
    }
}
