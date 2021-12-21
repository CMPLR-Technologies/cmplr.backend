<?php

namespace Database\Seeders;

use App\Models\Posts;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call(UserSeeder::class);
        $this->call(BlogSeeder::class);
        $this->call(BlogUserSeeder::class);
        $this->call(BlogSettingsSeeder::class);
        $this->call(TagSeeder::class);
        $this->call(PostsSeeder::class);
    }
}
