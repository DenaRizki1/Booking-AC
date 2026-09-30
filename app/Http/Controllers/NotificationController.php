<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi in-app (tabel notifications) untuk ketiga peran.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
            'layout' => match ($user->role) {
                UserRole::ADMIN => 'layouts.admin',
                UserRole::TEKNISI => 'layouts.teknisi',
                UserRole::CLIENT => 'layouts.client',
            },
        ]);
    }

    /**
     * Tandai dibaca lalu buka order terkait.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        // Hanya tautan internal (hindari open redirect).
        return str_starts_with($url, rtrim((string) config('app.url'), '/').'/') || str_starts_with($url, url('/').'/')
            ? redirect()->to($url)
            : to_route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return to_route('notifications.index')->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
