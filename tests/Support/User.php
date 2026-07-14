<?php

namespace Palgoal\MediaLibrary\Tests\Support;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal stand-in "host application" user model used only by the test
 * suite. This package does NOT ship or depend on this class — it exists
 * purely so tests can exercise the `uploader()` relation and the
 * auth/policy middleware against a real, resolvable user model, the same
 * way a consuming Laravel application would provide its own.
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'users';

    protected $guarded = [];
}
