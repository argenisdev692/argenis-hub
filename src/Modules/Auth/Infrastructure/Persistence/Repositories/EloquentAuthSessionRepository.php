<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Modules\Auth\Domain\ValueObjects\AuthSessionSnapshot;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;

final readonly class EloquentAuthSessionRepository implements AuthSessionTrackerPort
{
    public function register(
        string $userUuid,
        string $sessionId,
        DeviceFingerprint $device,
        ?string $ipAddress,
        ?string $userAgent,
    ): bool {
        $userId = $this->userIdFor($userUuid);

        if ($userId === null) {
            return false;
        }

        $isKnownDevice = AuthSessionEloquentModel::query()
            ->where('user_id', $userId)
            ->where('device_hash', $device->hash)
            ->exists();

        AuthSessionEloquentModel::query()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
                'device_hash' => $device->hash,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return ! $isKnownDevice;
    }

    public function touch(string $userUuid, string $sessionId): bool
    {
        return $this->ownedBy($userUuid)
            ->where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->update(['last_seen_at' => now()]) > 0;
    }

    public function release(string $userUuid, string $sessionId): void
    {
        $this->ownedBy($userUuid)
            ->where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revoke(string $userUuid, string $sessionUuid): bool
    {
        return $this->ownedBy($userUuid)
            ->where('uuid', $sessionUuid)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]) > 0;
    }

    public function revokeOthers(string $userUuid, string $currentSessionId): int
    {
        return $this->ownedBy($userUuid)
            ->where('session_id', '!=', $currentSessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function activeFor(string $userUuid, string $currentSessionId): array
    {
        return $this->ownedBy($userUuid)
            ->whereNull('revoked_at')
            ->select(['uuid', 'session_id', 'ip_address', 'user_agent', 'device_hash', 'last_seen_at', 'created_at'])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(static fn (AuthSessionEloquentModel $session): AuthSessionSnapshot => new AuthSessionSnapshot(
                uuid: $session->uuid,
                ipAddress: $session->ip_address,
                userAgent: $session->user_agent,
                deviceHash: $session->device_hash,
                lastSeenAt: $session->last_seen_at?->toIso8601String(),
                createdAt: (string) $session->created_at?->toIso8601String(),
                isCurrent: hash_equals($currentSessionId, $session->session_id),
            ))
            ->sortByDesc(static fn (AuthSessionSnapshot $snapshot): bool => $snapshot->isCurrent)
            ->values()
            ->all();
    }

    public function isRevoked(string $sessionId): bool
    {
        return AuthSessionEloquentModel::query()
            ->where('session_id', $sessionId)
            ->whereNotNull('revoked_at')
            ->exists();
    }

    /**
     * Every read and write is scoped to the owner, so a session uuid guessed
     * from another account resolves to nothing (OWASP §11 / API1).
     *
     * @return Builder<AuthSessionEloquentModel>
     */
    private function ownedBy(string $userUuid): Builder
    {
        return AuthSessionEloquentModel::query()
            ->whereIn('user_id', User::query()->select('id')->where('uuid', $userUuid));
    }

    private function userIdFor(string $userUuid): ?int
    {
        return User::query()->where('uuid', $userUuid)->value('id');
    }
}
