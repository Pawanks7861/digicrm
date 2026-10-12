<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'key', 'category', 'name', 'subject', 'heading', 'body',
        'cta_label', 'footer', 'is_active', 'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function render(array $vars): array
    {
        $replace = function (string $text, bool $plainText = false) use ($vars) {
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars, $plainText) {
                $key = $m[1];
                if (! array_key_exists($key, $vars)) {
                    return '';
                }
                $val = $vars[$key];
                if (! is_scalar($val) && $val !== null) {
                    return '';
                }
                $str = (string) $val;
                if ($plainText) {
                    return strip_tags($str);
                }

                return e($str);
            }, $text) ?? $text;
        };

        return [
            'subject' => $replace($this->subject, true),
            'heading' => $replace((string) $this->heading),
            'body' => $replace($this->body),
            'cta_label' => $replace((string) $this->cta_label, true),
            'footer' => $replace((string) $this->footer),
        ];
    }
}
