<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Mail\TrackpalCredentialsMail;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\ApiToken;
use App\Modules\Users\Support\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CredentialDeliveryService
{
    public function __construct(private readonly TemporaryPasswordGenerator $passwordGenerator) {}

    public function send(User $user, string $temporaryPassword, bool $isPasswordReset = false): void
    {
        $user->loadMissing(['role', 'customerDetail']);
        Mail::to($user->email)->send(new TrackpalCredentialsMail($user, $temporaryPassword, $isPasswordReset));
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array{sent: array<int, array{id: int, name: string}>, failed: array<int, array{id: int, name: string, message: string}>}
     */
    public function resetAndSend(array $userIds): array
    {
        $users = User::query()
            ->with(['role', 'customerDetail'])
            ->whereKey($userIds)
            ->orderBy('name')
            ->get();
        $sent = [];
        $failed = [];

        foreach ($users as $user) {
            try {
                DB::transaction(function () use ($user): void {
                    $lockedUser = User::query()->with(['role', 'customerDetail'])->lockForUpdate()->findOrFail($user->id);
                    $temporaryPassword = $this->passwordGenerator->generate();

                    $lockedUser->update([
                        'password' => $temporaryPassword,
                        'first_time_login' => true,
                    ]);

                    // A send failure aborts this user's transaction so their old login remains valid.
                    $this->send($lockedUser, $temporaryPassword, true);
                    ApiToken::query()->where('user_id', $lockedUser->id)->delete();
                });

                $sent[] = ['id' => (int) $user->id, 'name' => (string) $user->name];
            } catch (Throwable $exception) {
                Log::warning('Sending Trackpal login details failed.', [
                    'user_id' => $user->id,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
                $failed[] = [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'message' => __('Login details could not be sent.'),
                ];
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
