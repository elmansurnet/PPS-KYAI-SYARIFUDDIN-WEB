<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Catatan izin bawaan yang pernah diberikan seeder ke sebuah role (Modules\Core\Support\DefaultPermissions).
 * Seeder hanya memberi pasangan (role, izin) yang belum tercatat, sehingga izin yang dicabut superadmin
 * dari panel tidak diberikan lagi oleh `db:seed` berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seeded_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_name');
            $table->string('permission_name');
            $table->string('guard_name', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['role_name', 'permission_name', 'guard_name'], 'seeded_role_permissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seeded_role_permissions');
    }
};
