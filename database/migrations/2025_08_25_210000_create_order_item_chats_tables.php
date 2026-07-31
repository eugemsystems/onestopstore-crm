<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages per Order Product Item
        Schema::create('order_item_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_product_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->timestamps();

            $table->foreign('order_product_id')->references('id')->on('order_products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['order_product_id','created_at']);
        });

        // Likes on a message
        Schema::create('order_item_message_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('order_item_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['message_id','user_id']);
        });

        // Views of a message
        Schema::create('order_item_message_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('order_item_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['message_id','user_id']);
        });

        // Mentions in a message (for notifications)
        Schema::create('order_item_message_mentions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('mentioned_user_id');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('order_item_messages')->onDelete('cascade');
            $table->foreign('mentioned_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['message_id','mentioned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_message_mentions');
        Schema::dropIfExists('order_item_message_views');
        Schema::dropIfExists('order_item_message_likes');
        Schema::dropIfExists('order_item_messages');
    }
};
