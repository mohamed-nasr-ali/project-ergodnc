<?php
namespace App\Enums;

enum OfficeApprovalStatus:int{
    case APPROVAL_PENDING=1;
    case APPROVAL_APPROVED=2;

    public function status(): int
    {
        return match($this)
        {
            self::APPROVAL_PENDING => 1,
            self::APPROVAL_APPROVED => 2
        };
    }
}
