<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserReservationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(request('office_id'), fn(Builder $builder, $officeId) => $builder->where('office_id', $officeId))
            ->when(request('status'), fn(Builder $builder, $status) => $builder->where('status', $status))
            ->when(
                request('from_date') && request('to_date'),
                fn(Builder $builder) => $builder->where(function (Builder $builder) {
                    $builder->whereBetween('start_date', [request('from_date'), request('to_date')])
                        ->orWhereBetween('end_date', [request('from_date'), request('to_date')]);
                })
            )->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }
}
