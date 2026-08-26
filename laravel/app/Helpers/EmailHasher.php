<?php

namespace App\Helpers;

use RuntimeException;

class EmailHasher
{
    /**
     * Keyed pseudonym of an email address.
     *
     * Shared by the consent trail and the purchase log, both of which locate
     * rows by this value after the account itself is gone. A second copy of the
     * formula would not fail loudly if it drifted — it would simply stop
     * matching, and a person whose address was capitalised differently would no
     * longer find their own record.
     *
     * The key rather than a plain hash: a bare digest of an email falls to a
     * dictionary of addresses, while the key keeps the pseudonym matchable to an
     * address someone supplies without making it guessable from the outside.
     *
     * @throws RuntimeException when LEGAL_HASH_KEY is unset
     */
    public static function of(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower($email), ConfigHelper::getRequiredString('legal.email_hash_key'));
    }
}
