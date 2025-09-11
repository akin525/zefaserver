<?php

return [
    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'supervisor' => 'Supervisor',
        'staff' => 'Staff',
        'head' => 'Department Head',
        'executive' => 'Executive',
        'member' => 'Member',
    ],

    'permissions' => [
        // User Management
        'view_user' => 'View Users',
        'manage_user' => 'Manage Users',
        'create_user' => 'Create Users',
        'edit_user' => 'Edit Users',
        'delete_user' => 'Delete Users',

        // Admin Management
        'view_admin' => 'View Admins',
        'manage_admin' => 'Manage Admins',
        'create_admin' => 'Create Admins',
        'edit_admin' => 'Edit Admins',
        'delete_admin' => 'Delete Admins',

        // Transaction Management
        'view_transaction' => 'View Transactions',
        'manage_transaction' => 'Manage Transactions',
        'approve_transaction' => 'Approve Transactions',
        'reject_transaction' => 'Reject Transactions',

        // Payout Management
        'view_payout' => 'View Payouts',
        'manage_payout' => 'Manage Payouts',
        'approve_payout' => 'Approve Payouts',
        'process_payout' => 'Process Payouts',

        // Financial
        'manage_fees' => 'Manage Fees',
        'view_settlement' => 'View Settlements',
        'manage_settlement' => 'Manage Settlements',

        // Refunds
        'view_refund' => 'View Refunds',
        'manage_refund' => 'Manage Refunds',
        'process_refund' => 'Process Refunds',

        // KYC
        'view_kyc' => 'View KYC',
        'manage_kyc' => 'Manage KYC',
        'approve_kyc' => 'Approve KYC',
        'reject_kyc' => 'Reject KYC',

        // Reports
        'reporting' => 'View Reports',
        'financial_reports' => 'Financial Reports',
        'user_reports' => 'User Reports',

        // System
        'system_config' => 'System Configuration',
        'audit_logs' => 'View Audit Logs',
        'bulk_operations' => 'Bulk Operations',
        'data_export' => 'Data Export',
    ],

    'role_permissions' => [
        'super_admin' => [
            // All permissions
            'view_user', 'manage_user', 'create_user', 'edit_user', 'delete_user',
            'view_admin', 'manage_admin', 'create_admin', 'edit_admin', 'delete_admin',
            'view_transaction', 'manage_transaction', 'approve_transaction', 'reject_transaction',
            'view_payout', 'manage_payout', 'approve_payout', 'process_payout',
            'manage_fees', 'view_settlement', 'manage_settlement',
            'view_refund', 'manage_refund', 'process_refund',
            'view_kyc', 'manage_kyc', 'approve_kyc', 'reject_kyc',
            'reporting', 'financial_reports', 'user_reports',
            'system_config', 'audit_logs', 'bulk_operations', 'data_export',
        ],

        'admin' => [
            'view_user', 'manage_user', 'create_user', 'edit_user',
            'view_admin', 'create_admin', 'edit_admin',
            'view_transaction', 'manage_transaction', 'approve_transaction',
            'view_payout', 'manage_payout', 'approve_payout',
            'view_settlement', 'view_refund', 'manage_refund',
            'view_kyc', 'manage_kyc', 'approve_kyc',
            'reporting', 'financial_reports', 'user_reports',
            'audit_logs', 'data_export',
        ],

        'manager' => [
            'view_user', 'manage_user', 'edit_user',
            'view_admin', 'view_transaction', 'manage_transaction',
            'view_payout', 'manage_payout', 'view_settlement',
            'view_refund', 'manage_refund', 'view_kyc', 'manage_kyc',
            'reporting', 'user_reports',
        ],

        'supervisor' => [
            'view_user', 'edit_user', 'view_transaction',
            'view_payout', 'view_settlement', 'view_refund',
            'view_kyc', 'manage_kyc', 'reporting',
        ],

        'staff' => [
            'view_user', 'view_transaction', 'view_payout',
            'view_refund', 'view_kyc', 'reporting',
        ],
    ],

    'high_risk_actions' => [
        'delete_user', 'delete_admin', 'manage_fees',
        'system_config', 'bulk_operations', 'process_payout',
        'approve_transaction', 'process_refund', 'approve_boost',
    ],
];
