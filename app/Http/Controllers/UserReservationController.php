<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        validator(request()->all(), [
            'status' => Rule::in(collect(ReservationStatus::cases())->pluck('value')->toArray()),
            'office_id' => ['integer'],
            'from_date' => ['date', 'required_with:to_date'],
            'to_date' => ['date', 'required_with:from_date', 'after:from_date']
        ])->validate();

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(request('office_id'), fn(Builder $builder, $officeId) => $builder->where('office_id', $officeId))
            ->when(request('status'), fn(Builder $builder, $status) => $builder->where('status', $status))
            ->when(
                request('from_date') && request('to_date'),
                fn(Builder $builder) => $builder->betweenDates(request('from_date'), request('to_date'))
            )->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    /**
     * @throws \Throwable
     */
    public function create()
    {
        validator(
            request()->all(),
            [
                'office_id' => [
                    'required',
                    'integer',
                    Rule::exists(Office::class, 'id')->whereNotIn('user_id', [auth()->id()])
                ],
                'start_date' => [
                    'required',
                    'date:Y-m-d',
                    'before:end_date',
                    'after:today'
                ],
                'end_date' => ['required', 'date:Y-m-d', 'after:start_date'],
            ], [
                'office_id.exists' => 'invalid office!'
            ]
        )->validate();

        $office = Office::query()->find(request('office_id'));

        $reservation = Cache::lock('reservations_office_'.$office->id, 10)->block(3, function () use ($office) {
            throw_if(
                $office->reservations()->activeBetween(request('start_date'), request('end_date'))->exists(),
                ValidationException::withMessages(['start_date' => 'You Cannot Make a Reservation During This Time'])
            );

            $numberOfDays = Carbon::parse(request('end_date'))->endOfDay()
                ->diffInDays(Carbon::parse(request('start_date'))->startOfDay()) + 1;

            throw_if(
                $numberOfDays < 2,
                ValidationException::withMessages(['start_date' => 'You Cannot Make a Reservation For Only one day'])
            );

            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 && $monthly_discount = $office->monthly_discount) {
                $price -= ($price * $monthly_discount / 100);
            }

            return Reservation::create([
                                           'user_id' => auth()->id(),
                                           'office_id' => $office->id,
                                           'start_date' => request('start_date'),
                                           'end_date' => request('end_date'),
                                           'status' => ReservationStatus::STATUS_ACTIVE,
                                           'price' => $price
                                       ]);
        });

        return ReservationResource::make($reservation->load('office'));
    }
}
