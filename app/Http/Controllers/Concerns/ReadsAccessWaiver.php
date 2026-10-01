<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Bluebook;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The access permission waiver, as the admin records it on the edit form from
 * the signed copy the author hands in to the library.
 */
trait ReadsAccessWaiver
{
    /**
     * $allowLegacy: whether "no waiver on file" may be recorded - only for a
     * record the library added itself. A student's submission cannot be posted
     * without its waiver (Library Manual 5.2.1, grounds for non-acceptance).
     */
    private function accessWaiverRules(bool $allowLegacy = false): array
    {
        $levels = array_keys(Bluebook::ACCESS_LEVELS);
        if ($allowLegacy) {
            $levels[] = Bluebook::ACCESS_LEGACY;
        }

        return [
            'access_level'   => ['required', 'in:' . implode(',', $levels)],
            'withheld_pages' => ['nullable', 'string', 'max:255'],
            'access_parts'   => ['required_if:access_level,' . Bluebook::ACCESS_PARTIAL, 'array'],
            'access_parts.*' => ['in:' . implode(',', array_keys(Bluebook::ACCESS_PARTS))],
        ];
    }

    private function accessWaiverMessages(): array
    {
        return [
            'access_level.required'    => 'Please choose an access permission waiver.',
            'access_parts.required_if' => 'Please tick at least one part that readers may see.',
            'access_level.in'          => 'A student submission needs the access level from the author\'s signed waiver.',
        ];
    }

    /**
     * The pages no reader is sent - CV, contact details, signatures, ID
     * numbers (Library Manual 4.3.1.3) - as a normalized page list, or null.
     */
    private function withheldPagesFrom(Request $request): ?string
    {
        $list = Bluebook::normalizePageList($request->input('withheld_pages'));
        if ($list === false) {
            throw ValidationException::withMessages([
                'withheld_pages' => 'Write the withheld pages as page numbers and ranges, such as "3, 148-152".',
            ]);
        }

        $pages = (int) $request->input('pages');
        if ($list !== null && $pages > 0) {
            foreach (explode(',', $list) as $part) {
                if ((int) (explode('-', $part)[1] ?? $part) > $pages) {
                    throw ValidationException::withMessages([
                        'withheld_pages' => "The withheld pages must fall within pages 1 to {$pages}.",
                    ]);
                }
            }
        }

        return $list;
    }

    /**
     * The parts a partial waiver opens, each with the pages it occupies.
     *
     * The page range is what the waiver is enforced by - the served copy holds
     * those pages and no others - so a part ticked without a usable range is an
     * error rather than something to guess at.
     *
     * @return array<string, array{from: int, to: int}>|null
     */
    private function accessPartsFrom(Request $request): ?array
    {
        if ($request->input('access_level') !== Bluebook::ACCESS_PARTIAL) {
            return null;
        }

        $pages  = (int) $request->input('pages');
        $from   = (array) $request->input('part_from', []);
        $to     = (array) $request->input('part_to', []);
        $parts  = [];

        foreach (array_keys(Bluebook::ACCESS_PARTS) as $key) {
            if (!in_array($key, (array) $request->input('access_parts', []), true)) {
                continue;
            }

            $label = Bluebook::ACCESS_PARTS[$key];
            $start = filter_var($from[$key] ?? null, FILTER_VALIDATE_INT);
            $end   = filter_var($to[$key] ?? null, FILTER_VALIDATE_INT);

            if ($start === false || $end === false) {
                throw ValidationException::withMessages([
                    'access_parts' => "Please enter the page range for {$label}.",
                ]);
            }
            if ($start < 1 || $end < $start || $end > $pages) {
                throw ValidationException::withMessages([
                    'access_parts' => "The page range for {$label} must run forward and fall within pages 1 to {$pages}.",
                ]);
            }

            $parts[$key] = ['from' => $start, 'to' => $end];
        }

        return $parts;
    }
}
