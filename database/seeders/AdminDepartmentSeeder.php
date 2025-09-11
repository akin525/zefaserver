<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        $departments = [
            [
                'name' => 'Finance',
                'description' => 'Handles all financial operations, accounting, and budgeting',
                'permissions' => json_encode([
                    'financial' => ['view_settlement', 'manage_fees', 'view_payout', 'manage_payout'],
                    'reporting' => ['financial_reports']
                ]),
                'visibility' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Operations',
                'description' => 'Manages day-to-day business operations and logistics',
                'permissions' => json_encode([
                    'transaction' => ['view_transaction', 'manage_transaction'],
                    'reporting' => ['operational_reports']
                ]),
                'visibility' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Customer Support',
                'description' => 'Handles customer inquiries, complaints, and support tickets',
                'permissions' => json_encode([
                    'user' => ['view_user'],
                    'transaction' => ['view_transaction'],
                    'support' => ['view_tickets', 'manage_tickets']
                ]),
                'visibility' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Technology',
                'description' => 'Manages IT infrastructure, software development, and technical support',
                'permissions' => json_encode([
                    'system' => ['system_config', 'view_logs'],
                    'reporting' => ['technical_reports']
                ]),
                'visibility' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Human Resources',
                'description' => 'Handles recruitment, employee relations, and HR policies',
                'permissions' => json_encode([
                    'admin' => ['view_admin'],
                    'reporting' => ['hr_reports']
                ]),
                'visibility' => 'restricted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Manages brand promotion, marketing campaigns, and communications',
                'permissions' => json_encode([
                    'user' => ['view_user'],
                    'reporting' => ['marketing_reports']
                ]),
                'visibility' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Legal',
                'description' => 'Handles legal matters, compliance, and regulatory affairs',
                'permissions' => json_encode([
                    'kyc' => ['view_kyc', 'manage_kyc'],
                    'compliance' => ['view_compliance_reports']
                ]),
                'visibility' => 'restricted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Executive',
                'description' => 'Senior management and executive leadership team',
                'permissions' => json_encode([
                    'admin' => ['view_admin', 'manage_admin'],
                    'financial' => ['view_settlement', 'manage_fees'],
                    'reporting' => ['executive_reports']
                ]),
                'visibility' => 'private',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('admin_departments')->insert($departments);
    }
}
