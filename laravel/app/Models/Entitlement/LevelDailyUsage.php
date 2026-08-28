<?php

namespace App\Models\Entitlement;

use App\Models\Game;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One level a user touched on one day.
 *
 * Both daily counters are derived by counting these rows, never stored: started
 * today is the rows for the day, completed today is the same set filtered on
 * completed_at.
 *
 * A row is inserted for every account, unlimited and exempt ones included.
 * Recording and limit-checking are separate steps, and skipping the insert for
 * accounts that are never limited — the natural shape — would leave the
 * fair-use report with nothing to report on for exactly the tier it exists to
 * watch.
 *
 * There is deliberately no relation for level_id — the migration says why it is
 * not a foreign key.
 *
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $usage_date
 * @property int $game_id
 * @property int $level_id
 * @property \Illuminate\Support\Carbon $opened_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class LevelDailyUsage extends Model
{
    use HasFactory;

    /**
     * Singular on purpose: the table is named for what one row is, and the
     * pluraliser would otherwise reach for `level_daily_usages`.
     */
    protected $table = 'level_daily_usage';

    /**
     * opened_at is the insert time and completed_at the only later write, so
     * created_at and updated_at would hold nothing the row does not already say.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'usage_date',
        'game_id',
        'level_id',
        'opened_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Bare 'Y-m-d', not the `date` cast: that writes 'Y-m-d 00:00:00', which
     * MySQL truncates on write and sqlite keeps whole.
     */
    protected function usageDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => $value === null ? null : Carbon::parse($value),
            set: fn (DateTimeInterface|string $value): string => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * The account whose allowance this row consumes.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
