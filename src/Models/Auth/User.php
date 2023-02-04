<?php

namespace App\Models\Auth;

use App\Middlewares\Auth\AuthMiddleware;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * User Model.
 *
 * @property int $id
 * @property string $name
 * @property string $emailAddress
 * @property string $password
 * @property ?Carbon $createdAt
 * @property ?Carbon $updatedAt
 */
class User extends Model
{
    /**
     * Get current user.
     *
     * @return static|null
     */
    public static function me (): ?self
    {
        return AuthMiddleware::getCurrentUser();
    }
}