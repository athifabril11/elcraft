<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AddressController — Kelola buku alamat pengiriman pengguna.
 */
class AddressController extends Controller
{
    /**
     * Tampilkan daftar alamat milik pengguna yang sedang login.
     */
    public function index()
    {
        $addresses = Auth::user()->addresses()->orderByDesc('is_default')->get();
        return view('profile.addresses', compact('addresses'));
    }

    /**
     * Simpan alamat baru ke database.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'label'          => 'required|string|max:50',
            'recipient_name' => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'province'       => 'required|string|max:100',
            'city'           => 'required|string|max:100',
            'district'       => 'nullable|string|max:100',
            'postal_code'    => 'required|string|max:10',
            'full_address'   => 'required|string',
            'is_default'     => 'boolean',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $data) {
            // Jika alamat baru ini di-set sebagai default, hapus flag default dari semua alamat lain
            if (!empty($data['is_default'])) {
                $user->addresses()->update(['is_default' => false]);
            }

            // Jika ini adalah alamat pertama, jadikan default secara otomatis
            if ($user->addresses()->count() === 0) {
                $data['is_default'] = true;
            }

            $user->addresses()->create($data);
        });

        return redirect()->route('profile.addresses')
            ->with('success', 'Alamat berhasil ditambahkan.');
    }

    /**
     * Perbarui alamat yang sudah ada.
     */
    public function update(Request $request, Address $address)
    {
        // Pastikan alamat ini milik pengguna yang sedang login
        $this->authorize('update', $address);

        $data = $request->validate([
            'label'          => 'required|string|max:50',
            'recipient_name' => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'province'       => 'required|string|max:100',
            'city'           => 'required|string|max:100',
            'district'       => 'nullable|string|max:100',
            'postal_code'    => 'required|string|max:10',
            'full_address'   => 'required|string',
        ]);

        $address->update($data);

        return redirect()->route('profile.addresses')
            ->with('success', 'Alamat berhasil diperbarui.');
    }

    /**
     * Hapus alamat dari database.
     */
    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);

        $wasDefault = $address->is_default;
        $user = Auth::user();

        $address->delete();

        // Jika alamat yang dihapus adalah default, jadikan alamat pertama yang tersisa sebagai default
        if ($wasDefault) {
            $next = $user->addresses()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->route('profile.addresses')
            ->with('success', 'Alamat berhasil dihapus.');
    }

    /**
     * Set alamat tertentu sebagai default dalam satu transaksi atomik.
     */
    public function setDefault(Address $address)
    {
        $this->authorize('update', $address);

        DB::transaction(function () use ($address) {
            Auth::user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return redirect()->route('profile.addresses')
            ->with('success', 'Alamat utama berhasil diubah.');
    }
}
