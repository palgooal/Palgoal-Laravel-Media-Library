<?php

namespace Palgoal\MediaLibrary\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Palgoal\MediaLibrary\Concerns\HasMedia;

/**
 * Minimal stand-in "host application" model (no SoftDeletes) used only
 * by the test suite to exercise Concerns\HasMedia. This package does NOT
 * ship or depend on this class.
 */
class Product extends Model
{
    use HasMedia;

    protected $table = 'products';

    protected $guarded = [];
}
