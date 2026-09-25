<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreReviewRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\ReviewService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Order $order, ReviewService $reviews): RedirectResponse
    {
        Gate::authorize('view', $order);

        /** @var User $client */
        $client = $request->user();

        try {
            $reviews->submit($order, $client, $request->integer('rating'), $request->input('comment'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client.orders.show', $order)->with('success', 'Terima kasih atas ulasan Anda!');
    }
}
