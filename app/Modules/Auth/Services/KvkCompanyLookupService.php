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

    /** @return array{source: 'database'|'kvk'|'not_found', fields: array<string, string>} */
    public function lookup(string $kvk): array
    {
        $detail = CustomerDetail::query()
            ->with('user')
            ->whereRaw("replace(replace(replace(replace(replace(kvk, ' ', ''), '.', ''), '-', ''), '/', ''), '(', '') = ?", [$kvk])
            ->first();

        if ($detail !== null) {
            Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'database', 'response_status' => 200]);

            return ['source' => 'database', 'fields' => $this->mapper->fromCustomerDetail($detail)];
        }

        $profile = $this->fetchProfile($kvk);

        if ($profile === null) {
            Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 404]);

            return ['source' => 'not_found', 'fields' => []];
        }

        Log::info('KVK registration lookup completed.', ['kvk' => $kvk, 'source' => 'kvk', 'response_status' => 200]);

        return ['source' => 'kvk', 'fields' => $this->mapper->fromKvkProfile($profile, $kvk)];
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
}
