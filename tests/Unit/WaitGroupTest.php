<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Core\Exception\InvalidArgumentException;
use Switon\Core\Exception\MisuseException;
use Switon\Sync\Exception\WaitTimeoutException;
use Switon\Sync\Tests\TestCase;
use Switon\Sync\WaitGroup;

class WaitGroupTest extends TestCase
{
    public function testOfCreatesWithCount(): void
    {
        $wg = WaitGroup::of(3);

        $this->assertSame(3, $wg->count());
    }

    public function testOfThrowsOnZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WaitGroup::of(0);
    }

    public function testOfThrowsOnNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WaitGroup::of(-1);
    }

    public function testAddIncrementsCounter(): void
    {
        $wg = WaitGroup::of(1);

        $wg->add(2);

        $this->assertSame(3, $wg->count());
    }

    public function testDoneDecrementsCounter(): void
    {
        $wg = WaitGroup::of(3);

        $wg->done();

        $this->assertSame(2, $wg->count());
    }

    public function testDoneToZero(): void
    {
        $wg = WaitGroup::of(1);

        $wg->done();

        $this->assertSame(0, $wg->count());
    }

    public function testDoneThrowsWhenCounterIsZero(): void
    {
        $wg = WaitGroup::of(1);
        $wg->done();

        $this->expectException(MisuseException::class);

        $wg->done();
    }

    public function testWaitAfterAllDoneCalls(): void
    {
        // Arrange
        $wg = WaitGroup::of(3);

        // Act
        $wg->done();
        $wg->done();
        $wg->done();
        $wg->wait();

        // Assert
        $this->assertSame(0, $wg->count());
    }

    public function testWaitThrowsWhenCounterNonZeroInFpmMode(): void
    {
        // Arrange
        $wg = WaitGroup::of(2);
        $wg->done(); // counter is 1

        // Act & Assert
        $this->expectException(WaitTimeoutException::class);

        $wg->wait();
    }

    public function testWaitThrowsWithFullCounterInFpmMode(): void
    {
        // Arrange
        $wg = WaitGroup::of(5);

        // Act & Assert
        $this->expectException(WaitTimeoutException::class);

        $wg->wait();
    }

    public function testReusableAfterWait(): void
    {
        // Arrange - first cycle
        $wg = WaitGroup::of(2);
        $wg->done();
        $wg->done();
        $wg->wait();

        // Act - second cycle
        $wg->add(1);
        $wg->done();
        $wg->wait();

        // Assert
        $this->assertSame(0, $wg->count());
    }

    public function testNonCoroutineWaitFailureDoesNotPreventLaterReuse(): void
    {
        $wg = WaitGroup::of(2);
        $wg->done();

        try {
            $wg->wait();
            $this->fail('wait() should fail in non-coroutine mode while counter is non-zero');
        } catch (WaitTimeoutException) {
        }

        $wg->done();
        $wg->wait();

        $this->assertSame(0, $wg->count());
    }
}
