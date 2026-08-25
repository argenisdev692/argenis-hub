<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Repositories;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Domain\Ports\PasswordHistoryPort;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\PasswordHistoryEloquentModel;

/**
 * Keeps the last {@see self::RETAINED_PASSWORDS} credentials per user and
 * answers the reuse question without ever handing a stored hash back out.
 */
final readonly class EloquentPasswordHistoryRepository implements PasswordHistoryPort
{
    public const int RETAINED_PASSWORDS = 5;

    public function __construct(private Hasher $hasher) {}

    public function record(string $userUuid, string $hashedPassword): void
    {
        $userId = $this->userIdFor($userUuid);

        if ($userId === null) {
            return;
        }

        PasswordHistoryEloquentModel::query()->create([
            'user_id' => $userId,
            'password_hash' => $hashedPassword,
        ]);

        $this->pruneOlderThanRetained($userId);
    }

    public function matchesRecent(string $userUuid, string $plainPassword): bool
    {
        foreach ($this->retainedHashes($userUuid) as $hash) {
            if ($this->hasher->check($plainPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function forget(string $userUuid): void
    {
        $this->ownedBy($userUuid)->delete();
    }

    /**
     * @return list<string>
     */
    private function retainedHashes(string $userUuid): array
    {
        return $this->ownedBy($userUuid)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RETAINED_PASSWORDS)
            ->pluck('password_hash')
            ->all();
    }

    /**
     * Hard delete — retired credential material must leave the database rather
     * than linger behind a soft-delete flag.
     */
    private function pruneOlderThanRetained(int $userId): void
    {
        $keep = PasswordHistoryEloquentModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RETAINED_PASSWORDS)
            ->pluck('id');

        PasswordHistoryEloquentModel::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * @return Builder<PasswordHistoryEloquentModel>
     */
    private function ownedBy(string $userUuid): Builder
    {
        return PasswordHistoryEloquentModel::query()
            ->whereIn('user_id', User::query()->select('id')->where('uuid', $userUuid));
    }

    private function userIdFor(string $userUuid): ?int
    {
        return User::query()->where('uuid', $userUuid)->value('id');
    }
}
