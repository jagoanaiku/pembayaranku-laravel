<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pembayaranku_webhook_events', static function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->string('environment', 10);
            $table->string('event_type', 64);
            $table->char('payload_hash', 64);
            $table->string('state', 16)->default('processing');
            $table->timestamps();
            $table->index(['state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaranku_webhook_events');
    }
};
