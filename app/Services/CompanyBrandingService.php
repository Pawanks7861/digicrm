<?php

namespace App\Services;

use App\Models\BillingSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyBrandingService
{
    public function storeLogo(BillingSetting $settings, UploadedFile $file): BillingSetting
    {
        $path = $this->storeImage($file, 'logo', 5120, true);
        $this->deletePublic($settings->logo_path);
        $settings->update(['logo_path' => $path]);

        return $settings->fresh();
    }

    public function storeFavicon(BillingSetting $settings, UploadedFile $file): BillingSetting
    {
        $this->assertSquareFavicon($file);
        $path = $this->storeImage($file, 'favicon', 1024, false);
        $this->deletePublic($settings->favicon_path);
        $settings->update(['favicon_path' => $path]);

        return $settings->fresh();
    }

    public function removeLogo(BillingSetting $settings): BillingSetting
    {
        $this->deletePublic($settings->logo_path);
        $settings->update(['logo_path' => null]);

        return $settings->fresh();
    }

    public function removeFavicon(BillingSetting $settings): BillingSetting
    {
        $this->deletePublic($settings->favicon_path);
        $settings->update(['favicon_path' => null]);

        return $settings->fresh();
    }

    public function publicUrl(?string $path, mixed $version = null): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $url = '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
        $stamp = $version instanceof \DateTimeInterface ? $version->getTimestamp() : (is_numeric($version) ? (int) $version : time());

        return $url.'?v='.$stamp;
    }

    public function deletePublic(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function storeImage(UploadedFile $file, string $name, int $maxKb, bool $logo): string
    {
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                $name => 'File exceeds the '.($maxKb >= 1024 ? round($maxKb / 1024).' MB' : $maxKb.' KB').' limit.',
            ]);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $allowed = $logo ? ['png', 'jpg', 'jpeg', 'webp', 'svg'] : ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'];
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                $name => 'Use '.implode(', ', $allowed).' only.',
            ]);
        }

        if ($ext === 'svg') {
            $this->sanitizeSvg((string) file_get_contents($file->getRealPath()), $name);
        }

        $mime = (string) $file->getMimeType();
        $blocked = ['application/x-httpd-php', 'text/html', 'application/javascript', 'application/x-msdownload'];
        if (in_array($mime, $blocked, true)) {
            throw ValidationException::withMessages([$name => 'That file type is not allowed.']);
        }

        return $file->storeAs('company', $name.'-'.now()->timestamp.'-'.bin2hex(random_bytes(4)).'.'.$ext, 'public');
    }

    public function sanitizeSvg(string $contents, string $field = 'logo'): string
    {
        if (! str_contains(strtolower($contents), '<svg')) {
            throw ValidationException::withMessages([$field => 'SVG file is not valid.']);
        }

        if (preg_match('/<\s*script/i', $contents)
            || preg_match('/javascript\s*:/i', $contents)
            || preg_match('/on[a-z]+\s*=/i', $contents)
            || preg_match('/<\s*(foreignobject|iframe|embed|object|link|meta|handler)/i', $contents)
            || preg_match('/data\s*:\s*text\/html/i', $contents)
        ) {
            throw ValidationException::withMessages([$field => 'SVG file is not allowed because it contains unsafe content.']);
        }

        return $contents;
    }

    protected function assertSquareFavicon(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (in_array($ext, ['svg', 'ico'], true)) {
            return;
        }

        $info = @getimagesize($file->getRealPath());
        if (! $info || (int) $info[0] < 16 || (int) $info[1] < 16 || (int) $info[0] !== (int) $info[1]) {
            throw ValidationException::withMessages([
                'favicon' => 'Favicon should be a square image (for example 32×32, 64×64, or 128×128).',
            ]);
        }
    }
}
