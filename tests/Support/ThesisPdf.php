<?php

namespace Tests\Support;

/**
 * A small text PDF laid out like a CSPC bluebook's front matter: a title
 * page, the acknowledgements and the abstract page, each a page of its own,
 * one line of text per entry. Real text in a real PDF, so the tools the app
 * reads uploads with read it as they would a thesis.
 */
class ThesisPdf
{
    public const TITLE    = 'A Web-Based Inventory System for Campus Laboratories';
    public const AUTHORS  = ['Dela Cruz, Juan P.', 'Santos, Maria L.'];
    public const ADVISER  = 'Reyes, Ana B.';
    public const KEYWORDS = ['Inventory System', 'Web Application', 'Laboratory Management'];
    public const ABSTRACT = 'This study designed and built a web-based inventory system that keeps track of the equipment and supplies of the campus laboratories, replacing paper logbooks.';

    /** @return list<list<string>> */
    public static function pages(string $college = 'College of Computer Studies', string $degree = 'Bachelor of Science in Information Technology'): array
    {
        return [
            [
                'A WEB-BASED INVENTORY SYSTEM FOR',
                'CAMPUS LABORATORIES',
                '',
                'A Capstone Project',
                'presented to the Faculty of',
                $college,
                'Camarines Sur Polytechnic Colleges',
                '',
                'In Partial Fulfillment of the Requirements',
                'for the degree ' . $degree,
                '',
                'By',
                '',
                'Juan P. Dela Cruz',
                'Maria L. Santos',
                '',
                'December 2025',
            ],
            [
                'ACKNOWLEDGMENTS',
                '',
                'To Mr. Carlo D. Mendoza, our capstone subject adviser, for his patience in class.',
                'To Ana B. Reyes, MIT, our capstone adviser, for her guidance throughout this study.',
            ],
            [
                'ABSTRACT',
                '',
                'Title:',
                'A Web-Based Inventory System for',
                'Campus Laboratories',
                'Authors:',
                'Juan P. Dela Cruz',
                'Maria L. Santos',
                'Number of Pages:',
                '120',
                'School:',
                'Camarines Sur Polytechnic Colleges',
                'Degree Conferred:',
                $degree,
                'Keywords:',
                'Inventory System, Web Application,',
                'Laboratory Management',
                '',
                'This study designed and built a web-based inventory system that keeps track of the',
                'equipment and supplies of the campus laboratories, replacing paper logbooks.',
            ],
        ];
    }

    /** The PDF's bytes for $pages (default: the pages above). */
    public static function bytes(?array $pages = null): string
    {
        $pages ??= self::pages();

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];
        $next = 4;
        foreach ($pages as $lines) {
            $stream = "BT /F1 10 Tf 14 TL 50 760 Td\n";
            foreach ($lines as $line) {
                $stream .= '(' . strtr($line, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ") Tj T*\n";
            }
            $stream .= "ET";

            [$page, $content] = [$next, $next + 1];
            $next += 2;
            $kids[] = "{$page} 0 R";
            $objects[$page]    = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$content} 0 R >>";
            $objects[$content] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
        ksort($objects);

        $pdf     = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    /** The front matter as the text a PDF reader returns, for the parser alone. */
    public static function text(?array $pages = null): string
    {
        return implode("\n\f", array_map(fn($lines) => implode("\n", $lines), $pages ?? self::pages()));
    }
}
