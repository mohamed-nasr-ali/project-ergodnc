<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable=['user_id','office_id','price','status','start_date','end_date','wifi_password'];
    protected $casts = [
        'user_id'=>'integer',
        'price' => 'integer',
        'status' => ReservationStatus::class,
        'start_date' => 'immutable_date',
        'end_date' => 'immutable_date',
        'wifi_password'=>'encrypted'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /*Scopes*/
    public function scopeBetweenDates(Builder $query, $from, $to):void
    {
        $query->where(function (Builder $builder) use ($from, $to) {
            $builder->whereBetween('start_date', [$from, $to])
                ->orWhereBetween('end_date', [$from, $to])
                ->orWhere(function ($query) use ($to, $from) {
                    $query->where('start_date', '<', $from)
                        ->where('end_date', '>', $to);
                });
        });
    }

    public function scopeActiveBetween(Builder $query,$from,$to):void
    {
        $query->whereStatus(ReservationStatus::STATUS_ACTIVE)
              ->betweenDates($from,$to);
    }
}
