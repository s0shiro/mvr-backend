<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., 'booking_created', 'booking_cancelled'
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Who should receive the notification
            $table->morphs('notifiable'); // Polymorphic relationship to the related model (e.g., booking)
            $table->text('data'); // JSON data about the notification
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
