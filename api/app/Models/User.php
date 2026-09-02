<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ModelStatus;
use App\Notifications\ResetPassword;
use App\Traits\HasModelStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasModelStatus, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'uuid',
        'prefix',
        'firstName',
        'lastName',
        'postfix',
        'position',
        'slug',
        'username',
        'email',
        'phone',
        'locale',
        'note',
        'customer_id',
        'password',
        'status',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'login_count' => 'integer',
        'status' => ModelStatus::class,
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->uuid) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Meno bez titulov — základ pre username a slug.
     */
    public function plainName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    /**
     * Meno s titulmi: "Ing. Ján Novák, PhD."
     */
    public function fullName(): string
    {
        $name = trim(($this->prefix ? $this->prefix . ' ' : '') . $this->plainName());

        return $this->postfix ? $name . ', ' . $this->postfix : $name;
    }

    /**
     * Účet je použiteľný, len ak nie je vypnutý a status prístup nezakazuje.
     */
    public function isActive(): bool
    {
        return (bool) $this->active
            && ! in_array($this->status, [ModelStatus::Blocked, ModelStatus::Cancelled, ModelStatus::Archived], true);
    }

    /**
     * Jazyk notifikácií — Laravel ho použije pri posielaní mailov.
     */
    public function preferredLocale(): ?string
    {
        return in_array($this->locale, config('app.supported_locales', []), true)
            ? $this->locale
            : null;
    }

    /**
     * Zaznamená úspešné prihlásenie — bez dotknutia updated_at,
     * aby sa prihlásenie netvárilo ako úprava účtu.
     */
    public function recordLogin(?string $ip = null): void
    {
        static::withoutTimestamps(fn () => $this->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'login_count'   => ($this->login_count ?? 0) + 1,
        ])->saveQuietly());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrder()
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }

    /**
     * Sanctum tokeny — každé prihlásenie cez API vytvára nový,
     * takže sú zdrojom údaju o poslednej aktivite.
     */
    public function lastUsedToken()
    {
        return $this->hasOne(\Laravel\Sanctum\PersonalAccessToken::class, 'tokenable_id')
            ->where('tokenable_type', static::class)
            ->ofMany('last_used_at', 'max');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    public function setUsernameAttribute($value)
    {
        $this->attributes['username'] = $value;

        if ($value && empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value, '-');
        }
    }
}
