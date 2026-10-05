<?php

namespace App\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Imports a reviewed private manifest; never reads Gmail or sends notifications. */
class ImportGmailOrders extends Command
{
    protected $signature = 'orders:import-gmail {manifest : Private JSON manifest} {--apply : Commit the additive import} {--receipt= : Save the import result as JSON}';

    protected $description = 'Import reviewed direct email orders as historical archives (dry run by default)';

    public function handle(): int
    {
        try {
            $manifest = json_decode(file_get_contents($this->argument('manifest')), true, 512, JSON_THROW_ON_ERROR);
            Validator::make($manifest, [
                'account' => 'required|email', 'records' => 'required|array',
                'records.*.source_id' => 'required|regex:/^[a-f0-9]{16}$/|distinct',
                'records.*.source_ids' => 'required|array|min:1',
                'records.*.source_ids.*' => 'required|regex:/^[a-f0-9]{16}$/',
                'records.*.source_date' => 'required|date_format:Y-m-d\TH:i:sP|before_or_equal:now',
                'records.*.source_body' => 'required|string|max:16000',
                'records.*.source_subject' => 'nullable|string|max:1000',
                'records.*.status' => 'required|in:archived',
                'records.*.customer_id' => 'nullable|integer',
                'records.*.company' => 'required|string|max:200',
                'records.*.name' => 'nullable|string|max:150',
                'records.*.email' => 'required|email|max:150',
                'records.*.phone' => 'nullable|string|max:40',
                'records.*.street' => 'nullable|string|max:250',
                'records.*.city' => 'nullable|string|max:100',
                'records.*.postcode' => 'nullable|string|max:20',
                'records.*.ico' => 'nullable|regex:/^[0-9]{8}$/',
                'records.*.dic' => 'nullable|string|max:200',
                'records.*.missing' => 'required|array', 'records.*.missing.*' => 'required|string',
                'records.*.items' => 'present|array',
                'records.*.items.*.name' => 'required|string|max:200',
                'records.*.items.*.quantity' => 'required|integer|min:1|max:9999',
                // Historical price references are checked against the current database before any write.
                'records.*.items.*.price' => 'present|nullable|numeric|min:0|max:999999.99',
                'records.*.items.*.price_origin' => 'nullable|in:email,historical_order',
                'records.*.items.*.price_reference' => 'nullable|array',
            ])->validate();
            $apply = (bool) $this->option('apply');
            $result = DB::transaction(fn () => $this->import($manifest, $apply));
            $result['applied'] = $apply;
            $result['database'] = DB::connection()->getDatabaseName();
            $result['manifest_sha256'] = hash_file('sha256', $this->argument('manifest'));
            if ($this->option('receipt')) {
                if (file_put_contents($this->option('receipt'), json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                    throw new RuntimeException('Import finished, but receipt could not be saved. Do not assume it rolled back.');
                }
            }
            $this->line(json_encode($result['counts'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info($apply ? 'Historical archives imported without model events or notifications.' : 'Dry run completed; no rows were written.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function import(array $manifest, bool $apply): array
    {
        $result = ['counts' => ['orders' => 0, 'customers' => 0, 'contacts' => 0, 'items' => 0, 'skipped' => 0], 'created' => ['orders' => [], 'customers' => [], 'users' => [], 'order_products' => []]];
        $customers = DB::table('customers')->whereNull('deleted_at')->get()->keyBy('id')->map(fn ($c) => (array) $c)->all();
        $users = DB::table('users')->whereNull('deleted_at')->get(['id', 'customer_id', 'email'])->map(fn ($u) => (array) $u)->all();
        $linkedCustomers = [];
        foreach ($users as $u) {
            $linkedCustomers[strtolower($u['email'])][$u['customer_id']] = true;
        }
        $virtualId = -1;
        foreach ($manifest['records'] as $r) {
            $uuid = $this->sourceUuid($manifest['account'], $r['source_id']);
            if (DB::table('orders')->where('uuid', $uuid)->exists()) {
                $result['counts']['skipped']++;

                continue;
            }
            foreach ($r['items'] as $item) {
                if ($item['price'] === null) {
                    continue;
                }
                if (($item['price_origin'] ?? null) === 'historical_order') {
                    $reference = $item['price_reference'] ?? [];
                    $original = DB::table('order_products')->where('id', $reference['order_product_id'] ?? null)->first();
                    if (! $original || $original->order_id !== ($reference['order_id'] ?? null) || $original->product_id !== ($reference['product_id'] ?? null) || abs((float) $original->price - (float) $item['price']) > 0.001) {
                        throw new RuntimeException('Historical price reference mismatch for source '.$r['source_id']);
                    }
                } elseif (($item['price_origin'] ?? null) !== 'email') {
                    throw new RuntimeException('Missing price provenance for source '.$r['source_id']);
                }
            }
            $email = strtolower($r['email']);
            $ico = $r['ico'] ?? null;
            $customer = null;
            $matches = [];
            foreach ($customers as $c) {
                $sameIco = $ico && preg_replace('/\D/', '', $c['ico'] ?? '') === $ico;
                $sameEmail = strtolower($c['email'] ?? '') === $email;
                $linked = isset($linkedCustomers[$email][$c['id']]);
                if ($sameIco || (($sameEmail || $linked) && (! $ico || empty($c['ico']) || preg_replace('/\D/', '', $c['ico']) === $ico))) {
                    $matches[$c['id']] = $c;
                }
            }
            if (! empty($r['customer_id'])) {
                // An ID alone is not sufficient when replaying against a different database.
                $customer = $matches[$r['customer_id']] ?? throw new RuntimeException('Customer identity mismatch for source '.$r['source_id']);
            } elseif (count($matches) === 1) {
                $customer = reset($matches);
            } elseif (count($matches) > 1) {
                throw new RuntimeException('Ambiguous customer for source '.$r['source_id']);
            }
            $date = (new DateTimeImmutable($r['source_date']))->setTimezone(new DateTimeZone(config('app.timezone')))->format('Y-m-d H:i:s');
            if (! $customer) {
                $customer = [
                    'status' => 'active', 'company' => $r['company'], 'slug' => Str::slug($r['company']).'-gmail-'.$r['source_id'],
                    'email' => $email, 'phone' => $r['phone'], 'street' => $r['street'],
                    'postcode' => $r['postcode'] ?? '', 'city' => $r['city'] ?? '', 'ico' => $ico, 'dic' => $r['dic'],
                    'note' => 'Historický kontakt z priamej e-mailovej objednávky; neúplné údaje sú označené v objednávke.',
                    'created_at' => $date, 'updated_at' => $date,
                ];
                $customer['slug'] = mb_substr($customer['slug'], 0, 200);
                $id = $apply ? DB::table('customers')->insertGetId($customer) : $virtualId--;
                $customer['id'] = $id;
                $customers[$id] = $customer;
                $result['counts']['customers']++;
                if ($apply) {
                    $result['created']['customers'][] = $id;
                }
            }
            $user = collect($users)->first(fn ($u) => $u['customer_id'] === $customer['id'] && strtolower($u['email']) === $email);
            if (! $user) {
                // Imported contacts get no verified email, roles, password invitation or login activation.
                $data = [
                    'uuid' => (string) Str::uuid(), 'status' => 'active', 'active' => false,
                    'name' => $r['name'], 'username' => $r['name'] ? mb_substr($r['name'], 0, 100) : null,
                    'firstName' => '', 'lastName' => '', 'slug' => 'gmail-'.$r['source_id'],
                    'email' => $email, 'phone' => $r['phone'], 'customer_id' => $customer['id'],
                    'password' => $apply ? Hash::make(Str::random(64)) : '',
                    'created_at' => $date, 'updated_at' => $date,
                ];
                $id = $apply ? DB::table('users')->insertGetId($data) : $virtualId--;
                $user = ['id' => $id, 'customer_id' => $customer['id'], 'email' => $email];
                $users[] = $user;
                $linkedCustomers[$email][$customer['id']] = true;
                $linkedCustomers[$email][$customer['id']] = true;
                $result['counts']['contacts']++;
                if ($apply) {
                    $result['created']['users'][] = $id;
                }
            }
            $note = "Historická priama e-mailová objednávka — rekonštrukcia.\nChýbajúce/neoverené údaje:\n- ".implode("\n- ", $r['missing'])."\n\nZdrojový účet: ".$manifest['account']."\nPôvodný dátum: ".$r['source_date']."\nPredmet: ".$r['source_subject']."\n";
            foreach ($r['source_ids'] as $source) {
                $note .= 'https://mail.google.com/mail/u/0/#all/'.$source."\n";
            }
            $note .= "\nPôvodný text objednávky:\n".$r['source_body'];
            $snapshot = array_intersect_key($r, array_flip(['name', 'company', 'email', 'phone', 'street', 'city', 'postcode', 'ico', 'dic']));
            $order = [
                'uuid' => $uuid, 'serial_number' => 'GMAIL-'.$r['source_id'], 'status' => 'archived',
                'customer_id' => $customer['id'], 'user_id' => $user['id'], 'name' => $r['name'],
                'email' => $email, 'phone' => $r['phone'], 'note' => $note, 'isOpened' => true,
                'billing_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'snapshot_source' => 'reconstructed', 'created_at' => $date, 'updated_at' => $date,
            ];
            $orderId = $apply ? DB::table('orders')->insertGetId($order) : $virtualId--;
            $result['counts']['orders']++;
            if ($apply) {
                $result['created']['orders'][] = $orderId;
            }
            foreach ($r['items'] as $item) {
                $row = [
                    'status' => 'active', 'order_id' => $orderId, 'product_id' => null, 'is_custom' => true,
                    'quantity' => $item['quantity'], 'price' => $item['price'], 'total' => $item['price'] === null ? null : round($item['quantity'] * $item['price'], 2),
                    'product_snapshot' => json_encode(['name' => $item['name'], 'code' => null, 'unit_value' => 'ks', 'vat' => null, 'variant_name' => null, 'variant_code' => null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => $date, 'updated_at' => $date,
                ];
                $result['counts']['items']++;
                if ($apply) {
                    $result['created']['order_products'][] = DB::table('order_products')->insertGetId($row);
                }
            }
        }

        return $result;
    }

    private function sourceUuid(string $account, string $source): string
    {
        $hex = substr(hash('sha256', strtolower($account).'|'.$source), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
