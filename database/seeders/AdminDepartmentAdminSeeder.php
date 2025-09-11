<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDepartmentAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        // First, let's get some admin IDs
        // Assuming you have some admins already seeded
        $adminIds = DB::table('admins')->pluck('id')->toArray();

        // If no admins exist, create a sample admin
        if (empty($adminIds)) {
            $adminId = DB::table('admins')->insertGetId([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminIds = [$adminId];
        }

        // Get department IDs
        $departmentIds = DB::table('admin_departments')->pluck('id')->toArray();

        // Create sample department assignments
        $departmentAdmins = [];

        // Assign the first admin to all departments as head
        foreach ($departmentIds as $departmentId) {
            $departmentAdmins[] = [
                'admin_id' => $adminIds[0],
                'admin_department_id' => $departmentId,
                'role' => 'head',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // If we have more admins, assign them to random departments
        if (count($adminIds) > 1) {
            $roles = ['member', 'executive', 'member', 'member'];

            for ($i = 1; $i < count($adminIds); $i++) {
                // Assign to 1-3 random departments
                $randomDepartments = array_rand($departmentIds, min(3, count($departmentIds)));
                if (!is_array($randomDepartments)) {
                    $randomDepartments = [$randomDepartments];
                }

                foreach ($randomDepartments as $deptIndex) {
                    $departmentAdmins[] = [
                        'admin_id' => $adminIds[$i],
                        'admin_department_id' => $departmentIds[$deptIndex],
                        'role' => $roles[array_rand($roles)],
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('admin_department_admin')->insert($departmentAdmins);
    }
}
