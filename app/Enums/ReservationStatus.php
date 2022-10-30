<?php
namespace App\Enums;

enum ReservationStatus:int{
  case STATUS_ACTIVE=1;
  case STATUS_CANCELLED=2;

    public function status(): int
    {
        return match($this)
        {
            self::STATUS_ACTIVE => 1,
            self::STATUS_CANCELLED => 2
        };
    }
}
