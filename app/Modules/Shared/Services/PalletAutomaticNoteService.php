<?php

namespace App\Modules\Shared\Services;

use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;

/**
 * Creates the short operational note shown on a pallet to customers.
 *
 * The audit trail deliberately keeps the individual actor. This note is
 * customer-facing, so it identifies the acting role instead.
 */
class PalletAutomaticNoteService
{
    public function statusChanged(User $actor, Status $status): string
    {
        return sprintf('%s marked pallet as %s.', $this->roleLabel($actor), $this->statusLabel($status));
    }

    public function statusChangedWithManualNotes(
        User $actor,
        Status $status,
        ?string $existingNotes,
        ?string $submittedNotes = null,
    ): string {
        $automaticNote = $this->statusChanged($actor, $status);
        $manualNotes = $this->manualNotes(
            $submittedNotes !== null && trim($submittedNotes) !== trim((string) $existingNotes)
                ? $submittedNotes
                : $existingNotes,
        );

        return $manualNotes === '' ? $automaticNote : "{$automaticNote}\n{$manualNotes}";
    }

    public function repairStatusChanged(User $actor, bool $isForRepair): string
    {
        return sprintf(
            '%s %s pallet %s service.',
            $this->roleLabel($actor),
            $isForRepair ? 'admitted' : 'removed',
            $isForRepair ? 'to' : 'from',
        );
    }

    private function roleLabel(User $actor): string
    {
        $actor->loadMissing('role');

        return match (strtolower((string) $actor->role?->name)) {
            'admin' => 'Administrator',
            'admin_service', 'service_admin', 'admin_servis' => 'Service administrator',
            'admin_warehouse', 'warehouse_admin', 'admin_magacin' => 'Warehouse administrator',
            'driver' => 'Driver',
            'warehouse_operator', 'operator' => 'Warehouse employee',
            'customer' => 'Customer',
            'technician' => 'Technician',
            'finance_administration', 'finance_admin' => 'Finance administrator',
            default => 'User',
        };
    }

    private function statusLabel(Status $status): string
    {
        return match ($status->slug) {
            'bij-de-klant' => 'At client',
            'ophalen-klant' => 'Ready for Return',
            'bih-nl-transport', 'nl-bih-transport' => 'in transport',
            default => $status->name,
        };
    }

    private function manualNotes(?string $notes): string
    {
        $lines = preg_split('/\R/', trim((string) $notes)) ?: [];
        $automaticNotePatterns = [
            '/^(?:Administrator|Service administrator|Warehouse administrator|Driver|Warehouse employee|Customer|Technician|Finance administrator|User) marked pallet as .+\.$/i',
            '/^(?:Driver|Chauffeur|Vozač) marked pallet (?:as|in) .+\.$/i',
            '/^Chauffeur heeft de bok gemarkeerd (?:als|voor) .+\.$/i',
            '/^Voza.* označio paletu (?:kao|za) .+\.$/iu',
            '/^(?:.*) (?:admitted pallet to service|removed pallet from service)\.$/i',
        ];

        return collect($lines)
            ->map(static fn (string $line): string => trim($line))
            ->filter(function (string $line) use ($automaticNotePatterns): bool {
                if ($line === '') {
                    return false;
                }

                foreach ($automaticNotePatterns as $pattern) {
                    if (preg_match($pattern, $line) === 1) {
                        return false;
                    }
                }

                return true;
            })
            ->implode("\n");
    }
}
