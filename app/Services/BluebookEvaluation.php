<?php

namespace App\Services;

/**
 * The Library Manual's criteria for evaluating a submission (5.2.1), read as
 * far as they can be from the bluebook's OCR text and its record.
 *
 * Each criterion comes out okay, not okay, or unchecked - the last for what
 * only a person can judge (margins, signatures, the naming convention) and
 * for anything that needs the text before it has been read. The automatic
 * read is a starting point: the admin confirms or overrides each item, and
 * what they save is what the author is shown.
 */
class BluebookEvaluation
{
    public const OK        = 'ok';
    public const ISSUE     = 'issue';
    public const UNCHECKED = 'unchecked';

    public const CRITERIA = [
        'approved_unit'   => 'Approved by the appropriate academic unit/college',
        'approval_pages'  => 'All required approval and certification pages are included',
        'format'          => 'Conforms to the approved institutional thesis format',
        'pagination'      => 'Pagination is complete, sequential and consistent',
        'layout'          => 'Headings, margins, spacing, fonts and numbering follow standards',
        'toc'             => 'Table of Contents matches the contents and page numbers',
        'lists'           => 'Lists of Tables, Figures and Appendices match the manuscript',
        'labels'          => 'All cited tables, figures and appendices are present and properly labelled',
        'complete'        => 'No pages, tables, figures or appendices are missing',
        'reproduction'    => 'Exact reproduction of the final approved printed manuscript, with signatures',
        'readable'        => 'PDF, readable, complete and free from corruption',
        'unencrypted'     => 'No password protection, editing restrictions or encryption',
        'legible'         => 'All pages properly scanned, oriented and legible',
        'file_naming'     => 'File naming convention followed',
        'metadata'        => 'Title, authors, adviser, program, year and abstract are complete and accurate',
        'keywords'        => 'Keywords and subject information are identifiable',
        'waiver'          => 'Access Permission Waiver accomplished, with the access level clearly indicated',
        'sensitive'       => 'Pages with sensitive personal information identified (set as withheld pages)',
        'preservation'    => 'Suitable for long-term digital preservation',
    ];

    /** Comments are kept to this length, like a rejection reason. */
    public const COMMENT_MAX = 1000;

    /**
     * Each criterion with what the automatic read made of it, the admin's
     * saved decision where there is one, and the status to show.
     *
     * @param array $bluebook a Store::bookToArray()-shaped array
     * @return array<string, array{label: string, auto: string, note: string, saved: ?string, status: string, comment: string, spots: list<array{page: int, label: string}>}>
     */
    public static function items(array $bluebook): array
    {
        $auto  = self::auto($bluebook);
        $spots = self::spots($bluebook);
        $saved = $bluebook['evaluation'] ?? [];

        $items = [];
        foreach (self::CRITERIA as $key => $label) {
            $decision = $saved[$key]['status'] ?? null;
            $items[$key] = [
                'label'   => $label,
                'auto'    => $auto[$key]['status'],
                'note'    => $auto[$key]['note'],
                'saved'   => $decision,
                'status'  => $decision ?? $auto[$key]['status'],
                'comment' => $decision === self::ISSUE ? (string) ($saved[$key]['comment'] ?? '') : '',
                'spots'   => $spots[$key] ?? [],
            ];
        }

        return $items;
    }

    /**
     * The saved criteria the admin marked not okay, with their comments - what
     * the author is told to fix.
     *
     * @return array<string, array{label: string, comment: string}>
     */
    public static function issues(array $bluebook): array
    {
        $issues = [];
        foreach ($bluebook['evaluation'] ?? [] as $key => $decision) {
            if (isset(self::CRITERIA[$key]) && ($decision['status'] ?? null) === self::ISSUE) {
                $issues[$key] = ['label' => self::CRITERIA[$key], 'comment' => (string) ($decision['comment'] ?? '')];
            }
        }

        return $issues;
    }

    /**
     * The admin's form as it is stored: a status for each criterion they
     * marked, and a comment only on those marked not okay.
     */
    public static function fromInput(array $statuses, array $comments): array
    {
        $evaluation = [];
        foreach (array_keys(self::CRITERIA) as $key) {
            $status = $statuses[$key] ?? null;
            if ($status === self::OK) {
                $evaluation[$key] = ['status' => self::OK];
            } elseif ($status === self::ISSUE) {
                $comment = mb_substr(trim((string) ($comments[$key] ?? '')), 0, self::COMMENT_MAX);
                $evaluation[$key] = ['status' => self::ISSUE, 'comment' => $comment === '' ? null : $comment];
            }
        }

        return $evaluation;
    }

    /**
     * What can be told automatically, criterion by criterion.
     *
     * @return array<string, array{status: string, note: string}>
     */
    public static function auto(array $bluebook): array
    {
        $raw  = (string) ($bluebook['ocrText'] ?? '');
        $read = ($bluebook['ocrStatus'] ?? null) === 'completed' && preg_match('/\p{L}/u', $raw);
        $text = $read ? mb_strtolower(preg_replace('/\s+/u', ' ', $raw)) : '';

        $manual = fn(string $note = 'Check this by reading the document.') => self::result(self::UNCHECKED, $note);
        $noText = match ($bluebook['ocrStatus'] ?? null) {
            'failed'     => self::result(self::UNCHECKED, 'The text could not be read, so this was not checked.'),
            'completed'  => self::result(self::UNCHECKED, 'No text was found in the PDF, so this was not checked.'),
            default      => self::result(self::UNCHECKED, 'Waiting for the text to be read.'),
        };
        $fromText = fn(callable $check) => $read ? $check($text) : $noText;

        $results = [
            'approved_unit'  => $fromText(fn($t) => self::approval($t)
                ? self::result(self::OK, 'An approval sheet was found in the text.')
                : self::result(self::ISSUE, 'No approval sheet was found in the text.')),
            'approval_pages' => $fromText(fn($t) => self::approvalPages($t)),
            'format'         => $manual(),
            'pagination'     => $manual(),
            'layout'         => $manual(),
            'toc'            => $fromText(fn($t) => self::has($t, ['table of contents'])
                ? self::result(self::OK, 'A Table of Contents was found. Compare its page numbers with the pages.')
                : self::result(self::ISSUE, 'No Table of Contents was found in the text.')),
            'lists'          => $fromText(fn($t) => self::lists($t)),
            'labels'         => $fromText(fn($t) => self::labels($t)),
            'complete'       => $fromText(fn($t) => self::complete($t)),
            'reproduction'   => $manual('Compare it with the final approved printed copy.'),
            'readable'       => self::readable($bluebook, $read),
            'unencrypted'    => match ($bluebook['pdfEncrypted'] ?? null) {
                true    => self::result(self::ISSUE, 'The PDF is encrypted or restricted.'),
                false   => self::result(self::OK, 'The PDF has no password or restrictions.'),
                default => self::result(self::UNCHECKED, 'Not known until the file is read.'),
            },
            'legible'        => $fromText(fn($t) => self::legible($t, $bluebook)),
            'file_naming'    => $manual('Check the file name' . (!empty($bluebook['fileOriginalName']) ? ' ("' . $bluebook['fileOriginalName'] . '")' : '') . ' against the naming convention.'),
            'metadata'       => self::metadata($bluebook, $text),
            'keywords'       => !empty($bluebook['keywords'])
                ? self::result(self::OK, 'Keywords: ' . implode(', ', $bluebook['keywords']) . '.')
                : ($read && self::has($text, ['keywords:', 'key words:', 'keywords -', 'keywords—'])
                    ? self::result(self::OK, 'Keywords were found in the text, though none were entered on upload.')
                    : self::result(self::ISSUE, 'No keywords were entered or found in the text.')),
            'waiver'         => !empty($bluebook['waiverRecorded'])
                ? self::result(self::OK, 'Recorded: ' . \App\Models\Bluebook::accessName($bluebook['accessLevel'] ?? null) . '.')
                : self::result(self::UNCHECKED, 'Not recorded yet. The signed waiver is handed in to the library.'),
            'sensitive'      => $fromText(fn($t) => self::sensitive($t, $bluebook)),
        ];

        // Readable and unencrypted is what long-term preservation needs of
        // the file; otherwise it is left to the admin.
        $results['preservation'] = $results['readable']['status'] === self::OK && $results['unencrypted']['status'] === self::OK
            ? self::result(self::OK, 'Readable and unencrypted.')
            : ($results['readable']['status'] === self::ISSUE || $results['unencrypted']['status'] === self::ISSUE
                ? self::result(self::ISSUE, 'Not while the file is unreadable or encrypted.')
                : $manual());

        return $results;
    }

    /**
     * Where in the document to look, criterion by criterion: the page a thing
     * was found on, or - when it was not found - the page next to where it
     * belongs, so the admin can open it there instead of paging through.
     *
     * Page numbers are the PDF's own, counted from the form feeds the text
     * keeps between pages; text read before it kept them has none, and no
     * criterion gets a page.
     *
     * @return array<string, list<array{page: int, label: string}>>
     */
    public static function spots(array $bluebook): array
    {
        $raw = (string) ($bluebook['ocrText'] ?? '');
        if (($bluebook['ocrStatus'] ?? null) !== 'completed' || !str_contains($raw, "\f")) {
            return [];
        }

        $pages = array_map(fn($p) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $p))), explode("\f", $raw));

        // Contents and lists name every chapter, table and figure; a heading
        // or caption is looked for in the body first, and on them only if
        // that is the one place it appears. Only the first page of a long
        // contents says so, but every page of it has its dot leaders.
        $lists = array_keys(array_filter($pages, fn($page) =>
            self::has($page, ['table of contents', 'list of tables', 'list of figures', 'list of appendices'])
            || preg_match_all('/(?:\.\s?){5,}\s*(?:\d+|[ivxlc]+)\b/u', $page) >= 2
        ));
        $lists = array_map(fn($i) => $i + 1, $lists);
        $body  = fn($what, int $limit = 1) => self::find($pages, $what, $limit, $lists) ?: self::find($pages, $what, $limit);
        // A chapter or section starts with its heading at the top of a page;
        // "as discussed in Chapter 3" further down is only a mention of it.
        $heading = fn($what) => self::find($pages, $what, 1, $lists, self::HEADING_REACH) ?: $body($what);
        $at    = fn(string $label, array $nums) => array_map(fn($n) => ['page' => $n, 'label' => $label], $nums);

        $title    = $at('Title page', self::find($pages, '/\p{L}/u'));
        $toc      = $at('Table of Contents', self::find($pages, ['table of contents']));
        $approval = $at('Approval sheet', $body(self::APPROVAL));
        $chapter1 = $at('Chapter 1', $heading(self::chapterPattern(1)));
        $abstract = $at('Abstract', $body('/\babstract\b/u'));
        $front    = array_merge($title, $toc);

        $spots = [
            'approved_unit'  => $approval ?: $front,
            'approval_pages' => array_merge($approval, $at('Certification', $body(self::CERTIFICATION))) ?: $front,
            'format'         => array_merge($title, $chapter1),
            'pagination'     => array_merge($toc, $chapter1),
            'layout'         => $chapter1 ?: $title,
            'toc'            => $toc ?: $title,
            'lists'          => array_merge(
                $at('List of Tables', self::find($pages, ['list of tables'])),
                $at('List of Figures', self::find($pages, ['list of figures'])),
                $at('List of Appendices', self::find($pages, ['list of appendices'])),
                $at('Table 1', $body(self::labelPattern('table', 1))),
                $at('Figure 1', $body(self::labelPattern('(?:figure|fig\.)', 1))),
            ),
            'labels'         => self::gapSpots($pages, $body, $at),
            'complete'       => self::chapterSpots($pages, $heading, $at),
            'reproduction'   => $approval,
            'legible'        => ($bluebook['ocrEngine'] ?? null) === 'text layer' ? [] : self::illegiblePages($pages, $at),
            'metadata'       => array_merge($title, $abstract),
            'keywords'       => $at('Keywords', $body(['keywords', 'key words'])) ?: $abstract,
            'waiver'         => $at('Access Permission Waiver', $body(['permission waiver', 'access permission'])),
            // The CV and data sheet by their headings, the details themselves
            // wherever they are - but never the contents line naming them.
            'sensitive'      => array_merge(
                $at('Curriculum vitae', self::find($pages, self::SENSITIVE['curriculum vitae'], 1, $lists, self::HEADING_REACH)),
                $at('Personal data sheet', self::find($pages, self::SENSITIVE['personal data sheet'], 1, $lists, self::HEADING_REACH)),
                ...array_map(
                    fn($name, $pattern) => $at(ucfirst($name), self::find($pages, $pattern, 3, $lists)),
                    array_keys(array_slice(self::SENSITIVE, 2)), array_slice(self::SENSITIVE, 2)
                ),
            ),
        ];

        // Each page once per criterion, in page order, and few enough to read.
        return array_map(function (array $list) {
            $seen = [];
            foreach ($list as $spot) {
                $seen[$spot['page']] ??= $spot;
            }
            ksort($seen);

            return array_slice(array_values($seen), 0, 8);
        }, array_filter($spots));
    }

    /**
     * The pages (1-based) whose text has any of $what - phrases, or a regular
     * expression - in page order, skipping $skip; with $top, only within that
     * many characters of the top of the page, where a heading is.
     *
     * @return list<int>
     */
    private static function find(array $pages, string|array $what, int $limit = 1, array $skip = [], ?int $top = null): array
    {
        $found = [];
        foreach ($pages as $i => $page) {
            if (in_array($i + 1, $skip, true)) {
                continue;
            }
            $page = $top === null ? $page : mb_substr($page, 0, $top);
            if (is_string($what) ? preg_match($what, $page) : self::has($page, $what)) {
                $found[] = $i + 1;
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    private static function labelPattern(string $label, int $n): string
    {
        return '/\b' . $label . '\s+' . $n . '\b(?![.\-–]\d)/u';
    }

    private static function chapterPattern(int $n): string
    {
        $names = array_keys(self::CHAPTER_WORDS, $n, true);

        return '/\bchapter\s+(?:' . implode('|', array_merge([(string) $n], $names)) . ')\b/u';
    }

    /** Either side of each skipped table or figure number: where the missing one should be. */
    private static function gapSpots(array $pages, callable $body, callable $at): array
    {
        $spots = [];
        foreach (['Table' => 'table', 'Figure' => '(?:figure|fig\.)'] as $name => $pattern) {
            $n = self::numbers(implode(' ', $pages), $pattern);
            if (!$n || max($n) > 200) {
                continue;
            }
            foreach (array_diff(range(1, max($n)), $n) as $skipped) {
                $before = max(array_filter($n, fn($x) => $x < $skipped) ?: [0]);
                $after  = min(array_filter($n, fn($x) => $x > $skipped) ?: [0]);
                foreach (array_filter([$before, $after]) as $x) {
                    $spots = array_merge($spots, $at("{$name} {$x} (before {$name} {$skipped})", $body(self::labelPattern($pattern, $x))));
                }
            }
        }

        return $spots;
    }

    /**
     * Where each chapter starts - a missing one lies between its neighbours -
     * and the references, or the last page, where they belong.
     */
    private static function chapterSpots(array $pages, callable $heading, callable $at): array
    {
        $spots = [];
        for ($n = 1; $n <= 7; $n++) {
            $spots = array_merge($spots, $at("Chapter {$n}", $heading(self::chapterPattern($n))));
        }

        $references = $heading(['references', 'bibliography', 'literature cited', 'works cited']);

        return array_merge($spots, $references ? $at('References', $references) : $at('Last page', [count($pages)]));
    }

    /** Scanned pages OCR could make out least of: the ones to look at by eye. */
    private static function illegiblePages(array $pages, callable $at): array
    {
        $poor = [];
        foreach ($pages as $i => $page) {
            $tokens = preg_split('/\s+/u', $page, -1, PREG_SPLIT_NO_EMPTY);
            if (count($tokens) < 20) {
                continue;           // a figure, a divider or a blank page
            }
            $share = count(array_filter($tokens, fn($w) => preg_match('/^\p{L}{2,}[.,;:]?$/u', $w))) / count($tokens);
            if ($share < 0.6) {
                $poor[$i + 1] = $share;
            }
        }
        asort($poor);

        return $at('Hard to read', array_keys(array_slice($poor, 0, 8, true)));
    }

    /**
     * Whether a PDF is encrypted: an /Encrypt entry in its trailer. Read in
     * chunks, with an overlap so an entry split across two is still found.
     */
    public static function isEncrypted(string $path): ?bool
    {
        $in = @fopen($path, 'rb');
        if (!$in) {
            return null;
        }

        $tail = '';
        try {
            while (!feof($in)) {
                $chunk = $tail . fread($in, 1 << 20);
                if (str_contains($chunk, '/Encrypt')) {
                    return true;
                }
                $tail = substr($chunk, -16);
            }
        } finally {
            fclose($in);
        }

        return false;
    }

    private static function result(string $status, string $note): array
    {
        return ['status' => $status, 'note' => $note];
    }

    private static function has(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private const APPROVAL = ['approval sheet', 'approved by', 'accepted and approved', 'approved and accepted', 'recommended for approval', 'recommended for acceptance'];

    private const CERTIFICATION = ['certification', 'certificate', 'certify that', 'certifies that', 'certified by'];

    /** How far down a page, in characters, a heading is looked for: past a page number and a running head. */
    private const HEADING_REACH = 120;

    private static function approval(string $t): bool
    {
        return self::has($t, self::APPROVAL);
    }

    private static function approvalPages(string $t): array
    {
        $missing = [];
        if (!self::approval($t)) {
            $missing[] = 'approval sheet';
        }
        if (!self::has($t, self::CERTIFICATION)) {
            $missing[] = 'certification';
        }

        return $missing
            ? self::result(self::ISSUE, 'Not found in the text: ' . implode(', ', $missing) . '.')
            : self::result(self::OK, 'Approval and certification pages were found in the text.');
    }

    /** The whole numbers a label is used with: "Table 1", "Figure 12" - not "Table 3.1". */
    private static function numbers(string $t, string $label): array
    {
        preg_match_all('/\b' . $label . '\s+(\d{1,3})\b(?![.\-–]\d)/u', $t, $m);
        $n = array_unique(array_map('intval', $m[1]));
        sort($n);

        return array_values(array_filter($n, fn($x) => $x >= 1));
    }

    private static function lists(string $t): array
    {
        $missing = [];
        if (self::numbers($t, 'table') && !self::has($t, ['list of tables'])) {
            $missing[] = 'List of Tables';
        }
        if (self::numbers($t, '(?:figure|fig\.)') && !self::has($t, ['list of figures'])) {
            $missing[] = 'List of Figures';
        }
        if (preg_match('/\bappendix\s+[a-z0-9]\b/u', $t) && !self::has($t, ['list of appendices', 'appendices'])) {
            $missing[] = 'List of Appendices';
        }

        return $missing
            ? self::result(self::ISSUE, 'The manuscript has them, but no ' . implode(', ', $missing) . ' was found.')
            : self::result(self::OK, 'Each kind of table, figure or appendix used has its list.');
    }

    private static function labels(string $t): array
    {
        $gaps = [];
        foreach (['Table' => 'table', 'Figure' => '(?:figure|fig\.)'] as $name => $pattern) {
            $n = self::numbers($t, $pattern);
            if (!$n || max($n) > 200) {
                continue;
            }
            foreach (array_diff(range(1, max($n)), $n) as $skipped) {
                $gaps[] = "{$name} {$skipped}";
            }
        }

        return $gaps
            ? self::result(self::ISSUE, 'The numbering skips ' . implode(', ', array_slice($gaps, 0, 10)) . (count($gaps) > 10 ? ' and more' : '') . '.')
            : self::result(self::OK, 'Tables and figures are numbered without gaps.');
    }

    private const CHAPTER_WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7];

    private static function complete(string $t): array
    {
        $missing = [];
        foreach (['abstract' => ['abstract'], 'table of contents' => ['table of contents'],
                  'references' => ['references', 'bibliography', 'literature cited', 'works cited']] as $section => $phrases) {
            if (!self::has($t, $phrases)) {
                $missing[] = $section;
            }
        }

        preg_match_all('/\bchapter\s+(\d|one|two|three|four|five|six|seven|vii|vi|iv|v|iii|ii|i)\b/u', $t, $m);
        $chapters = array_unique(array_map(fn($c) => ctype_digit($c) ? (int) $c : self::CHAPTER_WORDS[$c], $m[1]));
        if (!$chapters) {
            $missing[] = 'chapters';
        } else {
            foreach (array_diff(range(1, max($chapters)), $chapters) as $skipped) {
                $missing[] = "Chapter {$skipped}";
            }
        }

        return $missing
            ? self::result(self::ISSUE, 'Not found in the text: ' . implode(', ', $missing) . '.')
            : self::result(self::OK, 'Abstract, contents, chapters 1–' . max($chapters) . ' and references were found.');
    }

    private static function readable(array $b, bool $read): array
    {
        if (empty($b['hasFile'])) {
            return self::result(self::ISSUE, 'No PDF is attached.');
        }

        return match (true) {
            $read                              => self::result(self::OK, 'The PDF opened and its text was read.'),
            ($b['ocrStatus'] ?? null) === 'failed' => self::result(self::ISSUE, 'The PDF could not be read' . (!empty($b['ocrError']) ? ': ' . mb_substr($b['ocrError'], 0, 200) : '.')),
            default                            => self::result(self::UNCHECKED, 'Waiting for the text to be read.'),
        };
    }

    /** Scans read by OCR: little of the text being words means blurred, skewed or upside-down pages. */
    private static function legible(string $t, array $b): array
    {
        if (($b['ocrEngine'] ?? null) === 'text layer') {
            return self::result(self::OK, 'Exported straight to PDF, so its text is exact. Check any scanned pages by eye.');
        }

        $tokens = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
        $words  = count(array_filter($tokens, fn($w) => preg_match('/^\p{L}{2,}[.,;:]?$/u', $w)));
        $share  = $tokens ? $words / count($tokens) : 0;

        return $share >= 0.6
            ? self::result(self::OK, 'OCR read the scanned pages clearly (' . round($share * 100) . '% words).')
            : self::result(self::ISSUE, 'OCR could make out only ' . round($share * 100) . '% of the scan as words; pages may be blurred, skewed or upside down.');
    }

    private static function metadata(array $b, string $t): array
    {
        $missing = [];
        foreach (['title' => 'title', 'authors' => 'authors', 'adviser' => 'adviser', 'program' => 'program', 'year' => 'year', 'abstract' => 'abstract'] as $field => $name) {
            if (empty($b[$field])) {
                $missing[] = $name;
            }
        }
        if ($missing) {
            return self::result(self::ISSUE, 'Missing: ' . implode(', ', $missing) . '.');
        }
        if ($t === '') {
            return self::result(self::UNCHECKED, 'Complete; not yet compared with the text.');
        }

        $words = fn(string $s) => array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)), fn($w) => mb_strlen($w) > 2);
        $found = fn(array $ws) => $ws ? count(array_filter($ws, fn($w) => str_contains($t, $w))) / count($ws) : 1;

        $notInText = [];
        if ($found($words($b['title'])) < 0.8) {
            $notInText[] = 'the title';
        }
        foreach ((array) $b['authors'] as $author) {
            // The surname: before the comma in "Dela Cruz, Maria", else the last word.
            $names   = preg_split('/\s+/', trim($author));
            $surname = str_contains($author, ',') ? strstr($author, ',', true) : end($names);
            if ($found($words($surname)) < 1) {
                $notInText[] = 'author ' . trim($author);
            }
        }

        return $notInText
            ? self::result(self::ISSUE, 'Entered, but not found in the text: ' . implode(', ', $notInText) . '.')
            : self::result(self::OK, 'Complete, and the title and authors match the text.');
    }

    private const SENSITIVE = [
        'curriculum vitae'     => '/\bcurriculum vitae\b/u',
        'personal data sheet'  => '/\bpersonal (?:data|information) sheet\b/u',
        'date of birth'        => '/\b(?:date of birth|birth ?date|birthday)\b/u',
        'phone number'         => '/(?:\+63|\b0)9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}\b/u',
        'home address'         => '/\b(?:home|permanent|residential) address\b/u',
        'student/ID number'    => '/\b(?:student (?:no|number)|id (?:no|number))\b/u',
    ];

    private static function sensitive(string $t, array $b): array
    {
        $found = [];
        foreach (self::SENSITIVE as $name => $pattern) {
            if (preg_match($pattern, $t)) {
                $found[] = $name;
            }
        }

        if (!$found) {
            return self::result(self::OK, 'No personal details were found in the text.');
        }

        return !empty($b['withheldPages'])
            ? self::result(self::OK, 'Found ' . implode(', ', $found) . '; pages ' . $b['withheldPages'] . ' are withheld.')
            : self::result(self::ISSUE, 'Found ' . implode(', ', $found) . ' in the text, but no pages are set as withheld.');
    }
}
