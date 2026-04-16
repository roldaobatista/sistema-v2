<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $email_account_id
 * @property string|null $message_id
 * @property string|null $thread_id
 * @property string|null $folder
 * @property string|null $subject
 * @property string|null $from_name
 * @property string|null $from_address
 * @property array|null $to_addresses
 * @property array|null $cc_addresses
 * @property string|null $snippet_text
 * @property string|null $body_text
 * @property string|null $body_html
 * @property bool $is_read
 * @property bool $is_starred
 * @property bool $is_archived
 * @property bool $has_attachments
 * @property string|null $direction
 * @property string|null $status
 * @property Carbon|null $date
 * @property int|null $customer_id
 * @property string|null $linked_type
 * @property int|null $linked_id
 * @property string|null $ai_category
 * @property string|null $ai_summary
 * @property string|null $ai_sentiment
 * @property string|null $ai_priority
 * @property string|null $ai_suggested_action
 * @property float|null $ai_confidence
 * @property Carbon|null $ai_classified_at
 * @property int|null $assigned_to_user_id
 * @property Carbon|null $assigned_at
 * @property Carbon|null $snoozed_until
 * @property string|null $tracking_id
 * @property int $read_count
 * @property Carbon|null $last_read_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property int|null $uid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmailAccount|null $account
 * @property-read Customer|null $customer
 * @property-read User|null $assignedTo
 * @property-read Model|null $linked
 * @property-read Collection<int, EmailAttachment> $attachments
 * @property-read Collection<int, EmailNote> $notes
 * @property-read Collection<int, CrmActivity> $activities
 * @property-read Collection<int, Email> $thread
 */
class Email extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'to_addresses' => 'array',
            'cc_addresses' => 'array',
            'date' => 'datetime',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'is_archived' => 'boolean',
            'has_attachments' => 'boolean',
            'ai_confidence' => 'decimal:2',
            'ai_classified_at' => 'datetime',
            'uid' => 'integer',
            // Phase 2
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_read_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'assigned_at' => 'datetime',
        ];

    }

    // --- Relationships ---

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EmailNote::class)->orderBy('created_at', 'desc');
    }

    public function tags()
    {
        return $this->belongsToMany(EmailTag::class, 'email_email_tag');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EmailActivity::class)->orderBy('created_at', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function linked(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Scopes ---

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    public function scopeInbox(Builder $query): Builder
    {
        return $query->where('folder', 'INBOX')
            ->where('is_archived', false)
            ->where('direction', 'inbound');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('ai_category', $category);
    }

    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('ai_priority', $priority);
    }

    public function scopeFromCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('email_account_id', $accountId);
    }

    // --- Accessors ---

    public function getIsClassifiedAttribute(): bool
    {
        return $this->ai_classified_at !== null;
    }

    public function getSnippetTextAttribute(): string
    {
        if ($this->snippet) {
            return $this->snippet;
        }

        return Str::limit(strip_tags($this->body_text ?? $this->body_html ?? ''), 200);
    }

    // --- Thread helpers ---

    public function thread(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id', 'thread_id');
    }

    public static function resolveThreadId(string $messageId, ?string $inReplyTo, ?string $references = null): string
    {
        if ($inReplyTo) {
            $existing = self::where('message_id', $inReplyTo)->value('thread_id');
            if ($existing) {
                return $existing;
            }
        }

        if ($references) {
            $refs = preg_split('/\s+/', trim($references));
            $firstRef = $refs[0] ?? null;
            if ($firstRef) {
                $existing = self::where('message_id', $firstRef)->value('thread_id');
                if ($existing) {
                    return $existing;
                }
            }
        }

        return md5($messageId);
    }
}
