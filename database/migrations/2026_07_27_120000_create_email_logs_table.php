<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->default('laravel');
            $table->string('status', 32);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->string('mailable_class')->nullable();
            $table->string('template_slug')->nullable();
            $table->string('mailer', 64)->nullable();
            $table->boolean('queued')->default(false);
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index('recipient');
            $table->index('mailable_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
