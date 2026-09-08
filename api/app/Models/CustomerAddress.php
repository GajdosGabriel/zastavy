<?php

namespace App\Models;

use App\Support\AddressFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uložená doručovacia adresa zákazníka.
 *
 * Adresár, nie história: riadok sa smie prepísať a objednávkam sa tým nič
 * nezmení — tie majú vlastný odtlačok adresy.
 */
class CustomerAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'created_at'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Hodnoty tak, ako ich objednávka zapíše do svojich `delivery_*` stĺpcov. */
    public function toSnapshot(): array
    {
        return [
            'delivery_company'  => $this->company,
            'delivery_name'     => $this->name,
            'delivery_street'   => $this->street,
            'delivery_postcode' => $this->postcode,
            'delivery_city'     => $this->city,
            'delivery_country'  => $this->country ?: 'SK',
            'delivery_phone'    => $this->phone,
            'delivery_note'     => $this->note,
        ];
    }

    /** Odtlačok na porovnanie s inou adresou — viď AddressFormatter::fingerprint(). */
    public function fingerprint(): string
    {
        return AddressFormatter::fingerprint(
            $this->company,
            $this->street,
            $this->postcode,
            $this->city,
            $this->country ?: 'SK',
        );
    }

    /** Jednoriadkový zápis do zoznamov a selectov. */
    public function getSummaryAttribute(): string
    {
        return collect([
            $this->company,
            $this->street,
            trim(AddressFormatter::formatPostcode($this->postcode).' '.$this->city),
        ])->filter()->implode(', ');
    }
}
