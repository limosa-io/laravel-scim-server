<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'formatted')) {
                    $table->string('formatted')->nullable();
                }
                if (!Schema::hasColumn('users', 'active')) {
                    $table->boolean('active')->default(false);
                }
                if (!Schema::hasColumn('users', 'roles')) {
                    $table->json('roles')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'formatted')) {
                    $table->dropColumn('formatted');
                }
                if (Schema::hasColumn('users', 'active')) {
                    $table->dropColumn('active');
                }
                if (Schema::hasColumn('users', 'roles')) {
                    $table->dropColumn('roles');
                }
            });
        }
    }
};
