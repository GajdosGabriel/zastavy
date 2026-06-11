# 🧠 ROLE
Si senior Laravel developer so skúsenosťami s e-commerce architektúrou.
Tvoj cieľ je navrhnúť čisté, škálovateľné riešenie.

---

# 📦 PROJECT
Vytváram e-shop v Laraveli.

Potrebujem implementovať:
- dopravu (shipping)
- platby (payment methods)
- zľavy (discounts)
- kupóny (coupon codes)
- je možné že niektorý model už mám, ak sa bude dať do toho implementovať, sprav to
- sprav frontent a backend
- sprav to ako component, lebo je možné že to budeme implementovať na viac stránkach

---

# ⚙️ REQUIREMENTS

## 🚚 Shipping (doprava)

- doprava NIE JE produkt
- je samostatná entita (ShippingMethod)
- order si musí ukladať:
  - shipping_method_id
  - shipping_price

### Shipping pravidlá:
- fixná cena (napr. 4.99 €)
- zdarma nad určitú sumu (napr. nad 50 €)
- viac dopravcov (Packeta, DPD, osobný odber)

---

## 💳 Payment (platby)

- platba je samostatná entita (PaymentMethod)
- order si ukladá:
  - payment_method_id
  - payment_fee

### Payment pravidlá:
- niektoré majú poplatok (dobierka +1.5 €)
- typy:
  - card
  - bank_transfer
  - cash_on_delivery

---

## 🏷️ Discounts (zľavy)

- zľavy môžu byť:
  - percentuálne (%)
  - fixná suma (€)

- môžu byť aplikované:
  - na celý order
  - na konkrétny produkt (voliteľné)

---

## 🎟️ Coupons (kupóny)

- kupón má kód (napr. SUMMER10)
- môže obsahovať:
  - percentuálnu alebo fixnú zľavu
  - minimálnu hodnotu objednávky
  - dátum platnosti
  - limit použitia

### Pravidlá:
- kupón možno použiť len ak spĺňa podmienky
- hodnota zľavy sa uloží do orderu (history)

---

# 🧱 DATABASE STRUCTURE

## ShippingMethod
- id
- name
- price
- free_from_price (nullable)
- active

## PaymentMethod
- id
- name
- fee
- type
- active

## Coupon
- id
- code
- type (percent | fixed)
- value
- min_order_price (nullable)
- usage_limit (nullable)
- used_count
- valid_from
- valid_to
- active

## Order
- payment_fee
- discount_amount
- coupon_id (nullable)
- shipping_method_id
- payment_method_id

---

# 🔗 RELATIONSHIPS

- Order belongsTo ShippingMethod
- Order belongsTo PaymentMethod
- Order belongsTo Coupon (nullable)

---

# 🧠 BUSINESS LOGIC

- shipping:
  - ak cart_total >= free_from_price → shipping_price = 0

- payment:
  - payment_fee sa vždy pripočíta

- coupons:
  - validácia (active, date, usage_limit)
  - kontrola min_order_price

- discounts:
  - percent → (total * %)
  - fixed → odpočíta sa suma

- total_price výpočet:
