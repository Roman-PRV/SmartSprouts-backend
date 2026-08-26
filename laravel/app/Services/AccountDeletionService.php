<?php

namespace App\Services;

use App\Helpers\EmailHasher;
use App\Mail\AccountDeletedMail;
use App\Mail\AccountDeletionCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AccountDeletionService
{
    /**
     * How long a one-time deletion code stays valid, in minutes.
     */
    public const CODE_TTL_MINUTES = 10;

    /**
     * Email a one-time deletion code to the account address.
     *
     * Only a hash of the code is cached (a cache dump alone can't confirm the
     * deletion); the TTL bounds how long the code works. Sent synchronously:
     * the user is waiting for this code, so a transport failure must surface
     * in the response instead of leaving them waiting for a mail that will
     * never arrive.
     */
    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), Hash::make($code), now()->addMinutes(self::CODE_TTL_MINUTES));

        Mail::to($user->email)
            ->locale(app()->getLocale())
            ->send(new AccountDeletionCodeMail($code));
    }

    /**
     * Whether the given one-time code matches the one issued to the user.
     *
     * A successful match consumes the code, so it cannot be replayed.
     */
    public function consumeCode(User $user, string $code): bool
    {
        $hash = Cache::get($this->cacheKey($user));

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            return false;
        }

        Cache::forget($this->cacheKey($user));

        return true;
    }

    /**
     * Erase the account and queue the deletion notice.
     *
     * Consent rows are pseudonymized rather than deleted (proof of consent
     * may be kept for the defense of legal claims, GDPR art. 17(3)(e)): a
     * keyed HMAC of the email replaces the identity link — a plain hash
     * would fall to a dictionary of addresses, while the key keeps the
     * pseudonym matchable to a complainant's address — IP and user-agent
     * evidence is cleared, and the FK's nullOnDelete detaches user_id when
     * the row goes. Game results follow via FK cascade; Sanctum tokens have
     * no FK, so they are deleted explicitly.
     */
    public function deleteAccount(User $user): void
    {
        $email = $user->email;
        $name = $user->name;

        DB::transaction(function () use ($user): void {
            $user->consents()->update([
                'email_hash' => EmailHasher::of($user->email),
                'ip_address' => null,
                'user_agent' => null,
            ]);

            $user->tokens()->delete();

            $user->delete();
        });

        // The account is gone the moment the transaction commits; the notice
        // is a courtesy. A queue outage right now must surface as a log entry
        // and a lost email, not as a 500 on an already-completed erasure.
        try {
            Mail::to($email)
                ->locale(app()->getLocale())
                ->queue(new AccountDeletedMail($name));
        } catch (Throwable $exception) {
            Log::error('Account-deleted notice could not be queued', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Cache key holding the hashed one-time code for the user.
     */
    private function cacheKey(User $user): string
    {
        return "account-deletion-code:{$user->id}";
    }
}
