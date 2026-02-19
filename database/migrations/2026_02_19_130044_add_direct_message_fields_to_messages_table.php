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
        if (Schema::hasColumn('messages', 'sender_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->after('sender_id')->constrained('users')->cascadeOnDelete();
            $table->index(['sender_id', 'recipient_id', 'created_at'], 'messages_sender_recipient_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('messages', 'sender_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_sender_recipient_created_idx');
            $table->dropConstrainedForeignId('sender_id');
            $table->dropConstrainedForeignId('recipient_id');
        });
    }
};
