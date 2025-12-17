<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('actor_type')->nullable()->comment('optional polymorphic actor type');
            $table->unsignedBigInteger('actor_id')->nullable()->comment('optional polymorphic actor id');
            $table->json('meta')->nullable(); // arbitrary data
            $table->text('sensitive')->nullable(); // encrypted sensitive data
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'event'], 'activity_logs_user_event_index');
            $table->index(['event', 'created_at'], 'activity_logs_event_created_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
}
