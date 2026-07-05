<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Lock;
use Switon\Sync\Mutex;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MutexTest extends TestCase
{
    public function testLockAcquiresLock(): void
    {
        $mutex = new Mutex();

        $this->assertFalse($mutex->isLocked());

        $mutex->lock();

        $this->assertTrue($mutex->isLocked());
    }

    public function testUnlockReleasesLock(): void
    {
        $mutex = new Mutex();

        $mutex->lock();
        $this->assertTrue($mutex->isLocked());

        $mutex->unlock();

        $this->assertFalse($mutex->isLocked());
    }

    public function testIsLockedReturnsFalseInitially(): void
    {
        $mutex = new Mutex();

        $this->assertFalse($mutex->isLocked());
    }

    public function testIsLockedReturnsTrueAfterLock(): void
    {
        $mutex = new Mutex();

        $mutex->lock();

        $this->assertTrue($mutex->isLocked());
    }

    public function testLockUnlockSequence(): void
    {
        $mutex = new Mutex();

        $this->assertFalse($mutex->isLocked());

        $mutex->lock();
        $this->assertTrue($mutex->isLocked());

        $mutex->unlock();
        $this->assertFalse($mutex->isLocked());

        $mutex->lock();
        $this->assertTrue($mutex->isLocked());
    }

    public function testLockThrowsExceptionWhenAlreadyLocked(): void
    {
        $mutex = new Mutex();

        $mutex->lock();
        $this->assertTrue($mutex->isLocked());

        $this->expectException(ChannelEmptyException::class);

        $mutex->lock();
    }

    // ========== Lock (RAII) Tests ==========

    public function testGuardReturnsLockAndLocksTheMutex(): void
    {
        // Arrange
        $mutex = new Mutex();

        // Act
        $lock = $mutex->guard();

        // Assert
        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertTrue($mutex->isLocked());
        $this->assertFalse($lock->isReleased());
    }

    public function testLockReleaseUnlocksMutex(): void
    {
        // Arrange
        $mutex = new Mutex();
        $lock = $mutex->guard();

        // Act
        $lock->release();

        // Assert
        $this->assertFalse($mutex->isLocked());
        $this->assertTrue($lock->isReleased());
    }

    public function testLockDoubleReleaseIsSafe(): void
    {
        // Arrange
        $mutex = new Mutex();
        $lock = $mutex->guard();

        // Act - release twice, should not throw
        $lock->release();
        $lock->release();

        // Assert
        $this->assertFalse($mutex->isLocked());
    }

    public function testLockAutoReleasesOnDestruct(): void
    {
        // Arrange
        $mutex = new Mutex();

        // Act - guard in a scope, then destroy
        $lock = $mutex->guard();
        $this->assertTrue($mutex->isLocked());
        unset($lock);

        // Assert
        $this->assertFalse($mutex->isLocked());
    }

    public function testLockAutoReleasesOnException(): void
    {
        // Arrange
        $mutex = new Mutex();
        $exceptionCaught = false;

        // Act
        try {
            $lock = $mutex->guard();
            throw new RuntimeException('test exception');
        } catch (RuntimeException) {
            $exceptionCaught = true;
            // $lock goes out of scope here
        }
        unset($lock);

        // Assert
        $this->assertTrue($exceptionCaught);
        $this->assertFalse($mutex->isLocked());
    }

    public function testLockReleaseThenReguard(): void
    {
        // Arrange
        $mutex = new Mutex();

        // Act
        $lock1 = $mutex->guard();
        $lock1->release();
        $lock2 = $mutex->guard();

        // Assert
        $this->assertTrue($mutex->isLocked());
        $this->assertTrue($lock1->isReleased());
        $this->assertFalse($lock2->isReleased());

        $lock2->release();
    }

    public function testGuardThrowsWhenAlreadyLocked(): void
    {
        // Arrange
        $mutex = new Mutex();
        $lock = $mutex->guard(); // Keep reference alive

        // Act & Assert
        $this->expectException(ChannelEmptyException::class);
        $mutex->guard();
    }
}
