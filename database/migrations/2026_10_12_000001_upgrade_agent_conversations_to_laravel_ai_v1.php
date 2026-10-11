<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * laravel/ai 1.x stores coach conversations against a participant (a
 * morph: type and id) instead of a user_id, and its messages keep `steps` and
 * a `status` instead of `tool_calls` and `tool_results`. Existing rows move
 * over: every old user_id becomes a User participant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conversations = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messages = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $userType = (new User)->getMorphClass();

        Schema::table($conversations, function (Blueprint $table): void {
            $table->string('participant_type')->nullable()->after('id');
            $table->unsignedBigInteger('participant_id')->nullable()->after('participant_type');
        });
        DB::table($conversations)->whereNotNull('user_id')->update(['participant_type' => $userType, 'participant_id' => DB::raw('user_id')]);
        Schema::table($conversations, function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropColumn('user_id');
            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Schema::table($messages, function (Blueprint $table): void {
            $table->string('participant_type')->nullable()->after('conversation_id');
            $table->unsignedBigInteger('participant_id')->nullable()->after('participant_type');
            $table->longText('steps')->nullable();
            $table->string('status', 25)->default('completed');
        });
        DB::table($messages)->whereNotNull('user_id')->update(['participant_type' => $userType, 'participant_id' => DB::raw('user_id')]);
        DB::table($messages)->update(['steps' => '[]']);
        Schema::table($messages, function (Blueprint $table): void {
            $table->dropIndex('conversation_index');
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'tool_calls', 'tool_results']);
            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    public function down(): void
    {
        $conversations = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messages = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($messages, function (Blueprint $table): void {
            $table->dropIndex('participant_index');
            $table->dropIndex('conversation_index');
            $table->foreignId('user_id')->nullable();
            $table->text('tool_calls')->nullable();
            $table->text('tool_results')->nullable();
        });
        DB::table($messages)->update(['user_id' => DB::raw('participant_id')]);
        Schema::table($messages, function (Blueprint $table): void {
            $table->dropColumn(['participant_type', 'participant_id', 'steps', 'status']);
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });

        Schema::table($conversations, function (Blueprint $table): void {
            $table->dropIndex('participant_updated_at_index');
            $table->foreignId('user_id')->nullable();
        });
        DB::table($conversations)->update(['user_id' => DB::raw('participant_id')]);
        Schema::table($conversations, function (Blueprint $table): void {
            $table->dropColumn(['participant_type', 'participant_id']);
            $table->index(['user_id', 'updated_at']);
        });
    }
};
