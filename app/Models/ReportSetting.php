<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'top_margin',
        'bottom_margin',
        'left_margin',
        'right_margin',
        'content_padding',
    ];

    protected $casts = [
        'top_margin' => 'integer',
        'bottom_margin' => 'integer',
        'left_margin' => 'integer',
        'right_margin' => 'integer',
        'content_padding' => 'integer',
    ];

    // Default values
    public const DEFAULT_TOP_MARGIN = 60;
    public const DEFAULT_BOTTOM_MARGIN = 120;
    public const DEFAULT_LEFT_MARGIN = 40;
    public const DEFAULT_RIGHT_MARGIN = 40;
    public const DEFAULT_CONTENT_PADDING = 10;

    // Min/Max constraints
    public const MIN_MARGIN = 5;
    public const MAX_MARGIN = 40;

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get default settings
     */
    public static function getDefaults(): array
    {
        return [
            'top_margin' => self::DEFAULT_TOP_MARGIN,
            'bottom_margin' => self::DEFAULT_BOTTOM_MARGIN,
            'left_margin' => self::DEFAULT_LEFT_MARGIN,
            'right_margin' => self::DEFAULT_RIGHT_MARGIN,
            'content_padding' => self::DEFAULT_CONTENT_PADDING,
        ];
    }

    /**
     * Clamp a value between min and max
     */
    public static function clamp(int $value): int
    {
        return max(self::MIN_MARGIN, min(self::MAX_MARGIN, $value));
    }
}

