<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $now = now();
        $categories = [];

        for ($i = 1; $i <= 20; $i++) {
            $categories[] = [
                'id' => $i,
                'parent_id' => null,
                'name' => "Category {$i}",
                'slug' => "category-{$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('categories')->insert($categories);
    }
}
