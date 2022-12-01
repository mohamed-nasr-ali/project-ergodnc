<?php

namespace App\Http\Controllers;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', OfficeApprovalStatus::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn(Builder $builder, $userId) => $builder->whereUserId($userId))
            ->when(
                request('visitor_id'),
                fn(Builder $builder, $visitorId) => $builder->whereRelation('reservations', 'user_id', '=', $visitorId)
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
        $office->loadCount(
            ['reservations' => fn(Builder $builder) => $builder->where('status', ReservationStatus::STATUS_ACTIVE)]
        )
            ->load(['tags', 'images', 'user']);

        return OfficeResource::make($office);
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function create(): JsonResource
    {
        $attributes = (new OfficeValidator())->validate($office = new Office(), request()->all());

        $attributes['approval_status'] = OfficeApprovalStatus::APPROVAL_PENDING;
        $attributes['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($attributes, $office) {
            $office->fill(Arr::except($attributes, ['tags']))->save();

            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });

        Notification::send(User::query()->firstWhere('name','Mohamed'),new OfficePendingApproval($office));

        return OfficeResource::make($office);
    }

    public function update(Office $office): OfficeResource
    {
        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate($office, request()->all());

        $office->fill(Arr::except($attributes, ['tags']));

        if ($requireReview=$office->isDirty(['lat', 'lng', 'price'])) {
            $office->fill(['approval_status' => OfficeApprovalStatus::APPROVAL_PENDING]);
        }

        DB::transaction(function () use ($office, $attributes) {
            $office->save();

            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        if ($requireReview){
            Notification::send(User::query()->firstWhere('name','Mohamed'),new OfficePendingApproval($office));
        }

        return OfficeResource::make($office->load(['images', 'tags', 'user']));
    }

    /**
     *
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function destroy(Office $office): void
    {
        $this->authorize('destroy',$office);

        throw_if($office->reservations()->where('status',ReservationStatus::STATUS_ACTIVE)->exists(),
             ValidationException::withMessages(['office'=>'cannot delete this office']));

        $office->delete();
    }
}
