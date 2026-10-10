@extends('admin.layouts.admin')

@section('title', 'Kelola Retur')
@section('page-title', 'Kelola Retur')
@section('page-subtitle', 'Daftar semua pengajuan retur pelanggan — diajukan → disetujui → diproses → selesai')

@section('content')

@php
    $statusMap = [
        'diajukan'  => ['label' => 'Diajukan',   'style' => 'background:#fefce8; color:#854d0e; border:1px solid #fef08a;'],
        'disetujui' => ['label' => 'Disetujui',  'style' => 'background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;'],
        'diproses'  => ['label' => 'Diproses',   'style' => 'background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff;'],
        'selesai'   => ['label' => 'Selesai',    'style' => 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;'],
        'ditolak'   => ['label' => 'Ditolak',    'style' => 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;'],
    ];
    $resMap = [
        'tukar_barang' => ['label' => 'Tukar Barang', 'style' => 'background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe;'],
        'refund'       => ['label' => 'Refund',       'style' => 'background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe;'],
    ];
    $allStatuses = ['' => 'Semua', 'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
@endphp

{{-- ── Status filter tabs ── --}}
<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:1.25rem; align-items:center;">
    @foreach($allStatuses as $key => $label)
        @php
            $isActive = ($filter ?? '') === $key;
            $count    = $key === '' ? $counts->sum() : ($counts[$key] ?? 0);
        @endphp
        <a href="{{ route('admin.returns.index', $key ? ['status' => $key] : []) }}"
           style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:0.775rem; font-weight:{{ $isActive ? '700' : '500' }}; text-decoration:none;
                  {{ $isActive ? 'background:#C17B6F; color:#fff;' : 'background:#fff; color:#78716C; border:1px solid #EDE8E4;' }}">
            {{ $label }}
            <span style="background:{{ $isActive ? 'rgba(255,255,255,0.25)' : '#F9F6F4' }}; color:{{ $isActive ? '#fff' : '#78716C' }};
                         border-radius:20px; padding:1px 7px; font-size:0.7rem; font-weight:700;">{{ $count }}</span>
        </a>
    @endforeach
</div>

<div class="admin-card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
            <thead>
                <tr style="background:#F9F6F4; border-bottom:1px solid #EDE8E4;">
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">No. Retur</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">No. Pesanan</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Pelanggan</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Item</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; max-width:180px;">Alasan</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Solusi</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Status</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Tanggal</th>
                    <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; min-width:200px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $ret)
                    @php
                        $s   = $statusMap[$ret->status] ?? ['label' => ucfirst($ret->status), 'style' => 'background:#f5f5f5; color:#78716C;'];
                        $res = $resMap[$ret->resolution_type] ?? ['label' => ucfirst($ret->resolution_type), 'style' => 'background:#f5f5f5; color:#78716C;'];
                    @endphp
                    <tr style="border-bottom:1px solid #EDE8E4;" onmouseover="this.style.background='#FDF8F6'" onmouseout="this.style.background=''">

                        {{-- No. Retur --}}
                        <td style="padding:0.875rem 1rem;">
                            <span style="font-family:monospace; font-size:0.775rem; font-weight:600; color:#C17B6F;">{{ $ret->return_number }}</span>
                        </td>

                        {{-- No. Pesanan --}}
                        <td style="padding:0.875rem 1rem;">
                            <span style="font-family:monospace; font-size:0.775rem; color:#1c1917;">{{ $ret->order?->order_number ?? '—' }}</span>
                        </td>

                        {{-- Pelanggan --}}
                        <td style="padding:0.875rem 1rem; color:#1c1917; font-weight:500;">
                            {{ $ret->user?->name ?? '—' }}
                        </td>

                        {{-- Item list --}}
                        <td style="padding:0.875rem 1rem;">
                            <div style="display:flex; flex-direction:column; gap:2px;">
                                @foreach($ret->items as $ri)
                                    <span style="font-size:0.75rem; color:#1c1917;">
                                        {{ $ri->orderItem?->product_name ?? 'Item' }}
                                        <span style="color:#78716C;">×{{ $ri->quantity }}</span>
                                    </span>
                                @endforeach
                                @if($ret->items->isEmpty())
                                    <span style="color:#b5b0ad; font-size:0.75rem;">—</span>
                                @endif
                            </div>
                        </td>

                        {{-- Alasan (truncated) --}}
                        <td style="padding:0.875rem 1rem; max-width:180px;">
                            <span style="font-size:0.775rem; color:#78716C; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;" title="{{ $ret->reason }}">
                                {{ $ret->reason }}
                            </span>
                            @if($ret->evidence_photo_path)
                                <a href="{{ Storage::disk('public')->url($ret->evidence_photo_path) }}"
                                   target="_blank"
                                   style="display:inline-flex; align-items:center; gap:3px; margin-top:4px; font-size:0.7rem; color:#C17B6F; text-decoration:none;">
                                    <span class="material-symbols-outlined" style="font-size:12px;">photo_camera</span>
                                    Foto Bukti
                                </a>
                            @endif
                        </td>

                        {{-- Solusi Diminta --}}
                        <td style="padding:0.875rem 1rem;">
                            <span style="display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.7rem; font-weight:600; {{ $res['style'] }}">
                                {{ $res['label'] }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td style="padding:0.875rem 1rem;">
                            <span style="display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.7rem; font-weight:600; {{ $s['style'] }}">
                                {{ $s['label'] }}
                            </span>
                            @if($ret->refund_status)
                                <span style="display:block; margin-top:4px; padding:2px 9px; border-radius:20px; font-size:0.7rem; font-weight:600; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; width:fit-content;">
                                    {{ $ret->refund_status === 'api' ? 'Refund API' : 'Refund Manual' }}
                                </span>
                            @endif
                        </td>

                        {{-- Tanggal --}}
                        <td style="padding:0.875rem 1rem; color:#78716C; font-size:0.775rem; white-space:nowrap;">
                            {{ $ret->created_at->format('d M Y, H:i') }}
                        </td>

                        {{-- Aksi --}}
                        <td style="padding:0.875rem 1rem;">
                            @if($ret->status === 'diajukan')
                                {{-- Setujui --}}
                                <details style="display:inline; margin-right:6px;">
                                    <summary style="list-style:none; display:inline-flex; align-items:center; gap:5px; cursor:pointer;
                                                    background:#C17B6F; color:#fff; border:none; border-radius:7px;
                                                    padding:5px 12px; font-size:0.75rem; font-weight:600;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">check_circle</span>
                                        Setujui
                                    </summary>
                                    <div style="position:relative; z-index:10; background:#fff; border:1px solid #EDE8E4; border-radius:10px;
                                                box-shadow:0 4px 16px rgba(0,0,0,0.10); padding:1rem; min-width:240px; margin-top:6px;">
                                        <form method="POST" action="{{ route('admin.returns.approve', $ret->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if($ret->resolution_type === 'refund')
                                                <div style="margin-bottom:10px;">
                                                    <label class="admin-label">Jumlah Refund (Rp) *</label>
                                                    <input type="number" name="refund_amount" min="0" step="1"
                                                           class="admin-input" placeholder="Contoh: 150000" required>
                                                </div>
                                            @endif
                                            <div style="margin-bottom:10px;">
                                                <label class="admin-label">Catatan Admin (opsional)</label>
                                                <textarea name="admin_notes" rows="2" class="admin-input"
                                                          placeholder="Catatan untuk pelanggan..."></textarea>
                                            </div>
                                            <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.375rem 0.875rem; width:100%;">
                                                Konfirmasi Setujui
                                            </button>
                                        </form>
                                    </div>
                                </details>

                                {{-- Tolak --}}
                                <details style="display:inline;">
                                    <summary style="list-style:none; display:inline-flex; align-items:center; gap:5px; cursor:pointer;
                                                    background:transparent; color:#ef4444; border:1px solid #fee2e2; border-radius:7px;
                                                    padding:5px 12px; font-size:0.75rem; font-weight:600;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">cancel</span>
                                        Tolak
                                    </summary>
                                    <div style="position:relative; z-index:10; background:#fff; border:1px solid #EDE8E4; border-radius:10px;
                                                box-shadow:0 4px 16px rgba(0,0,0,0.10); padding:1rem; min-width:240px; margin-top:6px;">
                                        <form method="POST" action="{{ route('admin.returns.reject', $ret->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div style="margin-bottom:10px;">
                                                <label class="admin-label">Alasan Penolakan *</label>
                                                <textarea name="admin_notes" rows="3" class="admin-input"
                                                          placeholder="Jelaskan alasan penolakan..." required minlength="5"></textarea>
                                            </div>
                                            <button type="submit" class="admin-btn-danger" style="width:100%; justify-content:center; padding:0.375rem 0.875rem;">
                                                Konfirmasi Tolak
                                            </button>
                                        </form>
                                    </div>
                                </details>

                            @elseif($ret->status === 'disetujui')
                                <form method="POST" action="{{ route('admin.returns.advance', $ret->id) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.375rem 0.875rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">inbox</span>
                                        Tandai Diproses
                                    </button>
                                </form>

                            @elseif($ret->status === 'diproses')
                                <form method="POST" action="{{ route('admin.returns.advance', $ret->id) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.375rem 0.875rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">task_alt</span>
                                        Tandai Selesai
                                    </button>
                                </form>

                            @else
                                <span style="font-size:0.75rem; color:#b5b0ad;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:3rem; text-align:center; color:#78716C; font-size:0.8125rem;">
                            <span class="material-symbols-outlined" style="display:block; font-size:36px; margin-bottom:8px; color:#EDE8E4;">assignment_return</span>
                            Belum ada pengajuan retur{{ $filter ? ' dengan status "' . $filter . '"' : '' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($returns->hasPages())
        <div style="padding:1rem 1.25rem; border-top:1px solid #EDE8E4;">
            {{ $returns->links() }}
        </div>
    @endif
</div>

@endsection