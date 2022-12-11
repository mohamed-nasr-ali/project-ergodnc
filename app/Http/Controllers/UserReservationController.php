<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserReservationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        validator(request()->all(),[
            'status'=>Rule::in(collect(ReservationStatus::cases())->pluck('value')->toArray()),
            'office_id'=>['integer'],
            'from_date'=>['date','required_with:to_date'],
            'to_date'=>['date','required_with:from_date','after:from_date']
        ])->validate();

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
