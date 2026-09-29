<?php

namespace App\Http\Controllers\Teknisi;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rating & ulasan yang diterima teknisi + jumlah tugas selesai.
 */
class ReviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $technician = $request->user()->technician ?? abort(403, 'Akun ini belum terhubung ke data teknisi.');

        return view('teknisi.reviews', [
            'technician' => $technician,
            'reviews' => $technician->reviews()->with(['client:id,name', 'order:id,code,scheduled_start'])->latest()->paginate(20),
            'distribution' => $technician->reviews()->toBase()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating'),
            'completedCount' => $technician->orders()->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS])->count(),
        ]);
    }
}
