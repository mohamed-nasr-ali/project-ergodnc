<?php

namespace App\Http\Controllers;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', OfficeApprovalStatus::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('host_id'), fn(Builder $builder) => $builder->whereUserId(request('host_id')))
            ->when(
                request('user_id'),
                fn(Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('user_id'))
            )
            ->when(
                request('lng') && request('lng'),
                fn(Builder $builder) => $builder->nearestTo(request('lng'), request('lng')),
                fn(Builder $builder) => $builder->oldest('id')
            )
            ->with(['tags', 'images', 'user'])
            ->withCount(
                ['reservations' => fn(Builder $builder) => $builder->where('status', ReservationStatus::STATUS_ACTIVE)]
            )
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office): OfficeResource
    {
        $office->loadCount(['reservations' => fn(Builder $builder) => $builder->where('status', ReservationStatus::STATUS_ACTIVE)])
            ->load(['tags', 'images', 'user']);

        return OfficeResource::make($office);
    }
}
