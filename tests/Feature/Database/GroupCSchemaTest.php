<?php

use App\Models\Helper;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('membuat tabel technicians sesuai ERD', function () {
    expectTableStructure('technicians', [
        'id' => ['bigint unsigned', false],
        'user_id' => ['bigint unsigned', false],
        'photo' => ['varchar(255)', true],
        'is_active' => ['tinyint(1)', false],
        'avg_rating' => ['decimal(3,2)', false],
        'review_count' => ['int unsigned', false],
        'notes' => ['text', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
        'deleted_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('technicians'))->toHaveKey('technicians_user_id_unique');
});

it('membuat tabel helpers sesuai ERD', function () {
    expectTableStructure('helpers', [
        'id' => ['bigint unsigned', false],
        'name' => ['varchar(120)', false],
        'phone' => ['varchar(20)', true],
        'is_active' => ['tinyint(1)', false],
        'notes' => ['text', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
        'deleted_at' => ['timestamp', true],
    ]);
});

it('membuat tabel staff_leaves sesuai ERD', function () {
    expectTableStructure('staff_leaves', [
        'id' => ['bigint unsigned', false],
        'staff_type' => ['varchar(191)', false],
        'staff_id' => ['bigint unsigned', false],
        'leave_date' => ['date', false],
        'reason' => ['varchar(255)', true],
        'created_at' => ['timestamp', true],
        'updated_at' => ['timestamp', true],
    ]);

    expect(tableIndexes('staff_leaves')['staff_leaves_staff_type_staff_id_leave_date_unique'])
        ->toBe(['staff_type', 'staff_id', 'leave_date']);
});

it('menolak cuti ganda di tanggal yang sama untuk orang yang sama', function () {
    $row = ['staff_type' => 'helper', 'staff_id' => 1, 'leave_date' => '2026-10-01'];

    DB::table('staff_leaves')->insert($row);
    DB::table('staff_leaves')->insert($row);
})->throws(QueryException::class);

it('menahan hapus user yang masih punya profil teknisi', function () {
    $userId = DB::table('users')->insertGetId(['name' => 'Andi', 'email' => 'andi@example.com', 'role' => 'teknisi']);
    DB::table('technicians')->insert(['user_id' => $userId]);

    DB::table('users')->where('id', $userId)->delete();
})->throws(QueryException::class);

it('memakai alias morph technician, helper, dan user', function () {
    expect(Relation::getMorphedModel('technician'))->toBe(Technician::class)
        ->and(Relation::getMorphedModel('helper'))->toBe(Helper::class)
        ->and(Relation::getMorphedModel('user'))->toBe(User::class)
        ->and((new Technician)->getMorphClass())->toBe('technician')
        ->and((new Helper)->getMorphClass())->toBe('helper')
        ->and(Relation::requiresMorphMap())->toBeTrue();
});
