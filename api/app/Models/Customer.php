<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Models\Mark;
use App\Casts\IcoFormater;
use App\Casts\DicFormater;
use Illuminate\Support\Str;
use App\Casts\PhoneFormater;
use App\Casts\DateTimeFormater;
use App\Casts\PostCodeFormater;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory, SoftDeletes, Notifiable, HasModelStatus;

    protected $guarded = ['id','created_at'];
    protected $appends = ['ordersCount'];

    protected $casts = [
        'ico'           => IcoFormater::class,
        'dic'           => DicFormater::class,
        'postcode'      => PostCodeFormater::class,
        'phone'         => PhoneFormater::class,
        'created_at'    => DateTimeFormater::class,
        'status'        => ModelStatus::class,
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

    /** Posudok údajov z post-kontroly — jeden na zákazníka, prepisuje sa. */
    public function review()
    {
        return $this->hasOne(CustomerReview::class);
    }


    /**
     * Meno kontaktnej osoby.
     *
     * Nie je to stĺpec — býval, a znamenal raz meno človeka, raz názov firmy.
     * Jediné miesto, kde meno kontaktu žije, je `users.username`; tento
     * accessor ho odtiaľ podáva, aby `$customer->name` čítalo ako predtým.
     *
     * Kto ho číta v zozname, nech si reláciu načíta dopredu (`with('primaryUser')`),
     * inak je to dotaz na každý riadok.
     */
    public function getNameAttribute(): ?string
    {
        $contact = $this->primaryUser ?? $this->latestUser;

        return $contact?->username;
    }

    /**
     * Slug visel na mene; odkedy meno nie je stĺpec, drží ho názov firmy.
     * `company` je povinné pri vytvorení aj pri úprave zákazníka, takže slug
     * nikdy nezostane prázdny.
     */
    public function setCompanyAttribute($value)
    {
        $this->attributes['company'] = $value;

        $slug = Str::slug((string) $value, '-');

        if ($slug !== '') {
            $this->attributes['slug'] = $slug;

            return;
        }

        // Stĺpec je NOT NULL, takže prázdno tu skončí SQL chybou pri vložení.
        // Existujúci slug prázdna firma neprepíše — len nový riadok dostane
        // náhradu.
        if (($this->attributes['slug'] ?? '') === '') {
            $this->attributes['slug'] = 'zakaznik';
        }
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
