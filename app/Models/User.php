<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Mendapatkan keranjang belanja yang dimiliki oleh pengguna.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cart(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Mendapatkan keranjang belanja pengguna saat ini, atau membuat baru jika belum ada.
     *
     * @return \App\Models\Cart
     */
    public function getOrCreateCart(): Cart
    {
        $cart = $this->cart;
        if (!$cart) {
            $cart = $this->cart()->first();
        }
        if (!$cart) {
            $cart = $this->cart()->create();
            $this->setRelation('cart', $cart);
        }
        return $cart;
    }

    /**
     * Mendapatkan daftar pesanan pengguna.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Mendapatkan daftar alamat pengiriman pengguna.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class);
    }
}
