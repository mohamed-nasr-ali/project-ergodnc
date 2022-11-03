<?php

namespace App\Http\Controllers;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', OfficeApprovalStatus::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn(Builder $builder) => $builder->whereUserId(request('user_id')))
            ->when(
                request('visitor_id'),
                fn(Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn(Builder $builder) => $builder->nearestTo(request('lat'), request('lng')),
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

    public function create():JsonResource
    {
        $attributes=validator(request()->all(),[
            'title'=>['required','string'],
            'description'=>['required','string'],
            'lat'=>['required','numeric'],
            'lng'=>['required','numeric'],
            'address_line1'=>['required','string'],
            'hidden'=>['bool'],
            'price_per_day'=>['required','integer','min:100'],
            'monthly_discount'=>['required','integer','min:0'],
            'tags'=>['array'],
            'tags.*'=>['integer',Rule::exists(Tag::class,'id')]
        ])->validate();

        $attributes['approval_status']=OfficeApprovalStatus::APPROVAL_PENDING;

        $office=auth()->user()->offices()->create(Arr::except($attributes, ['tags']));

        $office->tags()->sync($attributes['tags']);

        return OfficeResource::make($office);
    }
}
