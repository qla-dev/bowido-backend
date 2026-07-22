<?php

namespace App\Providers;

use App\Modules\Locations\Contracts\LocationProviderInterface;
use App\Modules\Locations\Providers\GeoapifyLocationProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

class AppServiceProvider extends ServiceProvider
{
    private static ?Process $frontendProcess = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocationProviderInterface::class, function (): LocationProviderInterface {
            return match ((string) config('location.provider')) {
                'geoapify' => new GeoapifyLocationProvider,
                default => throw new \InvalidArgumentException('Unsupported location provider configured.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        RateLimiter::for('reverse-geocoding', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return Limit::perMinute(max(1, (int) config('location.rate_limit_per_minute', 30)))->by($key);
        });

        $this->startFrontendDevServerForArtisanServe();
    }

    private function startFrontendDevServerForArtisanServe(): void
    {
        if (! config('frontend.auto_start')) {
            return;
        }

        if (! $this->app->runningInConsole() || ($_SERVER['argv'][1] ?? null) !== 'serve') {
            return;
        }

        $devServerUrl = (string) config('frontend.dev_server_url');

        if ($this->frontendDevServerIsReachable($devServerUrl)) {
            return;
        }

        $frontendPath = $this->resolveFrontendPath((string) config('frontend.path'));

        if ($frontendPath === null) {
            return;
        }

        $urlParts = parse_url($devServerUrl) ?: [];
        $host = $urlParts['host'] ?? '127.0.0.1';
        $port = (string) ($urlParts['port'] ?? 3000);
        $npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';

        self::$frontendProcess = new Process(
            [$npm, 'run', 'dev', '--', '--host='.$host, '--port='.$port],
            $frontendPath,
            ['VITE_BACKEND_URL' => (string) config('app.url')]
        );

        self::$frontendProcess->setTimeout(null);
        self::$frontendProcess->disableOutput();
        self::$frontendProcess->start();

        if (defined('STDOUT')) {
            fwrite(STDOUT, "Frontend dev server starting at {$devServerUrl}\n");
        }

        register_shutdown_function(static function (): void {
            self::$frontendProcess?->stop(1);
        });
    }

    private function frontendDevServerIsReachable(string $url): bool
    {
        $urlParts = parse_url($url) ?: [];
        $host = $urlParts['host'] ?? '127.0.0.1';
        $scheme = $urlParts['scheme'] ?? 'http';
        $port = (int) ($urlParts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $connection = @fsockopen($host, $port, $errno, $error, 0.2);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }

    private function resolveFrontendPath(string $path): ?string
    {
        $isAbsolutePath = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path) === 1;
        $resolvedPath = realpath($isAbsolutePath ? $path : base_path($path));

        return $resolvedPath !== false ? $resolvedPath : null;
    }
}
