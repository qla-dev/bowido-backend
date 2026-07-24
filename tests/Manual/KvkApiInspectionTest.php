<?php

namespace Tests\Manual;

use App\Modules\Auth\Services\KvkRegistrationFieldMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Opt-in diagnostic test. It is outside phpunit.xml's normal test suites.
 *
 * Run from backend/ with a real KVK number:
 *   $env:KVK_INSPECT_NUMBER='12345678'
 *   php artisan test tests/Manual/KvkApiInspectionTest.php
 *
 * It prints the raw KVK payload and the form fields derived from it. The API
 * key is only sent as a request header and is never printed.
 */
class KvkApiInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_the_raw_kvk_profile_and_the_mapped_registration_fields(): void
    {
        $kvk = preg_replace('/[\s.\-\/()]+/', '', (string) env('KVK_INSPECT_NUMBER'));

        if (! is_string($kvk) || ! preg_match('/^\d{8}$/', $kvk)) {
            $this->markTestSkipped('Set KVK_INSPECT_NUMBER to the 8-digit KVK number you want to inspect.');
        }

        $apiKey = trim((string) config('services.kvk.api_key'));
        $this->assertNotSame('', $apiKey, 'KVK_API_KEY is not configured.');

        $url = rtrim((string) config('services.kvk.basisprofiel_url'), '/').'/'.$kvk;
        $response = Http::acceptJson()
            ->withHeaders(['apikey' => $apiKey])
            ->withUserAgent('Trackpal KVK inspection')
            ->connectTimeout((int) config('services.kvk.connect_timeout_seconds', 3))
            ->timeout((int) config('services.kvk.timeout_seconds', 8))
            ->get($url);

        $payload = $response->json();

        fwrite(STDOUT, PHP_EOL.'KVK endpoint: '.$url.PHP_EOL);
        fwrite(STDOUT, 'HTTP status: '.$response->status().PHP_EOL);
        fwrite(STDOUT, 'Raw KVK response:'.PHP_EOL.json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);

        if (is_array($payload)) {
            $mapped = app(KvkRegistrationFieldMapper::class)->fromKvkProfile($payload, $kvk);
            fwrite(STDOUT, 'Mapped registration fields:'.PHP_EOL.json_encode($mapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
        }

        $this->assertTrue($response->successful(), 'KVK did not return a successful profile response.');
        $this->assertIsArray($payload);
    }
}
