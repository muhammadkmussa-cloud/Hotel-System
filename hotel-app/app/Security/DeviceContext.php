<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\GuestBindingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolves an enrolled device from its httpOnly device cookie. Only the
 * SHA-256 digest of the token is stored. Revoked/expired sessions and
 * deactivated devices resolve to null (fail closed).
 */
final class DeviceContext
{
    public const COOKIE = 'hotel_device';

    public const ATTRIBUTE = 'hotel.device';

    public const GUEST_ATTRIBUTE = 'hotel.guest';

    public function __construct(private readonly GuestBindingService $bindings) {}

    /** @return array{sessionId:string,deviceId:string,mode:string,name:string}|null */
    public function resolve(Request $request): ?array
    {
        if ($request->attributes->has(self::ATTRIBUTE)) {
            return $request->attributes->get(self::ATTRIBUTE);
        }
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || strlen($token) < 32 || strlen($token) > 128) {
            return null;
        }
        try {
            $row = DB::table('device_sessions')
                ->join('devices', 'devices.id', '=', 'device_sessions.device_id')
                ->where('device_sessions.token_digest', hash('sha256', $token))
                ->whereNull('device_sessions.revoked_at')
                ->where('device_sessions.expires_at', '>', now('UTC'))
                ->where('devices.active', 1)
                ->first(['device_sessions.id as session_id', 'device_sessions.last_seen_at', 'devices.id as device_id', 'devices.mode', 'devices.name']);
        } catch (Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        if ($row->last_seen_at === null || strtotime((string) $row->last_seen_at.' UTC') < time() - 60) {
            try {
                DB::table('device_sessions')->where('id', $row->session_id)->update(['last_seen_at' => now('UTC')]);
                DB::table('devices')->where('id', $row->device_id)->update(['last_seen_at' => now('UTC')]);
            } catch (Throwable) {
                // Last-seen is advisory.
            }
        }
        $device = ['sessionId' => (string) $row->session_id, 'deviceId' => (string) $row->device_id, 'mode' => (string) $row->mode, 'name' => (string) $row->name];
        $request->attributes->set(self::ATTRIBUTE, $device);

        return $device;
    }

    /** @return array{bindingId:string,guestId:string,guestLabel:string,visitId:string,tableId:string,tableLabel:string}|null */
    public function guest(Request $request): ?array
    {
        $device = $this->resolve($request);
        if ($device === null || $device['mode'] !== 'tablet') {
            return null;
        }

        return $this->bindings->resolveForDeviceSession($device['sessionId']);
    }
}
