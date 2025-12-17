<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->json('abilities')->nullable()->after('name');
            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'abilities',
                'device_name',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};

