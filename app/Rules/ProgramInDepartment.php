<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * The program must be one config('departments') lists under the chosen
 * department, so a bluebook cannot be filed as, say, a CCS paper in Nursing.
 *
 * $keep is the program already on a bluebook being edited: a record filed
 * under a program the list no longer carries can still be saved as it is.
 */
class ProgramInDepartment implements Rule
{
    public function __construct(private ?string $department, private ?string $keep = null)
    {
    }

    public function passes($attribute, $value): bool
    {
        if ($this->keep !== null && $value === $this->keep) {
            return true;
        }

        $programs = config('departments')[$this->department]['programs'] ?? [];

        return in_array($value, $programs, true);
    }

    public function message(): string
    {
        return 'Please choose a program offered by the selected department.';
    }
}
