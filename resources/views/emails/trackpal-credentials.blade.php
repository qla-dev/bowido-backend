<!doctype html>
<html lang="nl">
<body style="margin:0;background:#f4f7f5;color:#18352b;font-family:Arial,sans-serif">
<div style="max-width:600px;margin:0 auto;padding:32px 20px">
    <div style="border-radius:18px;background:#ffffff;padding:32px;box-shadow:0 10px 30px rgba(15,23,42,.08)">
        <h1 style="margin:0 0 24px;color:#007d43;font-size:24px">{{ $isPasswordReset ? 'Wachtwoord opnieuw ingesteld' : 'Welkom bij Trackpal' }}</h1>
        <p>Beste {{ $credentialUser->name }},</p>
        <p>{{ $isPasswordReset ? 'Er is een nieuw tijdelijk wachtwoord voor uw Trackpal-account aangemaakt.' : 'Wij maken vanaf nu gebruik van Trackpal voor het registreren en volgen van pallets.' }}</p>
        <p>{{ $isPasswordReset ? 'U kunt hiermee opnieuw inloggen.' : 'U kunt inloggen met uw accountgegevens.' }}</p>

        @if($credentialUser->isCustomer() && $credentialUser->customerDetail?->kvk)
            <p><strong>Bedrijfs-/KVK-nummer:</strong><br>{{ $credentialUser->customerDetail->kvk }}</p>
        @else
            <p><strong>E-mailadres:</strong><br>{{ $credentialUser->email }}</p>
        @endif

        <p><strong>Tijdelijk wachtwoord:</strong><br><span style="display:inline-block;margin-top:6px;padding:10px 14px;border-radius:8px;background:#edf8f2;font-family:monospace;font-size:17px;letter-spacing:1px">{{ $temporaryPassword }}</span></p>
        <p>Bij uw eerste login krijgt u de mogelijkheid om dit wachtwoord te wijzigen of het toegewezen wachtwoord te behouden.</p>
        <p style="margin-top:28px">Met vriendelijke groet,<br><br>Trackpal</p>
    </div>
</div>
</body>
</html>
