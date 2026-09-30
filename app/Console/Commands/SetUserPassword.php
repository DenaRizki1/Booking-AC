<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Ganti password akun admin/teknisi dari terminal (mis. password admin bawaan saat go-live).
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:password {email : Email akun admin/teknisi}';

    protected $description = 'Ganti password akun admin atau teknisi';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if (! $user || $user->role === UserRole::CLIENT) {
            $this->error('Akun admin/teknisi dengan email tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password baru (min. 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi password baru');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->update(['password' => $password]);
        $this->info("Password {$user->email} diperbarui.");

        return self::SUCCESS;
    }
}
