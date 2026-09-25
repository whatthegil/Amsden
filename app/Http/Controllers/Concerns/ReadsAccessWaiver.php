<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Bluebook;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The access permission waiver, as submitted by the author on upload and
 * confirmed by the admin on the edit form. Both read it the same way.
 */
trait ReadsAccessWaiver
{
    private function accessWaiverRules(): array
    {
        return [
            'access_level'   => ['required', 'in:' . implode(',', array_keys(Bluebook::ACCESS_LEVELS))],
            'access_parts'   => ['required_if:access_level,' . Bluebook::ACCESS_PARTIAL, 'array'],
            'access_parts.*' => ['in:' . implode(',', array_keys(Bluebook::ACCESS_PARTS))],
        ];
    }

    private function accessWaiverMessages(): array
    {
        return [
            'access_level.required'    => 'Please choose an access permission waiver.',
            'access_parts.required_if' => 'Please tick at least one part that readers may see.',
        ];
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
