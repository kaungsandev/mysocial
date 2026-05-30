<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // categories to seed
        $categoriesToAdd = [
            'Graphic Design',
            'Crafts',
            'Politics',
            'Political Science',
            'Mathematics',
            'Zoology',
            'Business',
            'Dance',
            'Banking',
            'HR Management',
            'Art',
            'Science',
            'Music',
            'Operating Systems',
            'Fashion Design',
            'Programming',
            'Painting',
            'Photography',
            'Drawing',
            'GST',
        ];

        foreach ($categoriesToAdd as $category) {
            Category::factory()->create([
                'name' => $category,
                'slug' => Str::slug($category),
            ]);
        }

        // Create 30 more random categories to reach 50
        Category::factory()->count(30)->create();
    }
}
