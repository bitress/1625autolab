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
        // 1. conversations
        Schema::create('conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('session_id', 128)->unique('uq_conversations_session_id');
            $table->string('status', 20)->default('bot')->comment('bot | human | closed');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 2. messages
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id')->index('idx_messages_conversation_id');
            $table->string('sender', 20)->comment('user | bot | human');
            $table->text('content');
            $table->string('message_type', 30)->default('text')->comment('text | quick_reply | card | button');
            $table->text('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id', 'fk_messages_conversation')
                ->references('id')->on('conversations')
                ->onDelete('cascade');
        });

        // 3. flows
        Schema::create('flows', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->longText('flow_json');
            $table->boolean('is_active')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 4. user_sessions
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('session_id', 128)->unique('uq_user_sessions_session_id');
            $table->text('variables_json')->default('{}');
            $table->string('current_node_id', 200)->nullable();
            $table->unsignedInteger('conversation_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('conversation_id', 'fk_user_sessions_conversation')
                ->references('id')->on('conversations')
                ->onDelete('cascade');
        });

        // 5. customer_profiles
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('session_id', 128)->unique('uq_customer_profiles_session_id');
            $table->string('name', 200)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_make', 100)->nullable();
            $table->string('vehicle_model', 100)->nullable();
            $table->string('vehicle_year', 10)->nullable();
            $table->string('vehicle_info', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 6. conversation_presence
        Schema::create('conversation_presence', function (Blueprint $table) {
            $table->increments('id');
            $table->string('session_id', 128)->unique('uq_conversation_presence_session_id');
            $table->boolean('customer_typing')->default(false);
            $table->boolean('agent_typing')->default(false);
            $table->timestamp('customer_last_read_at')->nullable();
            $table->timestamp('agent_last_read_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 7. service_availability_rules
        Schema::create('service_availability_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('service_id')->index('idx_service_availability_service_id');
            $table->unsignedTinyInteger('day_of_week')->nullable()->comment('0=Monday … 6=Sunday');
            $table->unsignedTinyInteger('start_hour')->nullable()->comment('0-23');
            $table->unsignedTinyInteger('end_hour')->nullable()->comment('0-23');
            $table->boolean('is_available')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('service_id', 'fk_service_availability_service')
                ->references('id')->on('services')
                ->onDelete('cascade');
        });

        // 8. appointment_action_requests
        Schema::create('appointment_action_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('session_id', 128)->index('idx_appt_action_session_id');
            $table->string('action', 30)->comment('reschedule | cancel');
            $table->string('requested_date', 20)->nullable();
            $table->string('requested_time', 20)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending | processed | rejected');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_action_requests');
        Schema::dropIfExists('service_availability_rules');
        Schema::dropIfExists('conversation_presence');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('flows');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
