<?php

/**
 * Read-only KVK registration inspector.
 *
 * Browser: /phptest/kvk-registration.php?kvk=90001745
 * CLI:     php public/phptest/kvk-registration.php "kvk=90001745"
 *
 * This utility calls the configured KVK Basisprofiel API directly. It exposes
 * neither the API key nor request headers and makes no database changes.
 */

declare(strict_types=1);

use App\Modules\Auth\Services\KvkRegistrationFieldMapper;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$app = require dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
$app->make(Kernel::class)->bootstrap();

if (PHP_SAPI === 'cli') {
    parse_str((string) ($argv[1] ?? ''), $_GET);
}

/** @param mixed $value */
function kvk_inspector_value($value): string
{
    if ($value === null) {
        return '';
    }

    return trim((string) $value);
}

/** @param mixed $value */
function kvk_inspector_html($value): string
{
    return htmlspecialchars(kvk_inspector_value($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, mixed> $payload */
function kvk_inspector_json(array $payload): string
{
    return (string) json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
    );
}

$fields = [
    'kvk' => 'KVK number',
    'name' => 'Company name',
    'country' => 'Country',
    'email' => 'Email address',
    'phone_number' => 'Mobile phone',
    'fixed_phone' => 'Fixed phone',
    'street' => 'Address: street',
    'house_number' => 'Address: house number',
    'postal_code' => 'Address: postal code',
    'city' => 'Address: city',
    'warehouse1_street' => 'Warehouse 1: street',
    'warehouse1_house_number' => 'Warehouse 1: house number',
    'warehouse1_postal_code' => 'Warehouse 1: postal code',
    'warehouse1_city' => 'Warehouse 1: city',
    'warehouse2_street' => 'Warehouse 2: street',
    'warehouse2_house_number' => 'Warehouse 2: house number',
    'warehouse2_postal_code' => 'Warehouse 2: postal code',
    'warehouse2_city' => 'Warehouse 2: city',
    'password' => 'Password',
    'password_confirmation' => 'Confirm password',
];

$kvkInput = trim((string) ($_GET['kvk'] ?? ''));
$kvk = preg_replace('/[\s.\-\/()]+/', '', $kvkInput) ?? '';
$rawPayload = null;
$mappedFields = [];
$error = null;
$status = null;

if ($kvkInput !== '') {
    if (preg_match('/^\d{8}$/', $kvk) !== 1) {
        $error = 'Enter an 8-digit KVK number.';
    } else {
        $apiKey = trim((string) config('services.kvk.api_key'));
        if ($apiKey === '') {
            $error = 'KVK_API_KEY is not configured.';
        } else {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['apikey' => $apiKey])
                    ->withUserAgent('Trackpal KVK registration inspector')
                    ->connectTimeout((int) config('services.kvk.connect_timeout_seconds', 3))
                    ->timeout((int) config('services.kvk.timeout_seconds', 8))
                    ->get(rtrim((string) config('services.kvk.basisprofiel_url'), '/').'/'.$kvk);

                $status = $response->status();
                $json = $response->json();
                $rawPayload = is_array($json) ? $json : ['response' => $response->body()];

                if (! $response->successful()) {
                    $error = 'The KVK API returned HTTP '.$status.'.';
                } else {
                    $mappedFields = app(KvkRegistrationFieldMapper::class)->fromKvkProfile($rawPayload, $kvk);
                }
            } catch (Throwable $exception) {
                $error = 'The KVK API could not be reached: '.$exception->getMessage();
            }
        }
    }
}

if (PHP_SAPI === 'cli') {
    if ($error !== null) {
        fwrite(STDERR, $error.PHP_EOL);
        exit(1);
    }

    if ($rawPayload === null) {
        fwrite(STDERR, 'Pass an 8-digit KVK number, for example: php public/phptest/kvk-registration.php "kvk=90001745"'.PHP_EOL);
        exit(1);
    }

    echo 'KVK API HTTP status: '.$status.PHP_EOL;
    echo 'Registration fields:'.PHP_EOL.kvk_inspector_json($mappedFields).PHP_EOL;
    echo 'Raw KVK API response:'.PHP_EOL.kvk_inspector_json($rawPayload).PHP_EOL;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KVK Registration Inspector</title>
  <style>
    :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #f5f8f6; color: #17201a; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 32px 16px; }
    main { max-width: 1120px; margin: 0 auto; }
    h1 { margin: 0; color: #075f35; font-size: 28px; letter-spacing: -.03em; }
    h2 { margin: 0 0 14px; font-size: 18px; }
    p { line-height: 1.5; }
    .intro { color: #526259; max-width: 780px; }
    form, section { margin-top: 20px; border: 1px solid #dce7df; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgb(19 68 40 / 6%); }
    form { display: flex; gap: 12px; padding: 16px; }
    input { min-width: 0; flex: 1; border: 1px solid #b9cbbf; border-radius: 10px; padding: 12px 14px; font: inherit; }
    button { border: 0; border-radius: 10px; padding: 12px 18px; background: #00a65a; color: white; font: inherit; font-weight: 700; cursor: pointer; }
    section { padding: 20px; }
    .notice { border-color: #f0c4c4; background: #fff7f7; color: #9a2525; }
    .meta { margin: -5px 0 16px; color: #64746a; font-size: 14px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 11px 12px; border-bottom: 1px solid #e8eeea; text-align: left; vertical-align: top; }
    th { color: #516158; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
    .returned { color: #087443; font-weight: 700; }
    .missing { color: #9b5b00; font-weight: 700; }
    .manual { color: #5b6070; font-weight: 700; }
    code, pre { font-family: "Cascadia Code", Consolas, monospace; }
    pre { max-height: 560px; overflow: auto; margin: 0; padding: 16px; border-radius: 10px; background: #14231a; color: #e4f7e9; font-size: 12px; line-height: 1.5; }
    @media (max-width: 600px) { body { padding: 16px; } form { flex-direction: column; } button { width: 100%; } th, td { padding: 9px; } }
  </style>
</head>
<body>
  <main>
    <h1>KVK Registration Inspector</h1>
    <p class="intro">Enter a KVK number to inspect exactly what the KVK Basisprofiel API returns and which values can populate the TrackPal KVK registration form. This is read-only: it does not create or update data.</p>

    <form method="get" action="">
      <input id="kvk" name="kvk" inputmode="numeric" pattern="[0-9 .\-/()]+" maxlength="20" value="<?= kvk_inspector_html($kvkInput) ?>" placeholder="8-digit KVK number" autofocus required>
      <button type="submit">Inspect KVK</button>
    </form>

    <?php if ($error !== null): ?>
      <section class="notice"><strong>Lookup unavailable:</strong> <?= kvk_inspector_html($error) ?></section>
    <?php endif; ?>

    <?php if ($rawPayload !== null): ?>
      <section>
        <h2>KVK registration fields</h2>
        <p class="meta">Provider response: HTTP <?= kvk_inspector_html($status) ?>. “Missing” means no value was returned by the KVK API for this registration field.</p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Registration field</th><th>Status</th><th>Value</th></tr></thead>
            <tbody>
              <?php foreach ($fields as $key => $label): ?>
                <?php $isPassword = in_array($key, ['password', 'password_confirmation'], true); ?>
                <?php $value = kvk_inspector_value($mappedFields[$key] ?? null); ?>
                <tr>
                  <td><?= kvk_inspector_html($label) ?><br><code><?= kvk_inspector_html($key) ?></code></td>
                  <?php if ($isPassword): ?>
                    <td class="manual">Manual entry</td><td>Required from the user; never returned by KVK.</td>
                  <?php elseif ($value !== ''): ?>
                    <td class="returned">Returned</td><td><?= kvk_inspector_html($value) ?></td>
                  <?php else: ?>
                    <td class="missing">Missing</td><td>—</td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2>Mapped registration payload</h2>
        <p class="meta">Only fields with a value are included. Password fields are intentionally omitted.</p>
        <pre><?= kvk_inspector_html(kvk_inspector_json($mappedFields)) ?></pre>
      </section>

      <section>
        <h2>Raw KVK API response</h2>
        <p class="meta">The provider payload is shown as received. API credentials and request headers are never shown.</p>
        <pre><?= kvk_inspector_html(kvk_inspector_json($rawPayload)) ?></pre>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
