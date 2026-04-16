<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * @property bool|null $notify_status_change
 * @property bool|null $notify_comment
 * @property bool|null $notify_due_date
 * @property bool|null $notify_assignment
 */
class AgendaItemWatcher extends Model
{
    use BelongsToTenant;

    protected $table = 'central_item_watchers';

    private static ?string $itemForeignKey = null;

    protected $guarded = ['id'];

    protected $attributes = [
        'role' => 'watcher',
        'added_by_type' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'notify_status_change' => 'boolean',
            'notify_comment' => 'boolean',
            'notify_due_date' => 'boolean',
            'notify_assignment' => 'boolean',
        ];

    }

    public static function itemForeignKey(): string
    {
        if (self::$itemForeignKey !== null) {
            return self::$itemForeignKey;
        }

        $table = (new static)->getTable();

        if (Schema::hasColumn($table, 'agenda_item_id')) {
            return self::$itemForeignKey = 'agenda_item_id';
        }

        if (Schema::hasColumn($table, 'central_item_id')) {
            return self::$itemForeignKey = 'central_item_id';
        }

        return self::$itemForeignKey = 'agenda_item_id';
    }

    public static function itemForeignAttributes(int $itemId): array
    {
        return [self::itemForeignKey() => $itemId];
    }

    public static function resetItemForeignKeyCache(): void
    {
        self::$itemForeignKey = null;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, self::itemForeignKey());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function scopeNotifyOn($query, string $event)
    {
        return $query->where("notify_{$event}", true);
    }

    public function shouldNotify(string $event): bool
    {
        $column = "notify_{$event}";

        return $this->getAttribute($column) === true;
    }
}
