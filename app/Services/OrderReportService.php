<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PhotoKind;
use App\Exceptions\ReportIncompleteException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Laporan teknisi per unit: foto sebelum/sesudah, catatan, harga final → kirim = SELESAI.
 */
class OrderReportService
{
    public const MAX_PHOTO_SIZE = 1600;

    public const MAX_PHOTOS_PER_KIND = 6;

    public function __construct(private readonly OrderStatusService $statuses) {}

    /**
     * Simpan foto unit: diperkecil maks. 1600px, dikompres JPEG, disimpan di disk public.
     */
    public function storePhoto(OrderItem $item, PhotoKind $kind, UploadedFile $file, User $uploader): OrderPhoto
    {
        $this->ensureWorking($item->order);

        if ($item->photos()->where('kind', $kind)->count() >= self::MAX_PHOTOS_PER_KIND) {
            throw new ReportIncompleteException('Maksimal '.self::MAX_PHOTOS_PER_KIND.' foto '.strtolower($kind->label()).' per unit.');
        }

        $image = ImageManager::gd()->read($file->getRealPath())->scaleDown(self::MAX_PHOTO_SIZE, self::MAX_PHOTO_SIZE);
        $path = "orders/{$item->order_id}/".strtolower($kind->value).'-'.$item->id.'-'.Str::random(12).'.jpg';
        Storage::disk('public')->put($path, (string) $image->toJpeg(quality: 80));

        return $item->photos()->create([
            'order_id' => $item->order_id,
            'kind' => $kind,
            'path' => $path,
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function deletePhoto(OrderPhoto $photo): void
    {
        $this->ensureWorking($photo->order);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }

    public function saveItem(OrderItem $item, int $finalPrice, ?string $priceNote, ?string $workNote): void
    {
        $this->ensureWorking($item->order);

        $item->update([
            'final_price' => $finalPrice,
            'price_note' => $finalPrice > $item->est_price_max ? $priceNote : ($priceNote ?: null),
            'work_note' => $workNote ?: null,
        ]);
    }

    /**
     * Unit dianggap lengkap bila harga final terisi dan ada minimal 1 foto sesudah.
     */
    public function itemComplete(OrderItem $item): bool
    {
        $hasAfterPhoto = $item->relationLoaded('photos')
            ? $item->photos->contains(fn (OrderPhoto $photo) => $photo->kind === PhotoKind::SESUDAH)
            : $item->photos()->where('kind', PhotoKind::SESUDAH)->exists();

        return $item->final_price !== null
            && $hasAfterPhoto
            && ($item->final_price <= $item->est_price_max || filled($item->price_note));
    }

    /**
     * Kirim laporan: semua unit lengkap → SELESAI, hitung final_price, grand_total, warranty_until.
     *
     * @throws ReportIncompleteException
     */
    public function submit(Order $order, User $technicianUser): Order
    {
        return DB::transaction(function () use ($order, $technicianUser) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureWorking($locked);

            $items = $locked->items()->with('photos')->get();
            $incomplete = $items->reject(fn (OrderItem $item) => $this->itemComplete($item));

            if ($items->isEmpty() || $incomplete->isNotEmpty()) {
                $units = $incomplete->map(fn (OrderItem $item) => 'Unit '.($items->search(fn (OrderItem $candidate) => $candidate->is($item)) + 1))->implode(', ');

                throw new ReportIncompleteException("Lengkapi dulu {$units}: harga final dan minimal 1 foto sesudah.");
            }

            $completedOn = CarbonImmutable::today();
            foreach ($items as $item) {
                $item->update(['warranty_until' => $completedOn->addDays((int) $item->warranty_days)]);
            }

            $finalPrice = (int) $items->sum('final_price');

            return $this->statuses->transition($locked, OrderStatus::SELESAI, $technicianUser, 'Laporan dikirim', [
                'final_price' => $finalPrice,
                'grand_total' => $finalPrice + (int) $locked->transport_fee,
            ]);
        });
    }

    private function ensureWorking(Order $order): void
    {
        if ($order->status !== OrderStatus::DIKERJAKAN) {
            throw new ReportIncompleteException('Laporan hanya bisa diisi saat order berstatus Dikerjakan.');
        }
    }
}
