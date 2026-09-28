<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Teknisi = baris `users` (role teknisi, login email + password) + profil `technicians`.
 * Status aktif keduanya selalu disamakan.
 */
class TechnicianAccountService
{
    public const PHOTO_DIRECTORY = 'technicians';

    /**
     * @param  array{name: string, email: string, phone: string, password: string, notes: ?string, is_active: bool}  $data
     */
    public function create(array $data, ?UploadedFile $photo = null): Technician
    {
        return DB::transaction(function () use ($data, $photo) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::TEKNISI,
                'is_active' => $data['is_active'],
                'email_verified_at' => now(),
            ]);

            return $user->technician()->create([
                'photo' => $photo?->store(self::PHOTO_DIRECTORY, 'public'),
                'notes' => $data['notes'],
                'is_active' => $data['is_active'],
            ]);
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string, notes: ?string, is_active: bool}  $data
     */
    public function update(Technician $technician, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Technician
    {
        $oldPhoto = $technician->photo;

        DB::transaction(function () use ($technician, $data, $photo, $removePhoto) {
            $technician->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            $technician->notes = $data['notes'];
            if ($photo) {
                $technician->photo = $photo->store(self::PHOTO_DIRECTORY, 'public');
            } elseif ($removePhoto) {
                $technician->photo = null;
            }
            $technician->save();

            $this->setActive($technician, $data['is_active']);
        });

        if ($oldPhoto && $oldPhoto !== $technician->photo) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $technician;
    }

    /**
     * Nonaktif = tidak bisa login & tidak muncul saat assign. Sesi yang sedang berjalan diakhiri.
     */
    public function setActive(Technician $technician, bool $active): void
    {
        if ($technician->is_active === $active && $technician->user->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($technician, $active) {
            $technician->update(['is_active' => $active]);
            $technician->user->update(['is_active' => $active]);
        });

        if (! $active) {
            $this->endSessions($technician->user);
        }
    }

    public function resetPassword(Technician $technician, string $password): void
    {
        $technician->user->update(['password' => $password]);
        $this->endSessions($technician->user);
    }

    /**
     * Order aktif yang masih ditugaskan ke teknisi (perlu di-assign ulang bila teknisi dinonaktifkan).
     */
    public function activeOrderCount(Technician $technician): int
    {
        return $technician->orders()
            ->whereIn('status', array_filter(OrderStatus::activeCases(), fn (OrderStatus $status) => $status !== OrderStatus::MENUNGGU))
            ->count();
    }

    /**
     * Keluarkan user dari semua perangkat: sesi database + token "ingat saya".
     */
    private function endSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
