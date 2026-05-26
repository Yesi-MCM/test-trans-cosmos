<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['todo', 'in_progress', 'review', 'completed']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'created_by' => User::factory(),
            'assigned_user_id' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
        ];
    }
}
