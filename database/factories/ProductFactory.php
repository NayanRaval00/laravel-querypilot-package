<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'          => $this->faker->name,
            'sku'           => $this->faker->unique()->bothify('SKU-#####'),
            'price'         => $this->faker->randomFloat(2, 10, 1000),
            'stock'         => $this->faker->numberBetween(0, 500),
            'is_active'     => $this->faker->boolean,
            'description'   => $this->faker->text,
            'user_id'       => User::factory(),
        ];
    }
}
