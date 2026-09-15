<?php

namespace Zieren\WYT\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zieren\WYT\Application\ValueObject\BudgetName;
use Zieren\WYT\Application\ValueObject\ClassName;
use Zieren\WYT\Application\ValueObject\Minutes;
use Zieren\WYT\Application\ValueObject\SlotExpression;
use Zieren\WYT\Application\ValueObject\WindowTitlePattern;

final class ValueObjectTest extends TestCase
{
    public function testClassNameTrimsWhitespaceAndRejectsEmpty(): void
    {
        $name = new ClassName('  School  ');
        $this->assertSame('School', $name->value);

        $this->expectException(InvalidArgumentException::class);
        new ClassName('   ');
    }

    public function testBudgetNameTrimsWhitespaceAndRejectsEmpty(): void
    {
        $name = new BudgetName("\tGames\n");
        $this->assertSame('Games', $name->value);

        $this->expectException(InvalidArgumentException::class);
        new BudgetName('');
    }

    public function testWindowTitlePatternTrimsWhitespaceAndRejectsEmpty(): void
    {
        $pattern = new WindowTitlePattern('  mail  ');
        $this->assertSame('mail', $pattern->value);

        $this->expectException(InvalidArgumentException::class);
        new WindowTitlePattern('');
    }

    public function testMinutesRejectsNegativeValues(): void
    {
        $minutes = new Minutes(0);
        $this->assertSame(0, $minutes->value);

        $minutes = new Minutes(30);
        $this->assertSame(30, $minutes->value);

        $this->expectException(InvalidArgumentException::class);
        new Minutes(-1);
    }

    public function testSlotExpressionTrimsWhitespaceAndRejectsEmpty(): void
    {
        $slot = new SlotExpression('  08:00-12:00  ');
        $this->assertSame('08:00-12:00', $slot->value);

        $this->expectException(InvalidArgumentException::class);
        new SlotExpression('  ');
    }
}
