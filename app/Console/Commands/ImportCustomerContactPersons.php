<?php

namespace App\Console\Commands;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ImportCustomerContactPersons extends Command
{
    protected $signature = 'customers:import-contact-persons {file : Path to the customer workbook} {--dry-run : Report changes without updating records}';
    protected $description = 'Import customer contact persons by exact company name from the KVK workbook.';

    public function handle(): int
    {
        $contacts = $this->contacts((string) $this->argument('file'));
        $matches = $updated = $blank = $unmatched = 0;
        $errors = [];

        DB::transaction(function () use ($contacts, &$matches, &$updated, &$blank, &$unmatched, &$errors): void {
            foreach ($contacts as $companyName => $contactPerson) {
                $details = CustomerDetail::query()->where('company_name', $companyName)->get();

                if ($details->isEmpty()) {
                    $unmatched++;
                    $this->warn("No customer details found for: {$companyName}");
                    continue;
                }

                if ($details->count() !== 1) {
                    $errors[] = "Multiple customer details match company name: {$companyName}";
                    continue;
                }

                $matches++;
                if ($contactPerson === '') {
                    $blank++;
                }

                $detail = $details->first();
                $value = $contactPerson !== '' ? $contactPerson : null;
                if ($detail->contact_person === $value) {
                    continue;
                }

                $updated++;
                if (! $this->option('dry-run')) {
                    $detail->update(['contact_person' => $value]);
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(PHP_EOL, $errors));
            }
        });

        $mode = $this->option('dry-run') ? 'Dry run' : 'Import';
        $this->info("{$mode}: {$matches} matched, {$updated} updated, {$blank} blank contacts, {$unmatched} unmatched.");

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function contacts(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open workbook: {$path}");
        }

        try {
            $shared = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('The workbook does not contain the expected first worksheet.');
            }

            $contacts = [];
            $sheet = new \SimpleXMLElement($sheetXml);
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                if ((int) $row['r'] <= 2) {
                    continue;
                }

                $cells = [];
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $value = $cell->xpath('./*[local-name()="v"]');
                    $cells[$this->columnIndex((string) $cell['r'])] = (string) $cell['t'] === 's'
                        ? ($shared[(int) ($value[0] ?? 0)] ?? '')
                        : (string) ($value[0] ?? '');
                }

                $companyName = trim((string) ($cells[0] ?? ''));
                if ($companyName === '') {
                    continue;
                }
                if (array_key_exists($companyName, $contacts)) {
                    throw new RuntimeException("Duplicate company name in workbook: {$companyName}");
                }
                $contacts[$companyName] = trim((string) ($cells[10] ?? ''));
            }

            return $contacts;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = new \SimpleXMLElement($xml);

        return array_map(static function ($item): string {
            $text = '';
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            return $text;
        }, $strings->xpath('//*[local-name()="si"]') ?: []);
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/([A-Z]+)/', $reference, $matches);
        $column = 0;
        foreach (str_split($matches[1] ?? '') as $character) {
            $column = $column * 26 + ord($character) - 64;
        }

        return $column - 1;
    }
}
