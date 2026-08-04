<?php

namespace App\Services\Testing;

use App\Enums\InteractionWeightEnum;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserProvisioner
{
    /**
     * Create a dedicated test user with an initial interest profile,
     * mirroring the real onboarding flow (interest-selector).
     */
    public function createTestUser(string $name, array $categoryIds): User
    {
        $user = User::create([
            'name' => $name,
            'email' => 'tester+'.Str::random(8).'@thesis.local',
            'password' => Hash::make(Str::random(16)),
            'email_verified_at' => now(),
            'is_test_user' => true,
        ]);

        $user->interests()->syncWithPivotValues(
            $categoryIds,
            ['weight' => InteractionWeightEnum::LIKE->value]
        );

        $user->new_account = false;
        $user->save();

        return $user;
    }

    public function existingTestUsers()
    {
        return User::where('is_test_user', true)->orderByDesc('created_at')->get();
    }

    public function snapshotInterests(int $userId): array
    {
        return DB::table('interests')
            ->where('user_id', $userId)
            ->get(['category_id', 'weight'])
            ->map(fn ($row) => ['category_id' => $row->category_id, 'weight' => $row->weight])
            ->all();
    }

    /**
     * Reset interests back to a snapshot, so each algorithm run starts
     * from an identical baseline — needed for a fair A/B comparison.
     */
    public function restoreInterests(int $userId, array $snapshot): void
    {
        DB::table('interests')->where('user_id', $userId)->delete();

        foreach ($snapshot as $row) {
            DB::table('interests')->insert([
                'user_id' => $userId,
                'category_id' => $row['category_id'],
                'weight' => $row['weight'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function clearInteractions(int $userId): void
    {
        Interaction::where('user_id', $userId)->delete();
    }
}
