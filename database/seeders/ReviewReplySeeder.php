<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReviewReply;

class ReviewReplySeeder extends Seeder
{
    public function run()
    {
        // create 10 replies linked to random reviews
        ReviewReply::factory()->count(10)->create();
    }
}
