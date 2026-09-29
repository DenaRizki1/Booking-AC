<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar ulasan client, filter per teknisi & rating.
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'technician' => ['nullable', 'integer'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $scoped = Review::query()
            ->when($filters['technician'] ?? null, fn (Builder $query, int $technician) => $query->where('technician_id', $technician));

        $distribution = (clone $scoped)->toBase()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        $reviews = (clone $scoped)
            ->when($filters['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('rating', $rating))
            ->with(['client:id,name,avatar', 'technician.user:id,name', 'order:id,code'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'filters' => $filters,
            'distribution' => $distribution,
            'total' => (int) $distribution->sum(),
            'average' => (float) (clone $scoped)->avg('rating'),
            'technicians' => Technician::query()->with('user:id,name')->get()->sortBy('user.name'),
        ]);
    }
}
