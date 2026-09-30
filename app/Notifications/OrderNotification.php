<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi order: in-app (database) + email, diantrekan.
 * Isi `data` notifikasi: order_id, code, title, message, icon, url (sesuai peran penerima).
 */
abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public ?string $note = null)
    {
        $this->afterCommit();
    }

    abstract public function title(): string;

    abstract public function message(User $notifiable): string;

    /** Ikon Lucide untuk daftar notifikasi in-app. */
    public function icon(): string
    {
        return 'bell';
    }

    /** Level email: primary | success | error (warna tombol). */
    protected function level(): string
    {
        return 'primary';
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'code' => $this->order->code,
            'title' => $this->title(),
            'message' => $this->message($notifiable),
            'note' => $this->note,
            'icon' => $this->icon(),
            'url' => $this->urlFor($notifiable),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->level($this->level())
            ->subject("{$this->title()} · {$this->order->code}")
            ->greeting('Halo, '.strtok($notifiable->name, ' ').'!')
            ->line($this->message($notifiable));

        if ($this->note) {
            $mail->line("Keterangan: {$this->note}");
        }

        return $mail->action($notifiable->role === UserRole::TEKNISI ? 'Buka tugas' : 'Lihat order', $this->urlFor($notifiable));
    }

    public function urlFor(User $notifiable): string
    {
        return match ($notifiable->role) {
            UserRole::ADMIN => route('admin.orders.show', $this->order),
            UserRole::TEKNISI => route('teknisi.tasks.show', $this->order),
            UserRole::CLIENT => route('client.orders.show', $this->order),
        };
    }

    protected function schedule(): string
    {
        return Format::schedule($this->order->scheduled_start, $this->order->scheduled_end, true);
    }

    protected function technicianName(): string
    {
        return $this->order->technician?->user->name ?? 'Teknisi';
    }
}
