<?php

namespace Tests\Support;

use App\Services\BluebookEvaluation;

/**
 * The evaluation checklist as the Reject form posts it: every criterion marked,
 * okay unless named in $issues, which maps criterion => comment.
 */
class Checklist
{
    public static function rejecting(string $reason, array $issues = ['complete' => 'Chapter 3 is missing.']): array
    {
        $status = array_fill_keys(array_keys(BluebookEvaluation::CRITERIA), BluebookEvaluation::OK);
        foreach (array_keys($issues) as $key) {
            $status[$key] = BluebookEvaluation::ISSUE;
        }

        return ['status' => $status, 'comment' => $issues, 'reason' => $reason];
    }
}
