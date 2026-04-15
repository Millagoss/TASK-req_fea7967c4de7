<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('password_hash');
            $table->string('display_name', 255)->nullable();
            $table->boolean('is_service_account')->default(false);
            $table->string('service_credential_hash')->nullable();
            $table->timestamp('service_credential_rotated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_service_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
