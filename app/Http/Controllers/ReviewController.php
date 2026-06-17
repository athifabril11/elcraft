<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'order_item_id' => 'required|integer|exists:order_items,id',
            'rating'        => 'required|integer|between:1,5',
            'comment'       => 'nullable|string|max:1000',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $orderItem = OrderItem::findOrFail($request->order_item_id);

        // Enforce authorization check via policy
        if (!Gate::allows('writeReview', [\App\Models\Review::class, $orderItem])) {
            return redirect()->back()->with('error', 'Anda tidak berwenang mengulas produk ini (pastikan pesanan sudah diterima dan belum diulas).');
        }

        // Image upload handling
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('reviews', 'public');
        }

        Review::create([
            'product_id'    => $orderItem->product_id,
            'user_id'       => Auth::id(),
            'order_item_id' => $orderItem->id,
            'rating'        => $request->rating,
            'comment'       => $request->comment,
            'image'         => $imagePath,
            'is_approved'   => false, // Default: requires admin approval
        ]);

        return redirect()->back()->with('success', 'Ulasan Anda berhasil dikirim dan sedang menunggu persetujuan admin.');
    }
}
