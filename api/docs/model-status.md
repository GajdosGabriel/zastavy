# Model status

Status modelov je ulozeny v stlpci `status` a v PHP sa cita ako enum `App\Enums\ModelStatus`.

## Defaulty

- `users`: `active`
- `orders`: `draft`
- `products`: `active`
- ostatne domenove modely: `active`

Registracia pouzivatela nemusi status nastavovat rucne. Databazovy default vytvori noveho pouzivatela ako `active`.

Objednavka zacina ako `draft`. Pri vytvoreni akejkolvek dodavky/expedicie (`Shipping`) sa objednavka automaticky prepne na `archived`.

## Pouzitie v modeloch

Modely pouzivaju trait `App\Traits\HasModelStatus`.

```php
use App\Enums\ModelStatus;

$product->status; // ModelStatus::Active
$product->isArchived(); // true pre archived/discontinued
$product->archive(); // nastavi status na archived
$product->activate(); // nastavi status na active

Product::withStatus(ModelStatus::Active)->get();
```

Pri hromadnom vytvarani alebo update pouzivaj enum, nie volny text:

```php
$order->update([
    'status' => ModelStatus::Archived,
]);
```

## API vystup

Resource moze vratit status cez:

```php
'status' => $this->statusData(),
```

Vystup ma tvar:

```json
{
  "value": "active",
  "label": "Aktivny",
  "color": "green"
}
```

Produkty a objednavky maju este doplnkovu logiku:

- produkt so statusom inym ako `active` vrati ulozeny status,
- aktivny produkt s `quantity <= 0` sa zobrazi ako `out_of_stock`,
- archivovana objednavka sa zobrazi ako `archived`,
- nearchivovana objednavka bez expedicie zostava `draft`.

## Mazanie

Archivovany model sa nema mazat. Policy pravidlo preto musi pred povolenim `delete` kontrolovat:

```php
return ! $model->isArchived();
```

Ak model uz ma vlastne podmienky mazania, kontrola archivacie sa pridava k nim:

```php
return ! $product->isArchived() && $product->getOrderProductsCount() == 0;
```

Pre controllery volaj pred zmazanim policy:

```php
Gate::authorize('delete', $product);
$product->delete();
```

## Pridanie statusu do noveho modelu

1. Pridaj stlpec do migracie:

```php
$table->string('status', 32)
    ->default(ModelStatus::Active->value)
    ->index();
```

2. V modeli pridaj trait a cast:

```php
use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;

use HasModelStatus;

protected $casts = [
    'status' => ModelStatus::class,
];
```

3. V resource vrat `statusData()`.
4. V policy nepovol mazanie archivovaneho modelu.
