<?php

namespace App\Models;

use App\Models\Mark;
use App\Casts\IcoFormater;
use App\Casts\DicFormater;
use Illuminate\Support\Str;
use App\Casts\PhoneFormater;
use App\Casts\DateTimeFormater;
use App\Casts\PostCodeFormater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory, SoftDeletes, Notifiable;

    protected $guarded = ['id','created_at'];
    protected $appends = ['ordersCount'];

    protected $casts = [
        'ico'           => IcoFormater::class,
        'dic'           => DicFormater::class,
        'postcode'      => PostCodeFormater::class,
        'phone'         => PhoneFormater::class,
        'created_at'    => DateTimeFormater::class,
    ];

    public function orders()
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function primaryUser()
    {
        return $this->hasOne(User::class)->oldestOfMany();
    }

    public function latestUser()
    {
        return $this->hasOne(User::class)->latestOfMany();
    }

    public function mark()
    {
        return $this->morphOne(Mark::class, 'fileable');
    }


    public function setNameAttribute($value)
    {
        $this->attributes['name'] =  $value;
        $this->attributes['slug'] =  Str::slug($value, '-');
    }

    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
