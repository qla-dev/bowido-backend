<?php

namespace App\Console\Commands;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

class ImportCustomersFromKvkWorkbook extends Command
{
    protected $signature = 'customers:import-kvk {file : Path to Kupci_sa_KVK.xlsx} {--replace : Remove existing customer accounts before importing}';
    protected $description = 'Import customers from the KVK workbook, including structured office and warehouse addresses.';

    public function handle(): int
    {
        $rows = $this->rows((string) $this->argument('file'));
        $roleId = Role::query()->where('name', 'customer')->value('id');
        if (! $roleId) { $this->error('The customer role is missing.'); return self::FAILURE; }
        if ($this->option('replace')) {
            $this->removeExistingCustomers();
        }

        $unregisteredPassword = Hash::make(Str::random(48));

        $created = $updated = $skipped = 0;
        foreach ($rows as $index => $row) {
            $kvk = $this->value($row, 1);
            $company = $this->value($row, 0);
            if ($kvk === '' || $company === '') { $skipped++; continue; }
            $mobile = $this->value($row, 3);
            $email = strtolower($this->value($row, 2));
            $fixedPhone = $this->value($row, 4);
            $street = $this->value($row, 6);
            $houseNumber = $this->value($row, 7);
            $postalCode = $this->value($row, 8);
            $city = $this->value($row, 9);
            $warehouse1PostalCode = $this->value($row, 13);
            $warehouse1HouseNumber = $this->value($row, 14);
            $warehouse1Street = $this->value($row, 15);
            $warehouse1City = $this->value($row, 16);
            DB::transaction(function () use ($kvk, $company, $email, $mobile, $fixedPhone, $street, $houseNumber, $postalCode, $city, $warehouse1PostalCode, $warehouse1HouseNumber, $warehouse1Street, $warehouse1City, $roleId, $unregisteredPassword, &$created, &$updated): void {
                $detail = CustomerDetail::query()->with('user')->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$this->normalizeKvk($kvk)])->first();
                $user = $detail?->user;
                $loginEmail = filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? $email
                    : "kvk.{$this->normalizeKvk($kvk)}@trackpal.invalid";
                if (! $user) {
                    $user = User::query()->create(['role_id' => $roleId, 'name' => $company, 'email' => $loginEmail, 'phone_number' => $mobile ?: null, 'password' => $unregisteredPassword, 'is_active' => true]);
                } else {
                    $user->update(['role_id' => $roleId, 'name' => $company, 'email' => $loginEmail, 'phone_number' => $mobile ?: null, 'is_active' => true]);
                }

                $attributes = [
                    'company_name' => $company,
                    'country' => 'NL',
                    'kvk' => $kvk,
                    'billing_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'fixed_phone' => $fixedPhone ?: null,
                    'street' => $street ?: null,
                    'house_number' => $houseNumber ?: null,
                    'postal_code' => $postalCode ?: null,
                    'city' => $city ?: null,
                    'warehouse1_street' => $warehouse1Street ?: null,
                    'warehouse1_house_number' => $warehouse1HouseNumber ?: null,
                    'warehouse1_postal_code' => $warehouse1PostalCode ?: null,
                    'warehouse1_city' => $warehouse1City ?: null,
                    'warehouse2_street' => null,
                    'warehouse2_house_number' => null,
                    'warehouse2_postal_code' => null,
                    'warehouse2_city' => null,
                    'is_active' => true,
                ];
                if ($detail) { $detail->update($attributes); $updated++; }
                else { CustomerDetail::query()->create(['user_id' => $user->id, ...$attributes, 'default_price_per_day' => 2, 'grace_period_days' => 14]); $created++; }
            });
        }
        $this->info("Imported KVK workbook: {$created} created, {$updated} updated, {$skipped} skipped.");
        return self::SUCCESS;
    }

    private function removeExistingCustomers(): void
    {
        DB::transaction(function (): void {
            $customerIds = User::query()
                ->whereHas('role', fn ($query) => $query->where('name', 'customer'))
                ->pluck('id');

            if ($customerIds->isEmpty()) {
                return;
            }

            DB::table('pallets')->whereIn('user_id', $customerIds)->update(['user_id' => null]);
            DB::table('invoices')->whereIn('user_id', $customerIds)->update(['user_id' => null]);
            DB::table('ghost_pallet_reports')->whereIn('user_id', $customerIds)->update(['user_id' => null]);
            DB::table('customer_details')->whereIn('user_id', $customerIds)->delete();
            User::query()->whereIn('id', $customerIds)->delete();
        });
    }

    private function rows(string $path): array { $zip = new ZipArchive; if ($zip->open($path) !== true) throw new \RuntimeException("Cannot open {$path}"); $shared=[]; if (($xml=$zip->getFromName('xl/sharedStrings.xml')) !== false) { $s=new \SimpleXMLElement($xml); foreach ($s->si as $item) $shared[]=(string)$item->t ?: implode('', array_map('strval', iterator_to_array($item->r))); } $sheet=new \SimpleXMLElement((string)$zip->getFromName('xl/worksheets/sheet1.xml')); $out=[]; foreach ($sheet->sheetData->row as $row) { $values=[]; foreach ($row->c as $cell) { preg_match('/([A-Z]+)/', (string)$cell['r'], $m); $col=0; foreach (str_split($m[1]) as $char) $col=$col*26+ord($char)-64; $values[$col-1]=(string)$cell['t']==='s' ? ($shared[(int)$cell->v] ?? '') : (string)$cell->v; } $out[]=$values; } return array_slice($out, 2); }
    private function value(array $row, int $column): string { return trim((string)($row[$column] ?? '')); }
    private function normalizeKvk(string $value): string { return preg_replace('/[\s.-]+/', '', $value) ?: ''; }
    private function address(string $street, string $number, string $postal, string $city): string { return trim(implode(', ', array_filter([trim("{$street} {$number}"), trim("{$postal} {$city}")]))); }
}
