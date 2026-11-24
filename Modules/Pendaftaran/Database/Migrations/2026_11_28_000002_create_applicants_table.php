<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();

            // NIK terenkripsi (payload Laravel panjang, jadi text) + hash HMAC hex 64 karakter untuk cek duplikat.
            $table->text('nik');
            $table->string('nik_hash', 64)->unique();

            $table->string('nama_lengkap');
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');

            $this->address($table, '');

            $table->boolean('pernah_mondok')->default(false);
            $table->string('pondok_sebelumnya')->nullable();
            $table->string('pendidikan_pondok_sebelumnya')->nullable();
            $table->foreignId('pendidikan_terakhir_id')->nullable()->constrained('pendidikan_terakhirs')->restrictOnDelete();
            $table->foreignId('jenjang_id')->nullable()->constrained('jenjangs')->restrictOnDelete();
            $table->string('kelas', 100)->nullable();

            $table->string('ayah_nama')->nullable();
            $table->string('ibu_nama')->nullable();
            $table->string('ayah_pekerjaan')->nullable();
            $table->string('ibu_pekerjaan')->nullable();
            $table->string('ayah_hp', 30)->nullable();
            $table->string('ibu_hp', 30)->nullable();
            $this->address($table, 'ortu_');

            $table->boolean('orang_tua_meninggal')->default(false);
            $table->string('wali_nama')->nullable();
            $table->string('wali_pekerjaan')->nullable();
            $table->string('wali_hp', 30)->nullable();
            $this->address($table, 'wali_');

            $table->string('kk_path')->nullable();

            $table->boolean('sudah_dihubungi')->default(false);
            $table->boolean('sudah_bayar')->default(false);
            $table->boolean('sudah_masuk')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }

    private function address(Blueprint $table, string $prefix): void
    {
        $table->string($prefix.'jalan_dusun')->nullable();
        $table->string($prefix.'rt', 10)->nullable();
        $table->string($prefix.'rw', 10)->nullable();
        $table->string($prefix.'desa', 100)->nullable();
        $table->string($prefix.'kecamatan', 100)->nullable();
        $table->string($prefix.'kabupaten', 100)->nullable();
        $table->string($prefix.'provinsi', 100)->nullable();
    }
};
