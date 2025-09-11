<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        $admins = [
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'phone' => '+1234567890',
                'role' => 'super_admin',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'has_temp_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Finance Manager',
                'username' => 'finance',
                'email' => 'finance@example.com',
                'phone' => '+1234567891',
                'role' => 'manager',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'has_temp_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Operations Staff',
                'username' => 'operations',
                'email' => 'operations@example.com',
                'phone' => '+1234567892',
                'role' => 'staff',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'has_temp_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Support Agent',
                'username' => 'support',
                'email' => 'support@example.com',
                'phone' => '+1234567893',
                'role' => 'staff',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'has_temp_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('admins')->insert($admins);
    }
}
