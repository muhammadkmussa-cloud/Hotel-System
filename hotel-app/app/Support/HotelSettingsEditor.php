<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Throwable;

/**
 * Versioned hotel identity and business-day settings. Updates require the
 * caller's expected resource version (If-Match); stale edits fail.
 */
final class HotelSettingsEditor
{
    public const ALLOWED_TIMEZONES = [
        'Africa/Nairobi',
        'Africa/Dar_es_Salaam',
        'Africa/Kampala',
        'Africa/Kigali',
        'Africa/Addis_Ababa',
        'Africa/Mogadishu',
        'UTC',
    ];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array{name:?string,timezone:?string,business_day_cutoff:?string,resource_version:int} */
    public function current(): array
    {
        $row = $this->database->connection('mysql')->table('hotel_settings')->first(['name', 'timezone', 'business_day_cutoff', 'resource_version', 'receipt_header', 'receipt_footer']);

        return [
            'name' => $row->name ?? null,
            'timezone' => $row->timezone ?? null,
            'business_day_cutoff' => $row->business_day_cutoff ?? null,
            'receipt_header' => $row->receipt_header ?? null,
            'receipt_footer' => $row->receipt_footer ?? null,
            'resource_version' => (int) ($row->resource_version ?? 1),
        ];
    }

    /**
     * @param array{name?:string,timezone?:string,business_day_cutoff?:string} $changes
     * @return string updated|stale|invalid_input|failed
     */
    public function update(int $expectedVersion, array $changes): string
    {
        $clean = [];
        foreach (['name', 'timezone', 'business_day_cutoff', 'receipt_header', 'receipt_footer'] as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }
            $value = $changes[$field];
            if ($value === null) {
                $clean[$field] = null;
                continue;
            }
            $value = trim((string) $value);
            if ($field === 'name' && ($value === '' || mb_strlen($value) > 150)) {
                return 'invalid_input';
            }
            if ($field === 'timezone' && ! in_array($value, self::ALLOWED_TIMEZONES, true)) {
                return 'invalid_input';
            }
            if ($field === 'business_day_cutoff' && ! preg_match('/^\d{2}:\d{2}$/', $value)) {
                return 'invalid_input';
            }
            $clean[$field] = $value;
        }
        if ($clean === []) {
            return 'invalid_input';
        }

        $connection = $this->database->connection('mysql');
        $id = (string) $connection->table('hotel_settings')->value('id');
        if ($id === '') {
            return 'failed';
        }

        try {
            VersionedUpdate::apply($connection, 'hotel_settings', $id, $expectedVersion, $clean);

            return 'updated';
        } catch (PreconditionFailedHttpException) {
            return 'stale';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
