<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function getAllOrdered(): Collection;

    public function create(array $data): User;

    public function update(int $id, array $data): User;

    public function delete(int $id): bool;
}
