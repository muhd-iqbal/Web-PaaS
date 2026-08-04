<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create or promote an administrator account';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email address');
        $password = $this->secret('Password (minimum 8 characters)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->plan_id ??= Plan::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        $user->save();

        $this->info("Administrator {$user->email} is ready. Visit /admin to sign in.");

        return self::SUCCESS;
    }
}
