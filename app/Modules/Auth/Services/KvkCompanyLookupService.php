<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Exceptions\KvkLookupException;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KvkCompanyLookupService
{
    public function __construct(private readonly KvkRegistrationFieldMapper $mapper)
    {
    }

    /** @return array{source: 'database'|'kvk'|'not_found', fields: array<string, string>, company_names: array<int, string>, company_options: array<int, array{name: string, fields: array<string, string>}>} */
    public function lookup(string $kvk): array
    {
        $details = CustomerDetail::query()
            ->with('user')
            ->whereRaw("replace(replace(replace(replace(replace(kvk, ' ', ''), '.', ''), '-', ''), '/', ''), '(', '') = ?", [$kvk])
            ->orderBy('company_name')
            ->get();

        if ($details->isNotEmpty()) {
            Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'database', 'response_status' => 200]);

            $companyOptions = $details
                ->map(function (CustomerDetail $detail): array {
                    $name = trim((string) $detail->company_name);

                    return [
                        'name' => $name,
                        'fields' => $this->completeRegistrationFields(
                            array_merge($this->mapper->fromCustomerDetail($detail), ['name' => $name]),
                        ),
                    ];
                })
                ->filter(fn (array $option): bool => $option['name'] !== '')
                ->unique(fn (array $option): string => mb_strtolower($option['name']))
                ->values();

            return [
                'source' => 'database',
                'fields' => $this->mapper->fromCustomerDetail($details->first()),
                'company_names' => $companyOptions->pluck('name')->all(),
                'company_options' => $companyOptions->all(),
            ];
        }

        $profile = $this->fetchProfile($kvk);

        if ($profile === null) {
            Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 404]);

            return ['source' => 'not_found', 'fields' => [], 'company_names' => [], 'company_options' => []];
        }

        Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 200]);

        $fields = $this->mapper->fromKvkProfile($profile, $kvk);
        $companyNames = $this->mapper->companyNamesFromKvkProfile($profile);

        return [
            'source' => 'kvk',
            'fields' => $fields,
            'company_names' => $companyNames,
            'company_options' => array_map(
                fn (string $name): array => ['name' => $name, 'fields' => array_merge($fields, ['name' => $name])],
                $companyNames,
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchProfile(string $kvk): ?array
    {
        $apiKey = trim((string) config('services.kvk.api_key'));
        if ($apiKey === '') {
            Log::warning('KVK registration lookup unavailable.', ['kvk' => $kvk, 'source' => 'kvk', 'failure_category' => 'not_configured']);
            throw new KvkLookupException(__('Company lookup is temporarily unavailable.'));
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => $apiKey])
                ->withUserAgent('Trackpal KVK lookup')
                ->connectTimeout((int) config('services.kvk.connect_timeout_seconds', 3))
                ->timeout((int) config('services.kvk.timeout_seconds', 8))
                ->retry(2, 250, null, false)
                ->get(rtrim((string) config('services.kvk.basisprofiel_url'), '/').'/'.$kvk);
        } catch (ConnectionException) {
            Log::warning('KVK registration lookup failed.', ['kvk' => $kvk, 'source' => 'kvk', 'failure_category' => 'connection']);
            throw new KvkLookupException(__('Company lookup is temporarily unavailable.'));
        }

        if ($response->status() === 404) {
            return null;
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::error('KVK registration lookup failed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => $response->status(), 'failure_category' => 'credentials']);
            throw new KvkLookupException(__('Company lookup is temporarily unavailable.'));
        }

        if ($response->status() === 429) {
            Log::warning('KVK registration lookup failed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 429, 'failure_category' => 'rate_limited']);
            throw new KvkLookupException(__('Company lookup is temporarily unavailable.'));
        }

        if ($response->failed()) {
            Log::warning('KVK registration lookup failed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => $response->status(), 'failure_category' => 'upstream']);
            throw new KvkLookupException(__('Company lookup is temporarily unavailable.'));
        }

        $profile = $response->json();
        if (! is_array($profile)) {
            Log::warning('KVK registration lookup failed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 200, 'failure_category' => 'invalid_response']);
            throw new KvkLookupException(__('Company lookup returned an invalid response.'), 502);
        }

        return $profile;
    }

    /** @param array<string, string> $fields
     *  @return array<string, string> */
    private function completeRegistrationFields(array $fields): array
    {
        return array_merge(array_fill_keys([
            'kvk', 'name', 'country', 'email', 'phone_number', 'fixed_phone',
            'street', 'house_number', 'postal_code', 'city',
            'warehouse1_street', 'warehouse1_house_number', 'warehouse1_postal_code', 'warehouse1_city',
            'warehouse2_street', 'warehouse2_house_number', 'warehouse2_postal_code', 'warehouse2_city',
        ], ''), $fields);
    }
}
