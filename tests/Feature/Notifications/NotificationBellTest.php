<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Technician;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TechnicianAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    CompanySettings::factory()->create();
});

function notifyNow(User $user, object $notification): void
{
    Notification::sendNow($user, $notification, ['database']);
}

it('menampilkan jumlah belum dibaca di lonceng ketiga layout', function (string $role, string $url) {
    $user = match ($role) {
        'admin' => User::factory()->admin()->create(),
        'teknisi' => Technician::factory()->create()->user,
        'client' => User::factory()->create(['phone' => '081234567890']),
    };
    $order = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create();
    notifyNow($user, new TaskAssignedNotification($order));
    notifyNow($user, new TaskAssignedNotification($order));

    $this->actingAs($user)->get($url)->assertOk()
        ->assertSee('data-unread="2"', false)
        ->assertSee('Notifikasi (2 belum dibaca)')
        ->assertSee('href="'.route('notifications.index').'"', false);
})->with([
    ['admin', '/admin'],
    ['teknisi', '/teknisi'],
    ['client', '/beranda'],
]);

it('menampilkan daftar notifikasi sesuai layout peran', function () {
    $client = User::factory()->create(['phone' => '081234567890']);
    $order = Order::factory()->for($client, 'client')->withStatus(OrderStatus::DITUGASKAN)->create();
    notifyNow($client, new TechnicianAssignedNotification($order));

    $this->actingAs($client)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Teknisi ditugaskan')
        ->assertSee($order->code)
        ->assertSee('1 belum dibaca')
        ->assertSee('Tandai semua dibaca');
});

it('membuka notifikasi: ditandai dibaca lalu diarahkan ke order terkait', function () {
    $technician = Technician::factory()->create();
    $order = Order::factory()->withStatus(OrderStatus::DITUGASKAN)->create(['technician_id' => $technician->id]);
    notifyNow($technician->user, new TaskAssignedNotification($order));
    $notification = $technician->user->notifications()->sole();

    $this->actingAs($technician->user)
        ->get(route('notifications.open', $notification->id))
        ->assertRedirect(route('teknisi.tasks.show', $order));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('menandai semua notifikasi dibaca', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create();
    notifyNow($admin, new TaskAssignedNotification($order));
    notifyNow($admin, new TaskAssignedNotification($order));

    $this->actingAs($admin)->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

    expect($admin->unreadNotifications()->count())->toBe(0);
    $this->get('/admin')->assertDontSee('data-unread=', false);
});

it('tidak bisa membuka notifikasi milik pengguna lain', function () {
    $owner = User::factory()->admin()->create();
    notifyNow($owner, new TaskAssignedNotification(Order::factory()->create()));
    $id = $owner->notifications()->sole()->id;

    $this->actingAs(User::factory()->admin()->create())->get(route('notifications.open', $id))->assertNotFound();

    expect($owner->notifications()->sole()->read_at)->toBeNull();
});

it('menolak tautan eksternal pada data notifikasi', function () {
    $admin = User::factory()->admin()->create();
    $admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'x', 'message' => 'x', 'url' => 'https://evil.example/phish'],
    ]);

    $this->actingAs($admin)->get(route('notifications.open', $admin->notifications()->sole()->id))
        ->assertRedirect(route('notifications.index'));
});

it('tamu diarahkan ke login', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('client.login'));
});
