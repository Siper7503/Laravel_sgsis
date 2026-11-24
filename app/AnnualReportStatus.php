<?php

namespace App;

enum AnnualReportStatus: string
{
    case Draft = 'draft';
    case SubmittedToDse = 'submitted_to_dse';
    case ReturnedToAgent = 'returned_to_agent';
    case SubmittedToDgss = 'submitted_to_dgss';
    case ReturnedToDse = 'returned_to_dse';
    case Approved = 'approved';

    public function canBeEditedByAgent(): bool
    {
        return in_array($this, [self::Draft, self::ReturnedToAgent], true);
    }

    public function canBeReviewedByDse(): bool
    {
        return in_array($this, [self::SubmittedToDse, self::ReturnedToDse], true);
    }

    public function canBeReviewedByDgss(): bool
    {
        return $this === self::SubmittedToDgss;
    }
}
