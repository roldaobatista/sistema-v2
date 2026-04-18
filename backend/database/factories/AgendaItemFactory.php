<?php

namespace Database\Factories;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Models\AgendaItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaItemFactory extends Factory
{
    protected $model = AgendaItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement(AgendaItemType::cases()),
            'title' => fake()->sentence(4),
            'short_description' => fake()->optional()->sentence(8),
            'assignee_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'status' => AgendaItemStatus::ABERTO,
            'priority' => fake()->randomElement(AgendaItemPriority::cases()),
            'origin' => AgendaItemOrigin::MANUAL,
            'visibility' => AgendaItemVisibility::EQUIPE,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }

    public function tarefa(): static
    {
        return $this->state(fn () => ['type' => AgendaItemType::TASK]);
    }

    public function urgente(): static
    {
        return $this->state(fn () => ['priority' => AgendaItemPriority::URGENTE]);
    }

    public function atrasado(): static
    {
        return $this->state(fn () => [
            'due_at' => now()->subDays(2),
            'status' => AgendaItemStatus::ABERTO,
        ]);
    }

    public function hoje(): static
    {
        return $this->state(fn () => ['due_at' => now()]);
    }

    public function concluido(): static
    {
        return $this->state(fn () => [
            'status' => AgendaItemStatus::CONCLUIDO,
            'closed_at' => now(),
        ]);
    }

    public function emAndamento(): static
    {
        return $this->state(fn () => ['status' => AgendaItemStatus::EM_ANDAMENTO]);
    }
}
