<?php

namespace Database\Seeders;

use App\Enums\TransactionCategory;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed categories from TransactionCategory enum.
     */
    public function run(): void
    {
        $categories = [];

        foreach (TransactionCategory::cases() as $case) {
            $categories[] = [
                'name' => $case->name,
                'slug' => $case->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Category::upsert($categories, ['slug'], ['name', 'updated_at']);
    }
}
