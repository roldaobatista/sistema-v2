<?php

namespace App\Models;

use App\Enums\SettingGroup;
use App\Enums\SettingType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property SettingType|null $type
 * @property SettingGroup|null $group
 */
class SystemSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'key', 'value', 'type', 'group'];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'group' => SettingGroup::class,
        ];
    }

    public function getTypedValue(): mixed
    {
        $type = $this->type ?? SettingType::STRING;

        return match ($type) {
            SettingType::BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            SettingType::INTEGER => (int) $this->value,
            SettingType::JSON => json_decode($this->value, true),
            SettingType::STRING => $this->value,
        };
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->getTypedValue() : $default;
    }

    public static function setValue(string $key, mixed $value, SettingType|string $type = SettingType::STRING, SettingGroup|string $group = SettingGroup::GENERAL): static
    {
        $tenantId = app()->bound('current_tenant_id')
            ? app('current_tenant_id')
            : (auth()->check() ? auth()->user()->current_tenant_id ?? auth()->user()->tenant_id : null);

        if (! $tenantId) {
            throw new \RuntimeException('Não é possível salvar configuração sem tenant_id definido.');
        }

        $typeEnum = $type instanceof SettingType ? $type : (SettingType::tryFrom($type) ?? SettingType::STRING);
        $groupEnum = $group instanceof SettingGroup ? $group : (SettingGroup::tryFrom($group) ?? SettingGroup::GENERAL);

        return static::updateOrCreate(
            ['key' => $key, 'tenant_id' => $tenantId],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $typeEnum, 'group' => $groupEnum]
        );
    }
}
