<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to a model: who it was for, which job, which model, what it
 * cost in tokens and time, and whether it failed. Written by RecordAiCall
 * from the SDK's events; read by UsageReport and the monthly cap.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $agent
 * @property string|null $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int|null $cached_input_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $duration_ms
 * @property bool $failed
 * @property string|null $error
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'agent', 'provider', 'model', 'input_tokens', 'output_tokens', 'cached_input_tokens', 'reasoning_tokens', 'duration_ms', 'failed', 'error', 'created_at'])]
final class AiCallRecord extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'ai_calls';

    protected function casts(): array
    {
        return [
            'failed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeSince(Builder $query, CarbonInterface $since): void
    {
        $query->where('created_at', '>=', $since);
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    /** The job behind the call, without its namespace: "SmartLogAgent". */
    public function job(): string
    {
        return class_basename($this->agent);
    }
}
