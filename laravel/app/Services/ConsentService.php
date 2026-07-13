<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\DB;

class ConsentService
{
    /**
     * Record acceptance of the current Terms and Privacy Policy versions.
     *
     * One checkbox covers both documents, so both rows must land atomically:
     * a partial acceptance would never satisfy hasCurrentConsent(). Idempotent
     * per version - firstOrCreate plus the unique (user, type, version) index
     * keep retries and double-clicks from duplicating evidence rows.
     */
    public function recordAcceptance(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        $acceptedAt = now();

        DB::transaction(function () use ($user, $ipAddress, $userAgent, $acceptedAt): void {
            foreach ($this->currentVersions() as $type => $version) {
                UserConsent::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                        'document_version' => $version,
                    ],
                    [
                        'accepted_at' => $acceptedAt,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
                    ],
                );
            }
        });
    }

    /**
     * Whether the user has accepted the current version of every document.
     *
     * False for Google-created accounts (no consent captured at creation),
     * for legacy accounts predating consent, and after a version bump in
     * config/legal.php - the client blocks the app and re-asks in all cases.
     */
    public function hasCurrentConsent(User $user): bool
    {
        foreach ($this->currentVersions() as $type => $version) {
            $accepted = $user->consents()
                ->where('type', $type)
                ->where('document_version', $version)
                ->exists();

            if (! $accepted) {
                return false;
            }
        }

        return true;
    }

    /**
     * The currently effective document versions, keyed by consent type.
     *
     * @return array<string, string>
     */
    private function currentVersions(): array
    {
        return [
            UserConsent::TYPE_TERMS => ConfigHelper::getRequiredString('legal.terms_version'),
            UserConsent::TYPE_PRIVACY => ConfigHelper::getRequiredString('legal.privacy_version'),
        ];
    }
}
