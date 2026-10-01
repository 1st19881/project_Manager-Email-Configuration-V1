<?php

namespace App\Support;

/**
 * Converts Thai text between UTF-8 and the legacy intranet tables.
 *
 * Those tables predate UTF-8 and store Thai as single byte TIS-620, so a value
 * read straight from them renders as mojibake once it reaches a UTF-8 mail or
 * JSON response. CP874 is the superset of TIS-620 and is used for the decode.
 *
 * Tables this application owns on the same database, such as TRC_MAIL_NOTI_MGR,
 * follow the same convention so they stay readable from the legacy pages and
 * SQL tools. Their models apply it through the LegacyThaiText cast.
 */
class LegacyText
{
    /**
     * Convert one legacy value to UTF-8, keeping null and empty values as they
     * are so callers can still tell "not recorded" from "recorded as blank".
     *
     * The conversion is unconditional on purpose. TIS-620 Thai frequently forms
     * byte sequences that are also valid UTF-8 — "สนิท" is stored as CA B9 D4 B7,
     * which passes a UTF-8 check but reads as garbage — so skipping the decode
     * for values that merely look like UTF-8 would leave real names broken.
     */
    public static function toUtf8(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return iconv('CP874', 'UTF-8//IGNORE', $value) ?: $value;
    }

    /**
     * Convert one UTF-8 value to TIS-620 for storage. Characters CP874 has no
     * byte for, such as emoji, are transliterated or dropped rather than
     * failing the whole write.
     */
    public static function toLegacy(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $encoded = iconv('UTF-8', 'CP874//TRANSLIT//IGNORE', $value);

        return $encoded === false ? $value : $encoded;
    }
}
