<?php

namespace Database\Factories;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Models\AgendaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaItemFactory extends Factory
{
    protected $model = AgendaItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'tipo' => fake()->randomElement(AgendaItemType::cases()),
            'titulo' => fake()->sentence(4),
            'descricao_curta' => fake()->optional()->sentence(8),
            'responsavel_user_id' => User::factory(),
            'criado_por_user_id' => User::factory(),
            'status' => AgendaItemStatus::ABERTO,
            'prioridade' => fake()->randomElement(AgendaItemPriority::cases()),
            'origem' => AgendaItemOrigin::MANUAL,
            'visibilidade' => AgendaItemVisibility::EQUIPE,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }

    public function tarefa(): static
    {
        return $this->state(fn () => ['tipo' => AgendaItemType::TASK]);
    }

    public function urgente(): static
    {
        return $this->state(fn () => ['prioridade' => AgendaItemPriority::URGENTE]);
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
