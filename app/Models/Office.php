<?php

namespace App\Models;

use App\Enums\OfficeApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable=[
        'user_id',
        'title',
        'description',
        'lat',
        'lng',
        'address_line1',
        'address_line2',
        'approval_status',
        'hidden',
        'price_per_day',
        'monthly_discount',
        'featured_image_id'
    ];
    protected $casts=[
        'lat'=>'decimal:8',
        'lng'=>'decimal:8',
        'approval_status'=>OfficeApprovalStatus::class,
        'hidden'=>'bool',
        'price_per_day'=>'integer',
        'monthly_discount'=>'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'resource');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Image::class,'featured_image_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class ,'offices_tags');
    }


    public function scopeNearestTo(Builder $builder, $lat, $lng): Builder
    {
        return $builder
            ->select()
            ->orderByRaw(
                'POW(69.1 * (lat - ?), 2) + POW(69.1 * (? - lng) * COS(lat / 57.3), 2)',
                [$lat, $lng]
            );
    }
}
