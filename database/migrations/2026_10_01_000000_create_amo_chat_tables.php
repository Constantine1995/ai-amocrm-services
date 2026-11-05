<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('amo_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('amo_message_id')->unique();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('talk_id')->nullable();
            $table->string('direction');
            $table->string('author_name')->nullable();
            $table->text('text');
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        Schema::create('amo_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->foreignId('amo_chat_message_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('client_reply');
            $table->boolean('answered_by_knowledge_base');
            $table->text('upsell_offer')->nullable();
            $table->text('upsell_reason');
            $table->text('upsell_phrase');
            $table->text('manager_tip');
            $table->string('model');
            $table->boolean('note_sent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amo_suggestions');
        Schema::dropIfExists('amo_chat_messages');
    }
};
