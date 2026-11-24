<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Support\AuditLog;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('AuditLog::record stores event, subject, causer and properties', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();
    $this->actingAs($actor);

    AuditLog::record('export-pendaftaran', $subject, ['count' => 5]);

    $log = Activity::where('event', 'export-pendaftaran')->latest('id')->first();
    expect($log->description)->toBe('export-pendaftaran')
        ->and($log->causer_id)->toBe($actor->id)
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->properties->toArray())->toBe(['count' => 5]);
});

test('AuditLog::record works without subject or user', function () {
    AuditLog::record('login-gagal');

    $log = Activity::where('event', 'login-gagal')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBeNull()
        ->and($log->subject_id)->toBeNull();
});

test('user changes are logged without password or remember_token', function () {
    $user = User::factory()->create(['name' => 'Awal']);
    Activity::query()->delete();

    $user->update(['name' => 'Baru', 'password' => 'rahasia-baru-123']);
    $user->forceFill(['remember_token' => 'tokenrahasia'])->save();

    $log = Activity::where('subject_id', $user->id)->where('event', 'updated')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect(json_encode($log->properties))->toContain('Baru')
        ->not->toContain('password')->not->toContain('rahasia')->not->toContain('remember_token');

    $created = User::factory()->create(['password' => 'sangat-rahasia-1']);
    $createdLog = Activity::where('subject_id', $created->id)->where('event', 'created')->first();
    expect(json_encode($createdLog->properties))->not->toContain('password')->not->toContain('sangat-rahasia');
});
