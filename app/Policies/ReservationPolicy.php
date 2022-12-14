<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationPolicy
{
    use HandlesAuthorization;

    public function cancel(User $user,Reservation $reservation): bool
    {
//        dd(  // cancel reservation that is  belongs to you
//            $reservation->user()->is($user) &&
//            // cancel reservation that active only
//            $reservation->status === ReservationStatus::STATUS_ACTIVE &&
//            // cancel reservation that has a start date in the future
//            $reservation->start_date > now()->toDateString());
        return
            // cancel reservation that is  belongs to you
            $reservation->user()->is($user) &&
            // cancel reservation that active only
            $reservation->status === ReservationStatus::STATUS_ACTIVE &&
            // cancel reservation that has a start date in the future
            $reservation->start_date > now()->toDateString();
    }
}
