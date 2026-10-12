<?php

return [
    'employment_types' => [
        'full_time', 'part_time', 'contract', 'intern', 'consultant',
    ],

    'employment_statuses' => [
        'active', 'probation', 'on_notice', 'suspended', 'exited',
    ],

    'working_arrangements' => [
        'office', 'wfh', 'field',
    ],

    'shift_types' => [
        'fixed', 'flexible', 'overnight',
    ],

    'attendance_day_statuses' => [
        'present_full', 'present_half', 'absent', 'paid_leave', 'unpaid_leave',
        'holiday', 'weekly_off', 'on_duty', 'exception',
    ],

    'punch_types' => [
        'check_in', 'check_out', 'break_start', 'break_end',
    ],

    'leave_units' => [
        'full_day', 'first_half', 'second_half',
    ],

    'leave_request_statuses' => [
        'draft', 'pending', 'approved', 'rejected', 'cancelled',
    ],

    'approval_statuses' => [
        'pending', 'approved', 'rejected', 'cancelled', 'escalated',
    ],

    'document_statuses' => [
        'pending', 'verified', 'rejected', 'expired',
    ],

    'expense_statuses' => [
        'draft', 'submitted', 'approved', 'rejected', 'scheduled', 'paid',
    ],

    'payroll_run_statuses' => [
        'draft', 'review', 'approved', 'locked',
    ],

    'default_attendance_rules' => [
        'full_day_minutes' => 480,
        'half_day_minutes' => 240,
        'grace_minutes' => 10,
        'break_deduct_after_minutes' => 60,
        'missing_checkout_treatment' => 'exception', // exception|half_day|absent
        'timezone' => 'Asia/Kolkata',
    ],
];
