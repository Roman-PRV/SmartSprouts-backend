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
 * @OA\Schema(
 *   schema="DailyLimitReachedResponse",
 *   type="object",
 *   description="403 on either gate once that gate's own daily counter is spent.",
 *
 *   @OA\Property(property="message", type="string", example="Daily limit reached"),
 *   @OA\Property(property="error_type", type="string", example="LEVEL_LIMIT_REACHED"),
 *   @OA\Property(
 *     property="details",
 *     type="object",
 *     description="limit_kind names the counter this gate enforces: started on opening, completed on submitting.",
 *     @OA\Property(property="limit_kind", type="string", enum={"started", "completed"}, example="started"),
 *     @OA\Property(property="resets_at", type="string", format="date-time", example="2026-08-24T00:00:00Z"),
 *     @OA\Property(property="purchasing_enabled", type="boolean", example=true)
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="LevelNotOpenedTodayResponse",
 *   type="object",
 *   description="403 on submitting a level with no recorded open for today. Not a limit: the client re-fetches the level and resubmits.",
 *
 *   @OA\Property(property="message", type="string", example="Open this level before submitting"),
 *   @OA\Property(property="error_type", type="string", example="LEVEL_NOT_OPENED_TODAY")
 * )
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $usage_date
 * @property int $game_id
 * @property int $level_id
 * @property Carbon $opened_at
 * @property Carbon|null $completed_at
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
            get: fn (string $value): Carbon => Carbon::parse($value),
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
