<?php

namespace Palgoal\MediaLibrary\Tests\Unit;

use Palgoal\MediaLibrary\Support\MediaSelection;
use Palgoal\MediaLibrary\Tests\TestCase;

class MediaSelectionTest extends TestCase
{
    public function test_parses_a_comma_separated_string(): void
    {
        $this->assertSame([5, 8, 10], MediaSelection::parse('5,8,10'));
    }

    public function test_parses_an_array_mixing_ints_and_numeric_strings(): void
    {
        $this->assertSame([5, 8, 10], MediaSelection::parse([5, '8', 10]));
    }

    public function test_trims_whitespace_deduplicates_and_ignores_non_numeric_values(): void
    {
        $this->assertSame([5, 8, 10], MediaSelection::parse(' 5, 8, 8, abc, 10 '));
    }

    public function test_preserves_order_of_first_occurrence(): void
    {
        $this->assertSame([10, 5, 8], MediaSelection::parse('10,5,8,5,10'));
    }

    public function test_null_returns_an_empty_array(): void
    {
        $this->assertSame([], MediaSelection::parse(null));
    }

    public function test_empty_string_returns_an_empty_array(): void
    {
        $this->assertSame([], MediaSelection::parse(''));
    }

    public function test_single_positive_integer(): void
    {
        $this->assertSame([5], MediaSelection::parse(5));
    }

    public function test_zero_and_negative_integers_are_rejected(): void
    {
        $this->assertSame([], MediaSelection::parse(0));
        $this->assertSame([], MediaSelection::parse(-5));
        $this->assertSame([], MediaSelection::parse('-5'));
        $this->assertSame([], MediaSelection::parse('0'));
    }

    public function test_array_with_only_invalid_values_returns_an_empty_array(): void
    {
        $this->assertSame([], MediaSelection::parse(['abc', '', null, '-3', '0']));
    }

    public function test_floats_and_decimal_looking_strings_are_ignored(): void
    {
        $this->assertSame([5], MediaSelection::parse([5, '5.5', 5.5]));
    }

    public function test_does_not_query_the_database(): void
    {
        // No Media rows exist at all in this test's fresh in-memory DB —
        // if parse() queried the database to validate existence, this
        // would still have to return the ids unchanged, since parse()'s
        // documented contract is normalization only, not existence
        // validation.
        $this->assertSame([1, 2, 3], MediaSelection::parse('1,2,3'));
        $this->assertDatabaseCount('media', 0);
    }
}
