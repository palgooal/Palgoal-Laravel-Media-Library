<?php

namespace Palgoal\MediaLibrary\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Palgoal\MediaLibrary\Concerns\HasMedia;

/**
 * Minimal stand-in "host application" model WITH SoftDeletes, used only
 * by the test suite to exercise Concerns\HasMedia's soft-delete-aware
 * cleanup behavior. This package does NOT ship or depend on this class.
 */
class Post extends Model
{
    use HasMedia;
    use SoftDeletes;

    protected $table = 'posts';

    protected $guarded = [];
}
