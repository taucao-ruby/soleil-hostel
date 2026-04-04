<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$accounts = [
    ['email' => 'user@soleil.test',      'role' => UserRole::USER],
    ['email' => 'moderator@soleil.test', 'role' => UserRole::MODERATOR],
    ['email' => 'admin@soleil.test',     'role' => UserRole::ADMIN],
];

foreach ($accounts as $a) {
    $u = User::updateOrCreate(
        ['email' => $a['email']],
        [
            'name' => ucfirst($a['role']->value).' Test',
            'password' => Hash::make('Test1234!'),
            'role' => $a['role'],
            'email_verified_at' => now(),
        ]
    );
    echo $u->role->value.' | '.$u->email.PHP_EOL;
}
