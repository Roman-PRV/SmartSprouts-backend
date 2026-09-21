<?php

namespace App\Services;

use App\Helpers\ConfigHelper;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserConsentDecline;
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
     *
     * @return bool Whether at least one new consent row was created.
     */
    public function recordAcceptance(User $user, ?string $ipAddress, ?string $userAgent): bool
    {
        $acceptedAt = now();

        return DB::transaction(function () use ($user, $ipAddress, $userAgent, $acceptedAt): bool {
            $recordedAny = false;

            foreach ($this->currentVersions() as $type => $version) {
                $consent = UserConsent::query()->firstOrCreate(
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

                $recordedAny = $recordedAny || $consent->wasRecentlyCreated;
            }

            return $recordedAny;
        });
    }

    /**
     * The account's consent state as the auth payloads report it.
     *
     * The decline is read only when consent is not current — accepting wins
     * over an earlier refusal without clearing it, so asking both questions
     * independently would let a restricted state outlive the refusal it
     * describes. Keeping that order here means no caller has to know it.
     *
     * @return array{consent_current: bool, consent_declined: bool}
     */
    public function stateFor(User $user): array
    {
        $current = $this->hasCurrentConsent($user);

        return [
            'consent_current' => $current,
            'consent_declined' => ! $current && $this->hasDeclinedCurrent($user),
        ];
    }

    /**
     * Record refusal of the current Terms and Privacy Policy versions.
     *
     * One checkbox covers both documents, so a refusal covers both too. The
     * acceptance trail is left untouched: a refusal is a state, not a
     * retraction of evidence already given for an earlier version.
     */
    public function recordDecline(User $user): void
    {
        $declinedAt = now();

        DB::transaction(function () use ($user, $declinedAt): void {
            foreach ($this->currentVersions() as $type => $version) {
                UserConsentDecline::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                        'document_version' => $version,
                    ],
                    ['declined_at' => $declinedAt],
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
     * Whether refusal rows exist for every document version in force — which
     * is not the same question as whether the account is restricted. stateFor()
     * is what puts the two in the order that makes the answer true.
     */
    private function hasDeclinedCurrent(User $user): bool
    {
        foreach ($this->currentVersions() as $type => $version) {
            $declined = $user->consentDeclines()
                ->where('type', $type)
                ->where('document_version', $version)
                ->exists();

            if (! $declined) {
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
