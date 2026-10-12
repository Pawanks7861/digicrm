@php
    $brand = $settings ?? \App\Models\BillingSetting::query()->first();
    $logo = ($brand && method_exists($brand, 'logoDataUri')) ? $brand->logoDataUri() : null;
@endphp
@if($logo)
    <img src="{{ $logo }}" alt="" style="height:46px;margin-bottom:8px;">
@endif
