@extends('admin.layouts.admin')

@section('title', 'Moderasi Ulasan')
@section('page-title', 'Moderasi Ulasan')
@section('page-subtitle', 'Tinjau, setujui, atau tolak ulasan produk dari pelanggan')

@section('content')

@php
    $filterOptions = [
        'pending'  => ['label' => 'Menunggu Review', 'color' => '#854d0e', 'bg' => '#fefce8', 'border' => '#fef08a'],
        'approved' => ['label' => 'Disetujui',       'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
        'rejected' => ['label' => 'Ditolak',          'color' => '#991b1b', 'bg' => '#fef2f2', 'border' => '#fecaca'],
        'all'      => ['label' => 'Semua',            'color' => '#3730a3', 'bg' => '#eef2ff', 'border' => '#c7d2fe'],
    ];
@endphp

{{-- Filter Tabs --}}
<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:1.25rem; align-items:center;">
    @foreach($filterOptions as $key => $opt)
        @php $isActive = $filter === $key; $count = $counts[$key] ?? 0; @endphp
        <a href="{{ route('admin.reviews.index', ['status' => $key]) }}"
           style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.775rem; font-weight:{{ $isActive ? '700' : '500' }}; text-decoration:none;
                  {{ $isActive ? 'background:#C17B6F; color:#fff;' : 'background:#fff; color:#78716C; border:1px solid #EDE8E4;' }}">
            {{ $opt['label'] }}
            <span style="background:{{ $isActive ? 'rgba(255,255,255,0.25)' : '#F9F6F4' }}; color:{{ $isActive ? '#fff' : '#78716C' }};
                         border-radius:20px; padding:1px 7px; font-size:0.7rem; font-weight:700;">{{ $count }}</span>
        </a>
    @endforeach
</div>

{{-- Reviews Table --}}
<div class="admin-card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
            <thead>
                <tr style="background:#F9F6F4; border-bottom:1px solid #EDE8E4;">
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Pelanggan</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Produk</th>
                    <th style="padding:0.75rem 1rem; text-align:center; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Rating</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; max-width:220px;">Komentar</th>
                    <th style="padding:0.75rem 1rem; text-align:center; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Foto</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Status</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Tanggal</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; min-width:280px; white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    @php
                        if (is_null($review->is_approved)) {
                            $statusLabel = 'Pending';
                            $statusStyle = 'background:#fefce8; color:#854d0e; border:1px solid #fef08a;';
                        } elseif ($review->is_approved) {
                            $statusLabel = 'Disetujui';
                            $statusStyle = 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;';
                        } else {
                            $statusLabel = 'Ditolak';
                            $statusStyle = 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;';
                        }
                    @endphp
                    <tr style="border-bottom:1px solid #EDE8E4;" onmouseover="this.style.background='#FDF8F6'" onmouseout="this.style.background=''">

                        {{-- Pelanggan --}}
                        <td style="padding:0.875rem 1rem;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#E8B4AC,#C17B6F); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700; flex-shrink:0;">
                                    {{ strtoupper(substr($review->user?->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600; color:#1c1917; font-size:0.8125rem;">{{ $review->user?->name ?? '—' }}</div>
                                    <div style="font-size:0.7rem; color:#78716C;">{{ $review->user?->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- Produk --}}
                        <td style="padding:0.875rem 1rem;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                @if($review->product?->image)
                                    <img src="{{ asset('storage/'.$review->product->image) }}"
                                         alt="{{ $review->product->name }}"
                                         style="width:32px; height:32px; border-radius:8px; object-fit:cover; border:1px solid #EDE8E4; flex-shrink:0;">
                                @else
                                    <div style="width:32px; height:32px; border-radius:8px; background:#F9F6F4; border:1px solid #EDE8E4; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                        <svg style="width:14px; height:14px; color:#A39994;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon>
                                        </svg>
                                    </div>
                                @endif
                                <span style="font-size:0.775rem; color:#1c1917; font-weight:500; max-width:130px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $review->product?->name ?? '—' }}">
                                    {{ $review->product?->name ?? '—' }}
                                </span>
                            </div>
                        </td>

                        {{-- Rating --}}
                        <td style="padding:0.875rem 1rem; text-align:center;">
                            <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                                @for($s = 1; $s <= 5; $s++)
                                    <svg style="width:14px; height:14px; fill:{{ $s <= $review->rating ? '#F59E0B' : '#E5E7EB' }}; color:{{ $s <= $review->rating ? '#F59E0B' : '#D1D5DB' }};"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                    </svg>
                                @endfor
                            </div>
                            <div style="font-size:0.7rem; color:#78716C; margin-top:2px;">{{ $review->rating }}/5</div>
                        </td>

                        {{-- Komentar --}}
                        <td style="padding:0.875rem 1rem; max-width:220px;">
                            @if($review->comment)
                                <span style="font-size:0.775rem; color:#3D3A38; display:block; line-height:1.5; word-break:break-word;">
                                    "{{ Str::limit($review->comment, 120) }}"
                                </span>
                            @else
                                <span style="color:#b5b0ad; font-size:0.775rem; font-style:italic;">Tidak ada komentar</span>
                            @endif
                        </td>

                        {{-- Foto --}}
                        <td style="padding:0.875rem 1rem; text-align:center;">
                            @if($review->image)
                                <a href="{{ asset('storage/'.$review->image) }}" target="_blank" style="display:inline-block;">
                                    <img src="{{ asset('storage/'.$review->image) }}" alt="Foto Ulasan"
                                         style="width:40px; height:40px; border-radius:8px; object-fit:cover; border:1px solid #EDE8E4; cursor:pointer;">
                                </a>
                            @else
                                <span style="color:#b5b0ad; font-size:0.775rem;">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td style="padding:0.875rem 1rem;">
                            <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:600; {{ $statusStyle }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        {{-- Tanggal --}}
                        <td style="padding:0.875rem 1rem; color:#78716C; font-size:0.775rem; white-space:nowrap;">
                            {{ $review->created_at?->format('d M Y, H:i') ?? '—' }}
                        </td>

                        {{-- Aksi --}}
                        <td style="padding:0.875rem 1rem;">
                            <div style="display:flex; align-items:center; flex-wrap:nowrap; gap:5px;">

                                {{-- Setujui --}}
                                @unless($review->is_approved === true)
                                <form method="POST" action="{{ route('admin.reviews.approve', $review->id) }}?status={{ $filter }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.72rem; padding:5px 9px; gap:4px;"
                                            title="Setujui ulasan ini">
                                        <span class="material-symbols-outlined" style="font-size:13px;">check_circle</span>
                                        Setujui
                                    </button>
                                </form>
                                @endunless

                                {{-- Tolak --}}
                                @unless($review->is_approved === false)
                                <form method="POST" action="{{ route('admin.reviews.reject', $review->id) }}?status={{ $filter }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" style="display:inline-flex; align-items:center; gap:4px; background:transparent; color:#ef4444; border:1px solid #fee2e2; border-radius:8px; padding:5px 9px; font-size:0.72rem; font-weight:600; cursor:pointer;"
                                            title="Tolak ulasan ini">
                                        <span class="material-symbols-outlined" style="font-size:13px;">cancel</span>
                                        Tolak
                                    </button>
                                </form>
                                @endunless

                                {{-- Hapus --}}
                                <button type="button" title="Hapus ulasan" @click="$dispatch('confirm-delete', {
                                    action: '{{ route('admin.reviews.destroy', $review->id) }}?status={{ $filter }}',
                                    title: 'Hapus Ulasan?',
                                    message: 'Apakah Anda yakin ingin menghapus ulasan ini secara permanen? Tindakan ini tidak dapat dibatalkan.'
                                })" style="display:inline-flex; align-items:center; gap:4px; background:transparent; color:#A39994; border:1px solid #EDE8E4; border-radius:8px; padding:5px 9px; font-size:0.72rem; font-weight:600; cursor:pointer; transition:all 0.15s;"
                                        onmouseover="this.style.color='#ef4444'; this.style.borderColor='#fecaca';"
                                        onmouseout="this.style.color='#A39994'; this.style.borderColor='#EDE8E4';">
                                    <span class="material-symbols-outlined" style="font-size:13px;">delete</span>
                                </button>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:3.5rem; text-align:center; color:#78716C; font-size:0.8125rem;">
                            <span class="material-symbols-outlined" style="display:block; font-size:40px; margin-bottom:10px; color:#EDE8E4;">rate_review</span>
                            @if($filter === 'pending')
                                Tidak ada ulasan yang menunggu moderasi. Semua ulasan sudah ditinjau!
                            @else
                                Tidak ada ulasan dengan status "{{ $filterOptions[$filter]['label'] ?? $filter }}".
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($reviews->hasPages())
        <div style="padding:1rem 1.25rem; border-top:1px solid #EDE8E4;">
            {{ $reviews->links() }}
        </div>
    @endif
</div>

@endsection
