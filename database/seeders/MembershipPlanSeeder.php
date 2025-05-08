<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;

class MembershipPlanSeeder extends Seeder {
    public function run() {
        // Assuming user ID 1 is the system/admin creator
        $adminId = 1;

        $plans = [
            [
                'name'            => '1-Month Membership',
                'price'           => 50.00,
                'duration_months' => 1,
            ],
            [
                'name'            => '3-Month Membership',
                'price'           => 140.00,
                'duration_months' => 3,
            ],
            [
                'name'            => '6-Month Membership',
                'price'           => 270.00,
                'duration_months' => 6,
            ],
            [
                'name'            => '12-Month Membership',
                'price'           => 500.00,
                'duration_months' => 12,
            ],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'price'           => $plan['price'],
                    'duration_months' => $plan['duration_months'],
                    'created_by'      => $adminId,
                    'updated_by'      => $adminId,
                ]
            );
        }
    }
}
