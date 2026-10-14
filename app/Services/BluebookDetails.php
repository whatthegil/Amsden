<?php

namespace App\Services;

use App\Services\Ocr\BinaryFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * A bluebook's catalogue details read from the document itself, so the
 * author does not type them in (and mistype them) on the upload form.
 *
 * CSPC papers share one layout, and that is what this reads:
 *
 *  - the title page: the title, "A Capstone Project / presented to the
 *    Faculty of / College of ...", "for the degree ...", "By", the authors
 *    one per line, then the date;
 *  - the abstract page: "ABSTRACT", then labelled fields - Title, Authors,
 *    Number of Pages, School, Degree Conferred, Keywords - then the abstract;
 *  - the acknowledgements, which name the thesis / capstone adviser.
 *
 * Whatever cannot be found comes back empty, for the library to fill in when
 * it reviews the submission. Nothing here throws on odd input.
 */
class BluebookDetails
{
    /** Labels on the abstract page, by the field they fill. */
    private const LABELS = [
        'title'    => 'title',
        'authors'  => 'authors?|researchers?|proponents?',
        'pages'    => '(?:number|no\.?)\s+of\s+pages',
        'school'   => 'school|institution',
        'degree'   => 'degree\s+conferred|degree|program|course',
        'adviser'  => 'adviser|advisor',
        'keywords' => 'key\s*words?',
    ];

    /** Words kept lowercase inside a title. */
    private const MINOR_WORDS = ['a','an','the','and','or','but','nor','of','in','on','at','to','for','by','with','from','into','via','as','using','its'];

    /** Degree words that say nothing about which program it is. */
    private const GENERIC_DEGREE_WORDS = ['bachelor','of','science','in','the','and','major','arts','degree'];

    /** Letters after a name: degrees and suffixes, dropped from an adviser's name. */
    private const POST_NOMINALS = '(?:MIT|MSIT|MSCS|MSc|MS|MA|MAEd|MAED|MAT|MBA|MPA|MLIS|DIT|DBA|PhD|Ph\.D\.|EdD|Ed\.D\.|LPT|CPA|RN|Engr\.)';

    /**
     * Every detail the upload form used to ask for, from the PDF at $path.
     *
     * @return array{title:string, authors:list<string>, adviser:string, keywords:list<string>, abstract:string, department:string, program:string, pages:int}
     */
    public static function fromPdf(string $path, ?string $text = null): array
    {
        $text ??= PdfTextExtractor::extract($path);
        $details = self::fromText($text);

        // The file's own count is exact; the abstract page's figure is what
        // the authors typed, and the fallback.
        $details['pages'] = self::pageCount($path) ?? $details['pages'];

        return $details;
    }

    /** @return array{title:string, authors:list<string>, adviser:string, keywords:list<string>, abstract:string, department:string, program:string, pages:int} */
    public static function fromText(string $text): array
    {
        $text  = self::clean($text);
        $lines = array_map('trim', explode("\n", $text));

        $cover    = self::coverPage($lines);
        $abstract = self::abstractPage($lines);

        $title = $cover['title'] !== ''
            ? self::titleCase($cover['title'], $abstract['title'])
            : self::titleCase($abstract['title'], $abstract['title']);

        // The title page's list and order, but the abstract page's spelling
        // where the two differ only in spacing ("JoshuaCharles").
        $authors = $cover['authors'] ?: $abstract['authors'];
        $squash  = fn($n) => mb_strtolower(preg_replace('/[\s.,]+/u', '', $n));
        foreach ($authors as &$author) {
            foreach ($abstract['authors'] as $other) {
                if ($squash($other) === $squash($author) && substr_count($other, ' ') > substr_count($author, ' ')) $author = $other;
            }
        }
        unset($author);

        [$department, $program] = self::college(
            implode(' ', array_slice($lines, 0, 80)),
            $cover['degree'] . ' ' . $abstract['degree']
        );

        $adviser = $abstract['adviser'] !== '' ? self::personName($abstract['adviser']) : self::adviser($text);

        return [
            'title'      => $title,
            'authors'    => array_values(array_filter(array_map([self::class, 'personName'], $authors))),
            'adviser'    => $adviser,
            'keywords'   => $abstract['keywords'],
            'abstract'   => $abstract['body'],
            'department' => $department,
            'program'    => $program,
            'pages'      => $abstract['pages'],
        ];
    }

    /** The title a submission is filed under until its own is read: the file's name. */
    public static function placeholderTitle(?string $fileName): string
    {
        $name = trim(preg_replace('/[_\s]+/u', ' ', pathinfo((string) $fileName, PATHINFO_FILENAME)));
        return $name !== '' ? $name : 'Untitled bluebook';
    }

    /** The details that came back empty, by the name the author knows them by. */
    public static function missing(array $details): array
    {
        $labels = [
            'title' => 'title', 'authors' => 'authors', 'adviser' => 'adviser', 'keywords' => 'keywords',
            'abstract' => 'abstract', 'department' => 'college', 'program' => 'program', 'pages' => 'number of pages',
        ];
        return array_values(array_filter($labels, fn($key) => empty($details[$key]), ARRAY_FILTER_USE_KEY));
    }

    /**
     * Fill in what a submission's upload could not read, now that its text is
     * in - a scan's details come with its OCR. Only empty fields are written,
     * and only while it waits for review, so nothing a reviewer or the author
     * set is ever overwritten.
     */
    public static function fillMissing(\App\Models\Bluebook $bluebook): void
    {
        if ($bluebook->status !== 'Pending' || trim((string) $bluebook->ocr_text) === '') return;

        $found   = self::fromText($bluebook->ocr_text);
        $changes = [];

        if ($found['title'] !== '' && $bluebook->title === self::placeholderTitle($bluebook->file_original_name)) {
            $changes['title'] = $found['title'];
        }
        foreach (['authors', 'keywords'] as $list) {
            if ($found[$list] && empty(array_filter((array) $bluebook->$list))) $changes[$list] = $found[$list];
        }
        foreach (['adviser', 'abstract', 'department', 'program'] as $field) {
            if ($found[$field] !== '' && trim((string) $bluebook->$field) === '') $changes[$field] = $found[$field];
        }
        // A program belongs to its college; never pair one with another's.
        if (isset($changes['program']) && !isset($changes['department']) && $bluebook->department !== $found['department']) {
            unset($changes['program']);
        }
        if ($found['pages'] > 0 && (int) $bluebook->pages === 0) {
            $changes['pages'] = $found['pages'];
        }

        if ($changes) {
            $bluebook->forceFill($changes)->save();
        }
    }

    /** The PDF's page count, from whichever tool is installed; null when none can tell. */
    public static function pageCount(string $path): ?int
    {
        if (!is_file($path)) return null;

        $mutool = BinaryFinder::find(config('ocr.mutool_path'), ['mutool'], [
            'C:\\Program Files\\mupdf\\mutool.exe',
            (getenv('LOCALAPPDATA') ?: '') . '\\Microsoft\\WinGet\\Packages\\ArtifexSoftware.mutool_*\\mupdf-*\\mutool.exe',
        ]);
        if ($mutool && preg_match('/^\s*(\d+)\s*$/', (string) self::run([$mutool, 'show', $path, 'trailer/Root/Pages/Count']), $m)) {
            return (int) $m[1] ?: null;
        }

        $pdfinfo = BinaryFinder::find(null, ['pdfinfo'], [
            'C:\\poppler*\\Library\\bin\\pdfinfo.exe',
            'C:\\Program Files\\poppler*\\bin\\pdfinfo.exe',
        ]);
        if ($pdfinfo && preg_match('/^Pages:\s*(\d+)/m', (string) self::run([$pdfinfo, $path]), $m)) {
            return (int) $m[1] ?: null;
        }

        // No tools: the page tree's /Count, which an uncompressed PDF shows
        // in plain text. The largest is the root's.
        $raw = @file_get_contents($path, false, null, 0, 40 * 1024 * 1024);
        if ($raw !== false && preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)|/Count\s+(\d+)[^>]*?/Type\s*/Pages\b#', $raw, $m)) {
            $counts = array_map('intval', array_filter(array_merge($m[1], $m[2])));
            return $counts ? max($counts) : null;
        }

        return null;
    }

    // ── The title page ────────────────────────────────────────────────────

    /**
     * Title, authors and degree from the title page: the title is what comes
     * before "A Capstone Project" / "presented to", the degree follows "for
     * the degree", and the authors are the lines between "By" and the date.
     */
    private static function coverPage(array $lines): array
    {
        $out  = ['title' => '', 'authors' => [], 'degree' => ''];
        $head = array_slice($lines, 0, 60);

        // "presented to" ends the title, and so does the "A Capstone Project"
        // line just above it - not one that is part of the title itself
        // ("...USING ODOO: A CAPSTONE PROJECT").
        $type   = '/^(?:an?\s+)?(?:undergraduate\s+)?(?:capstone|thesis|research|study|project|special\s+problem|feasibility)\b/i';
        $marker = null;
        foreach ($head as $i => $line) {
            if (preg_match('/^presented\s+to\b/i', $line)) {
                $marker = $i;
                for ($j = $i - 1; $j >= 0 && $head[$j] === ''; $j--);
                if ($j >= 0 && preg_match($type, $head[$j])) $marker = $j;
                break;
            }
        }
        if ($marker === null) {
            foreach ($head as $i => $line) {
                if (preg_match($type, $line)) {
                    $marker = $i;
                    break;
                }
            }
        }
        if ($marker === null) return $out;

        // A scanned page's letterhead reads as noise; it ends at "ISO 9001:2015
        // Certified", and the title starts below it.
        $from = 0;
        foreach (array_slice($head, 0, $marker) as $i => $line) {
            if (preg_match('/\bISO\s*\d{4}|\bcertified\b/i', $line)) $from = $i + 1;
        }

        $titleLines = [];
        foreach (array_slice($head, $from, $marker - $from) as $line) {
            // The school's letterhead above the title, not "...OF CAMARINES
            // SUR POLYTECHNIC COLLEGES" inside it.
            if ($line === '' || (!$titleLines && self::isInstitutionLine($line))) continue;
            $titleLines[] = $line;
        }
        $out['title'] = self::joinLines($titleLines);

        $by = null;
        foreach ($head as $i => $line) {
            if ($i <= $marker) continue;
            if (preg_match('/\bdegree\b/i', $line) && $out['degree'] === '') {
                // The degree can run onto the next line or two.
                $out['degree'] = self::joinLines(array_slice($head, $i, 3));
            }
            if (preg_match('/^by\s*:?$/i', $line)) {
                $by = $i;
                break;
            }
        }
        if ($by === null) return $out;

        foreach (array_slice($head, $by + 1) as $line) {
            if ($line === '') continue;
            if (self::isDateLine($line) || preg_match('/^(?:[ivxlc]+|\d+)$/i', $line) || preg_match('/^[A-Z\s]{4,}$/', $line) && str_word_count($line) <= 2) break;
            if (!self::looksLikeName($line)) break;
            $out['authors'][] = $line;
        }

        return $out;
    }

    private static function isInstitutionLine(string $line): bool
    {
        return (bool) preg_match('/camarines\s+sur|polytechnic|^college\s+of|nabua|naga\s+city|republic\s+of|^cspc\b/i', $line);
    }

    private static function isDateLine(string $line): bool
    {
        return (bool) preg_match('/^(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?,?\s+(?:\d{1,2},?\s+)?\d{4}$|^(?:19|20)\d{2}$/i', $line);
    }

    private static function looksLikeName(string $line): bool
    {
        // Two to six words, each capitalised or an initial, no sentence words.
        $words = preg_split('/\s+/', trim($line));
        return count($words) >= 2 && count($words) <= 7
            && preg_match('/^[\p{Lu}][\p{L}\p{M}.,\'’\-\s]+$/u', $line)
            && !preg_match('/\b(?:college|university|faculty|requirements|degree|science|bachelor)\b/i', $line);
    }

    // ── The abstract page ─────────────────────────────────────────────────

    /**
     * The labelled fields after "ABSTRACT" and the abstract itself. Values
     * may sit on the label's line ("Title: ...", "Title ..."), under it, or
     * after a lone ":" line, and wrap over several lines; the abstract text
     * starts at the first full-width line after the last label.
     */
    private static function abstractPage(array $lines): array
    {
        $out = ['title' => '', 'authors' => [], 'pages' => 0, 'degree' => '', 'adviser' => '', 'keywords' => [], 'body' => ''];

        $start = null;
        foreach (array_slice($lines, 0, 1500, true) as $i => $line) {
            if (preg_match('/^abstract\s*:?$/i', $line)) {
                $start = $i + 1;
                break;
            }
        }
        if ($start === null) return $out;

        $labelPattern = '/^(' . implode('|', self::LABELS) . ')\s*:?\s*(.*)$/i';

        $fields  = [];
        $current = null;
        $body    = [];
        $inBody  = false;
        foreach (array_slice($lines, $start, 200) as $line) {
            if (preg_match('/^(?:table\s+of\s+contents|chapter\s+(?:1|i|one)\b|introduction$)/i', $line)) break;
            if (preg_match('/^(?:[ivxlc]+|\d+)$/i', $line) && $inBody) break;   // page number: end of the page

            if (!$inBody && preg_match($labelPattern, $line, $m) && self::isLabel($m[1], $m[2])) {
                $current = self::fieldFor($m[1]);
                $fields[$current] = [];
                $value = ltrim($m[2], ': ');
                if ($value !== '') $fields[$current][] = $value;
                continue;
            }

            // The abstract proper: a full line of prose, after the labels.
            if (!$inBody && mb_strlen($line) >= 60 && ($current === null || !empty($fields[$current]))) {
                $inBody = true;
            }

            if ($inBody) {
                $body[] = $line;
            } elseif ($current !== null && $line !== '' && $line !== ':') {
                $fields[$current][] = $line;
            }
        }

        $out['title']   = self::joinLines($fields['title'] ?? []);
        $out['authors'] = array_values(array_filter($fields['authors'] ?? [], fn($l) => self::looksLikeName($l)));
        $out['degree']  = self::joinLines($fields['degree'] ?? []);
        $out['adviser'] = self::joinLines($fields['adviser'] ?? []);
        if (preg_match('/\d+/', self::joinLines($fields['pages'] ?? []), $m)) {
            $out['pages'] = (int) $m[0];
        }
        $out['keywords'] = self::keywords($fields['keywords'] ?? []);
        $out['body']     = self::paragraph($body);

        return $out;
    }

    /** "Title Readiness of ..." is a label; "Titles of the study ..." in prose is not. */
    private static function isLabel(string $label, string $rest): bool
    {
        return $rest === '' || str_starts_with(ltrim($rest), ':') || preg_match('/^\s*:?\s*\p{Lu}|^\s*\d/u', $rest);
    }

    private static function fieldFor(string $label): string
    {
        foreach (self::LABELS as $field => $pattern) {
            if (preg_match('/^(?:' . $pattern . ')$/i', trim($label))) return $field;
        }
        return 'other';
    }

    /** Comma or semicolon separated; one per line when there are no separators. */
    private static function keywords(array $lines): array
    {
        $joined = self::joinLines($lines);
        $parts  = preg_match('/[,;]/', $joined)
            ? preg_split('/\s*[,;]\s*/', $joined)
            : array_map([self::class, 'joinLines'], array_map(fn($l) => [$l], $lines));

        $parts = array_map(fn($k) => trim($k, " \t.·•"), $parts);
        return array_values(array_unique(array_filter($parts, fn($k) => $k !== '' && mb_strlen($k) <= 80)));
    }

    /** The abstract's lines as one paragraph, words broken by a hyphen rejoined. */
    private static function paragraph(array $lines): string
    {
        $text = self::joinLines($lines);
        // A trailing keywords line some papers put under the abstract.
        $text = preg_replace('/\s*key\s*words?\s*:.*$/is', '', $text);
        return trim($text);
    }

    // ── The college and program ───────────────────────────────────────────

    /**
     * The department whose college the title page names, and the program in
     * it that the degree line describes. The degree is matched on its telling
     * words ("Information Technology"), so "Information System" still finds
     * "Information Systems" and a shortened "Bachelor of Library Information
     * Science" finds its program.
     *
     * @return array{0: string, 1: string}
     */
    private static function college(string $frontMatter, string $degree): array
    {
        $front = ' ' . SearchService::normalize($frontMatter) . ' ';

        $department = '';
        $at = PHP_INT_MAX;
        foreach (config('departments', []) as $code => $dept) {
            $pos = strpos($front, ' ' . SearchService::normalize($dept['name']) . ' ');
            if ($pos !== false && $pos < $at) {
                [$department, $at] = [$code, $pos];
            }
        }

        $degreeWords = array_flip(array_map([SearchService::class, 'stem'], explode(' ', SearchService::normalize($degree))));
        $best = ['', '', 0, false];   // code, program, telling words, in the named college
        foreach (config('departments', []) as $code => $dept) {
            foreach ($dept['programs'] as $program) {
                $telling = self::tellingWords($program);
                if (!$telling || array_diff($telling, array_keys($degreeWords))) continue;

                $inCollege = $code === $department;
                if (count($telling) > $best[2] || (count($telling) === $best[2] && $inCollege && !$best[3])) {
                    $best = [$code, $program, count($telling), $inCollege];
                }
            }
        }

        // The program settles the department when the title page named none,
        // or named one that does not offer it: a degree is the more specific.
        return $best[1] === '' ? [$department, ''] : [$best[0], $best[1]];
    }

    private static function tellingWords(string $program): array
    {
        $words = explode(' ', SearchService::normalize($program));
        $words = array_diff($words, self::GENERIC_DEGREE_WORDS);
        return array_values(array_unique(array_map([SearchService::class, 'stem'], $words)));
    }

    // ── The adviser ───────────────────────────────────────────────────────

    /**
     * The thesis / capstone / project adviser the acknowledgements thank -
     * "Juan P. Dela Cruz, MIT, our capstone adviser" or "our thesis adviser,
     * Mr. Juan P. Dela Cruz". The subject adviser (the course's teacher) is
     * not the paper's adviser and is passed over, unless the same person is
     * named as both.
     */
    private static function adviser(string $text): string
    {
        // The front matter only, as one line: names wrap across lines there.
        $cut   = preg_split('/\n\s*(?:table\s+of\s+contents|chapter\s+(?:1|i|one))\b/i', $text, 2)[0];
        $front = preg_replace('/\s+/u', ' ', mb_substr($cut, 0, 30000));

        $name   = "((?:(?:Mr|Ms|Mrs|Dr|Engr|Prof)\.?\s+)?(?:\p{Lu}[\p{L}\p{M}'’\-]*\.?\s+){1,5}\p{Lu}[\p{L}\p{M}'’\-]+)";
        $titles = '(?:,?\s*(?:' . self::POST_NOMINALS . '|Jr\.?|Sr\.?|II|III))*';
        $role   = '((?:[\p{L}]+\s+){0,3}?)advis[eo]r((?:\s+and\s+(?:[\p{L}]+\s+){0,2}advis[eo]r)?)';

        $found = [];
        // "<name>, MIT, our capstone adviser"
        if (preg_match_all("/{$name}{$titles},?\s+(?:who\s+(?:is|was|served\s+as)\s+)?(?:as\s+)?(?:our|their|the|his|her)?\s*{$role}/iu", $front, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) $found[] = [$hit[0][1], $hit[1][0], $hit[2][0] . ' ' . $hit[3][0]];
        }
        // "our thesis adviser, Mrs. <name>"
        if (preg_match_all("/(?:our|their|the)\s+{$role},?\s+{$name}/iu", $front, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) $found[] = [$hit[0][1], $hit[3][0], $hit[1][0] . ' ' . $hit[2][0]];
        }
        usort($found, fn($a, $b) => $a[0] <=> $b[0]);

        foreach ($found as [, $who, $roleWords]) {
            $roleWords = strtolower($roleWords);
            // The subject adviser, unless "Subject Adviser and Project Adviser".
            if (str_contains($roleWords, 'subject') && !str_contains($roleWords, ' and ')) continue;
            if (preg_match('/\b(?:panel|statistic|grammar|technical|language|english|research\s+instructor)/', $roleWords)) continue;

            $person = self::personName($who);
            if ($person !== '') return $person;
        }

        return '';
    }

    // ── Names and text ────────────────────────────────────────────────────

    /**
     * A name as the archive files it, "Last, First M.": "Juan P. Dela Cruz"
     * becomes "Dela Cruz, Juan P."; one already written that way is kept.
     * Courtesy titles, degrees and a leading "To" are dropped.
     */
    public static function personName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name), " \t,;:");
        $name = preg_replace('/^(?:(?:to|and|the|our|their|foremost|also|lastly|finally|thank|we|mr|ms|mrs|dr|engr|prof)\.?\s+)+/i', '', $name);
        $name = preg_replace('/(?:,?\s*' . self::POST_NOMINALS . ')+$/', '', $name);

        $suffix = '';
        if (preg_match('/,?\s*\b(Jr|Sr|II|III|IV)\.?$/', $name, $m)) {
            $suffix = ' ' . $m[1] . (in_array($m[1], ['Jr', 'Sr'], true) ? '.' : '');
            $name   = trim(substr($name, 0, -strlen($m[0])));
        }
        if ($name === '' || !preg_match('/\p{L}/u', $name)) return '';

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
            return self::nameCase($last) . $suffix . ', ' . self::nameCase($first);
        }

        $words = explode(' ', $name);
        if (count($words) < 2) return self::nameCase($name);

        // The last name follows the middle initial ("Ma. Therese T. Sombrero",
        // "Gian B. De las Llagas"); with no initial it is the last word.
        $split = count($words) - 1;
        for ($i = count($words) - 2; $i >= 1; $i--) {
            if (preg_match('/^\p{Lu}{1,2}\.$/u', $words[$i])) {
                $split = $i + 1;
                break;
            }
        }

        $first = implode(' ', array_slice($words, 0, $split));
        $last  = implode(' ', array_slice($words, $split));
        return self::nameCase($last) . $suffix . ', ' . self::nameCase($first);
    }

    /** "DELA CRUZ" → "Dela Cruz"; a name already in mixed case is left as typed. */
    private static function nameCase(string $name): string
    {
        if (mb_strtoupper($name) !== $name) return $name;
        return preg_replace_callback('/\p{L}+/u', fn($w) => mb_strlen($w[0]) <= 2 && str_ends_with($name, $w[0] . '.')
            ? $w[0]
            : mb_convert_case($w[0], MB_CASE_TITLE), $name);
    }

    /**
     * A title in title case. Words the abstract page writes in its own way
     * keep that spelling ("YOLOv11", "KKBN", "Odoo"); the rest are capitalised,
     * with short joining words in lowercase.
     */
    private static function titleCase(string $title, string $reference): string
    {
        $title = trim($title);
        if ($title === '') return '';

        $spelling = [];
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $reference, $m);
        foreach ($m[0] as $word) {
            $key = mb_strtolower($word);
            // Prefer the spelling with capitals inside it: an acronym or brand.
            if (!isset($spelling[$key]) || preg_match('/\p{Lu}/u', mb_substr($word, 1))) $spelling[$key] = $word;
        }

        $shouting = mb_strtoupper($title) === $title;
        $index    = 0;
        $newPart  = true;
        return preg_replace_callback('/([\p{L}\p{N}][\p{L}\p{N}\'’\-]*)(\.?)|([:—–]\s*)/u', function ($m) use ($spelling, $shouting, &$index, &$newPart) {
            if (!empty($m[3])) {
                $newPart = true;
                return $m[3];
            }
            $word  = $m[1];
            $dot   = $m[2];
            $lower = mb_strtolower($word);
            $first = $index++ === 0 || $newPart;
            $newPart = false;

            if (isset($spelling[$lower]) && preg_match('/\p{Lu}/u', mb_substr($spelling[$lower], 1))) return $spelling[$lower] . $dot;
            if (!$shouting) return $word . $dot;
            // "A." in "Wennie A. Samper" is an initial, not the article.
            if (!$first && $dot === '' && in_array($lower, self::MINOR_WORDS, true)) return $lower;
            return mb_convert_case($lower, MB_CASE_TITLE) . $dot;
        }, $title);
    }

    /** Lines as one string, rejoining a word a line break split with a hyphen. */
    private static function joinLines(array $lines): string
    {
        $text = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($text !== '' && preg_match('/\p{Ll}-$/u', $text) && preg_match('/^\p{Ll}/u', $line)) {
                $text = mb_substr($text, 0, -1) . $line;
            } else {
                $text .= ($text === '' ? '' : ' ') . $line;
            }
        }
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Line endings evened out, and "n˜" (how some PDFs carry ñ) put back together. */
    private static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);
        $text = strtr($text, ['n˜' => 'ñ', 'N˜' => 'Ñ', '˜n' => 'ñ', '˜N' => 'Ñ']);
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        return preg_replace('/[ \t]+/', ' ', $text);
    }

    /** @param list<string> $cmd */
    private static function run(array $cmd): ?string
    {
        try {
            $process = new Process($cmd);
            $process->setTimeout(30);
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
