<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReliabilitySheetNameResolver;
use PHPUnit\Framework\TestCase;

final class ReliabilitySheetNameResolverTest extends TestCase
{
    public function test_it_removes_invalid_characters_and_limits_names_to_31_characters(): void
    {
        $resolver = new ReliabilitySheetNameResolver;

        $name = $resolver->resolve('Pengontrol / Petunjuk [Wesel]: Mekanik?');

        self::assertLessThanOrEqual(31, mb_strlen($name));
        self::assertDoesNotMatchRegularExpression('/[\\\\\/\?\*\[\]:]/u', $name);
    }

    public function test_it_adds_a_deterministic_suffix_for_duplicate_names(): void
    {
        $resolver = new ReliabilitySheetNameResolver;

        self::assertSame('Interlocking Elektrik', $resolver->resolve('Interlocking Elektrik'));
        self::assertSame('Interlocking Elektrik (2)', $resolver->resolve('Interlocking Elektrik'));
    }
}
