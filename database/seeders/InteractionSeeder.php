<?php

namespace Database\Seeders;

use App\Models\Interaction;
use Illuminate\Database\Seeder;

class InteractionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Interaction::factory()->count(5)->create();
    }
}
