<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Tampilkan daftar banner hero carousel beranda el Craft.
     */
    public function index()
    {
        $banners = Banner::orderBy('sort_order', 'asc')
            ->orderByDesc('created_at')
            ->paginate(10);

        $totalActiveBanners = Banner::where('is_active', true)->count();

        return view('admin.banners.index', compact('banners', 'totalActiveBanners'));
    }

    /**
     * Simpan banner baru beserta upload gambar.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'eyebrow'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url'  => ['nullable', 'string', 'max:255'],
            'image'       => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $imagePath = $request->file('image')->store('banners', 'public');

        Banner::create([
            'title'       => $validated['title'],
            'eyebrow'     => $validated['eyebrow'] ?? null,
            'description' => $validated['description'] ?? null,
            'button_text' => $validated['button_text'] ?: 'LIHAT KOLEKSI',
            'button_url'  => $validated['button_url'] ?: '/products',
            'image_path'  => $imagePath,
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => $request->has('is_active'),
        ]);

        return redirect()->route('admin.banners.index')->with('success', 'Banner promosi berhasil ditambahkan.');
    }

    /**
     * Perbarui konten banner dan gambar (jika ada file baru diunggah).
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'eyebrow'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url'  => ['nullable', 'string', 'max:255'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'title'       => $validated['title'],
            'eyebrow'     => $validated['eyebrow'] ?? null,
            'description' => $validated['description'] ?? null,
            'button_text' => $validated['button_text'] ?: 'LIHAT KOLEKSI',
            'button_url'  => $validated['button_url'] ?: '/products',
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => $request->has('is_active'),
        ];

        if ($request->hasFile('image')) {
            // Hapus file gambar lama jika ada
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $updateData['image_path'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($updateData);

        return redirect()->route('admin.banners.index')->with('success', 'Banner promosi berhasil diperbarui.');
    }

    /**
     * Toggle status aktif / nonaktif banner.
     */
    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);

        $status = $banner->is_active ? 'ditayangkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Banner berhasil {$status}.");
    }

    /**
     * Hapus banner beserta file gambarnya dari storage.
     */
    public function destroy(Banner $banner)
    {
        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return redirect()->back()->with('success', 'Banner berhasil dihapus.');
    }
}
