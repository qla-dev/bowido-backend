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
    protected $signature = 'customers:import-kvk {file : Path to Kupci_sa_KVK.xlsx}';
    protected $description = 'Import or update customers from the KVK workbook.';

    public function handle(): int
    {
        $rows = $this->rows((string) $this->argument('file'));
        $roleId = Role::query()->where('name', 'customer')->value('id');
        if (! $roleId) { $this->error('The customer role is missing.'); return self::FAILURE; }
        $unregisteredPassword = Hash::make(Str::random(48));

        $created = $updated = $skipped = 0;
        foreach ($rows as $index => $row) {
            $kvk = $this->value($row, 1);
            $company = $this->value($row, 0);
            if ($kvk === '' || $company === '') { $skipped++; continue; }
            $email = strtolower($this->value($row, 2));
            $fixedPhone = $this->value($row, 4);
            $phone = $this->value($row, 3) ?: $fixedPhone;
            $billing = $this->address($this->value($row, 6), $this->value($row, 7), $this->value($row, 8), $this->value($row, 9));
            // Workbook columns N:Q are postal code, number, street, city.
            $delivery = $this->address($this->value($row, 15), $this->value($row, 14), $this->value($row, 13), $this->value($row, 16));
            $warehousePostalCode = $this->value($row, 13);

            DB::transaction(function () use ($kvk, $company, $email, $phone, $fixedPhone, $billing, $delivery, $warehousePostalCode, $roleId, $unregisteredPassword, &$created, &$updated): void {
                $detail = CustomerDetail::query()->with('user')->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$this->normalizeKvk($kvk)])->first();
                $user = $detail?->user;
                if (! $user && $email !== '') $user = User::query()->where('email', $email)->whereDoesntHave('customerDetail')->first();
                if (! $user) {
                    $loginEmail = $email !== '' && ! User::query()->where('email', $email)->exists()
                        ? $email
                        : "kvk.{$this->normalizeKvk($kvk)}@trackpal.invalid";
                    $loginPhone = $phone !== '' && ! User::query()->where('phone_number', $phone)->exists() ? $phone : null;
                    $user = User::query()->create(['role_id' => $roleId, 'name' => $company, 'email' => $loginEmail, 'phone_number' => $loginPhone, 'password' => $unregisteredPassword, 'is_active' => true]);
                } else {
                    $user->fill(['role_id' => $roleId, 'name' => $company, 'is_active' => true]);
                    if ($phone === '' || ! User::query()->where('phone_number', $phone)->whereKeyNot($user->id)->exists()) $user->phone_number = $phone ?: null;
                    if ($email !== '' && ! User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) $user->email = $email;
                    $user->save();
                }
                $attributes = ['company_name' => $company, 'country' => 'NL', 'kvk' => $kvk, 'billing_email' => $email ?: null, 'fixed_phone' => $fixedPhone ?: null, 'street' => $delivery ?: null, 'postal_code' => $warehousePostalCode ?: null, 'billing_address' => $billing ?: null, 'delivery_address' => $delivery ?: null, 'tax_number' => $kvk, 'is_active' => true];
                if ($detail) { $detail->update($attributes); $updated++; }
                else { CustomerDetail::query()->create(['user_id' => $user->id, ...$attributes, 'default_price_per_day' => 0, 'grace_period_days' => 0]); $created++; }
            });
        }
        $this->info("Imported KVK workbook: {$created} created, {$updated} updated, {$skipped} skipped.");
        return self::SUCCESS;
    }

    private function rows(string $path): array { $zip = new ZipArchive; if ($zip->open($path) !== true) throw new \RuntimeException("Cannot open {$path}"); $shared=[]; if (($xml=$zip->getFromName('xl/sharedStrings.xml')) !== false) { $s=new \SimpleXMLElement($xml); foreach ($s->si as $item) $shared[]=(string)$item->t ?: implode('', array_map('strval', iterator_to_array($item->r))); } $sheet=new \SimpleXMLElement((string)$zip->getFromName('xl/worksheets/sheet1.xml')); $out=[]; foreach ($sheet->sheetData->row as $row) { $values=[]; foreach ($row->c as $cell) { preg_match('/([A-Z]+)/', (string)$cell['r'], $m); $col=0; foreach (str_split($m[1]) as $char) $col=$col*26+ord($char)-64; $values[$col-1]=(string)$cell['t']==='s' ? ($shared[(int)$cell->v] ?? '') : (string)$cell->v; } $out[]=$values; } return array_slice($out, 2); }
    private function value(array $row, int $column): string { return trim((string)($row[$column] ?? '')); }
    private function normalizeKvk(string $value): string { return preg_replace('/[\s.-]+/', '', $value) ?: ''; }
    private function address(string $street, string $number, string $postal, string $city): string { return trim(implode(', ', array_filter([trim("{$street} {$number}"), trim("{$postal} {$city}")]))); }
}
