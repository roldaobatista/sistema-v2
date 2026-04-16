<?php

namespace Database\Factories;

use App\Models\InmetroInstrument;
use App\Models\InmetroLocation;
use App\Models\InmetroOwner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class InmetroInstrumentFactory extends Factory
{
    protected $model = InmetroInstrument::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            if (! $model instanceof InmetroInstrument) {
                return;
            }

            $instrument = $model;
            $attributes = $instrument->getAttributes();
            $ownerId = $attributes['owner_id'] ?? null;

            if ($ownerId !== null) {
                $owner = InmetroOwner::query()->find($ownerId);

                if ($owner !== null) {
                    $location = InmetroLocation::query()->firstOrCreate(
                        [
                            'owner_id' => $owner->id,
                            'address_city' => 'Cuiaba',
                            'address_state' => 'MT',
                        ],
                        [
                            'address_street' => 'Base Operacional',
                            'address_number' => '1',
                            'address_zip' => '78000-000',
                        ]
                    );

                    $attributes['location_id'] = $location->id;
                }
            }

            if (array_key_exists('type', $attributes) && ! empty($attributes['type'])) {
                $attributes['instrument_type'] = $attributes['type'];
            } elseif (array_key_exists('instrument_type', $attributes) && ! empty($attributes['instrument_type'])) {
                $attributes['type'] = $attributes['instrument_type'];
            }

            unset($attributes['owner_id']);
            $instrument->setRawAttributes($attributes);
        });
    }

    public function definition(): array
    {
        return [
            'location_id' => InmetroLocation::factory(),
            'tenant_id' => null,
            'inmetro_number' => $this->faker->unique()->numerify('##########'),
            'serial_number' => $this->faker->bothify('SN-####-??'),
            'brand' => $this->faker->word,
            'model' => $this->faker->bothify('MOD-###'),
            'capacity' => '80t',
            'instrument_type' => 'Balança Rodoviária',
            'current_status' => 'approved',
            'next_verification_at' => $this->faker->dateTimeBetween('now', '+1 year'),
        ];
    }
}
