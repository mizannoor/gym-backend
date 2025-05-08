<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Status;

class StatusSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $statuses = [
            ['name' => 'active',   'description' => 'Currently active'],
            ['name' => 'inactive', 'description' => 'Not active'],
            ['name' => 'pending',  'description' => 'Awaiting processing'],
            ['name' => 'success',  'description' => 'Completed successfully'],
            ['name' => 'failed',   'description' => 'Completed with errors'],
        ];
        foreach ($statuses as $s) {
            Status::updateOrCreate(['name' => $s['name']], $s);
        }
    }
}
