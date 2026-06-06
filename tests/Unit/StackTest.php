<?php

declare(strict_types=1);

namespace Switon\Sync\Tests\Unit;

use Switon\Sync\Exception\ChannelEmptyException;
use Switon\Sync\Exception\ChannelFullException;
use Switon\Sync\Stack;
use Switon\Sync\Tests\TestCase;
use stdClass;

/**
 * Black-box and white-box tests for Stack.
 *
 * Black-box tests verify external behavior without knowledge of internal implementation.
 * White-box tests verify implementation details and edge cases.
 */
class StackTest extends TestCase
{
    // ========== Basic Construction Tests ==========

    public function testConstructCreatesStackWithCapacity(): void
    {
        $stack = new Stack(5);

        $this->assertSame(5, $stack->capacity());
        $this->assertSame(0, $stack->length());
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());
    }

    public function testConstructWithCapacityOne(): void
    {
        $stack = new Stack(1);

        $this->assertSame(1, $stack->capacity());
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());
    }

    public function testConstructWithLargeCapacity(): void
    {
        $stack = new Stack(1000);

        $this->assertSame(1000, $stack->capacity());
        $this->assertTrue($stack->isEmpty());
    }

    // ========== Black-Box Tests: LIFO Behavior ==========

    /**
     * Black-box test: Verify Stack maintains LIFO order in simple case.
     */
    public function testLifoBehaviorSimple(): void
    {
        $stack = new Stack(5);

        $stack->push('first');
        $stack->push('second');
        $stack->push('third');

        $this->assertSame('third', $stack->pop());
        $this->assertSame('second', $stack->pop());
        $this->assertSame('first', $stack->pop());
    }

    /**
     * Black-box test: Verify LIFO order with mixed push/pop operations.
     */
    public function testLifoBehaviorWithMixedOperations(): void
    {
        $stack = new Stack(10);

        $stack->push('a');
        $stack->push('b');
        $this->assertSame('b', $stack->pop());

        $stack->push('c');
        $stack->push('d');
        $this->assertSame('d', $stack->pop());
        $this->assertSame('c', $stack->pop());
        $this->assertSame('a', $stack->pop());
    }

    /**
     * Black-box test: Verify LIFO after multiple cycles of push/pop.
     */
    public function testLifoBehaviorAfterMultipleCycles(): void
    {
        $stack = new Stack(5);

        // Cycle 1
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);
        $this->assertSame(3, $stack->pop());
        $this->assertSame(2, $stack->pop());

        // Cycle 2
        $stack->push(4);
        $stack->push(5);
        $this->assertSame(5, $stack->pop());
        $this->assertSame(4, $stack->pop());
        $this->assertSame(1, $stack->pop());
    }

    // ========== Black-Box Tests: Capacity and State ==========

    /**
     * Black-box test: Verify state transitions during operations.
     */
    public function testStateTransitions(): void
    {
        $stack = new Stack(3);

        // Initial state
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());
        $this->assertSame(0, $stack->length());

        // After first push
        $stack->push('a');
        $this->assertFalse($stack->isEmpty());
        $this->assertFalse($stack->isFull());
        $this->assertSame(1, $stack->length());

        // After filling
        $stack->push('b');
        $stack->push('c');
        $this->assertFalse($stack->isEmpty());
        $this->assertTrue($stack->isFull());
        $this->assertSame(3, $stack->length());

        // After pop
        $stack->pop();
        $this->assertFalse($stack->isEmpty());
        $this->assertFalse($stack->isFull());
        $this->assertSame(2, $stack->length());

        // After emptying
        $stack->pop();
        $stack->pop();
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());
        $this->assertSame(0, $stack->length());
    }

    // ========== White-Box Tests: Index Reuse ==========

    /**
     * White-box test: Verify index reuse after pop (tests internal array handling).
     */
    public function testIndexReuseAfterPop(): void
    {
        $stack = new Stack(3);

        // Fill stack
        $stack->push('a'); // index 0
        $stack->push('b'); // index 1
        $stack->push('c'); // index 2

        // Pop middle element
        $this->assertSame('c', $stack->pop()); // index 2 removed

        // Push new element - should reuse or create new index
        $stack->push('d');

        // Verify LIFO still works
        $this->assertSame('d', $stack->pop());
        $this->assertSame('b', $stack->pop());
        $this->assertSame('a', $stack->pop());
    }

    /**
     * White-box test: Verify correct behavior with multiple pops and pushes.
     */
    public function testMultiplePopPushCycles(): void
    {
        $stack = new Stack(5);

        // Fill to capacity
        for ($i = 0; $i < 5; $i++) {
            $stack->push("item$i");
        }

        // Pop all
        for ($i = 4; $i >= 0; $i--) {
            $this->assertSame("item$i", $stack->pop());
        }

        // Fill again
        for ($i = 0; $i < 5; $i++) {
            $stack->push("new$i");
        }

        // Verify LIFO
        for ($i = 4; $i >= 0; $i--) {
            $this->assertSame("new$i", $stack->pop());
        }
    }

    // ========== Boundary Tests ==========

    /**
     * Black-box test: Verify behavior at capacity limit.
     */
    public function testAtCapacityLimit(): void
    {
        $stack = new Stack(2);

        $stack->push('a');
        $this->assertFalse($stack->isFull());

        $stack->push('b');
        $this->assertTrue($stack->isFull());

        // Should throw exception
        $this->expectException(ChannelFullException::class);
        $stack->push('c');
    }

    /**
     * Black-box test: Verify behavior when empty.
     */
    public function testWhenEmpty(): void
    {
        $stack = new Stack(2);

        $this->assertTrue($stack->isEmpty());
        $this->expectException(ChannelEmptyException::class);
        $stack->pop();
    }

    /**
     * Black-box test: Verify single element stack.
     */
    public function testSingleElementStack(): void
    {
        $stack = new Stack(1);

        $stack->push('only');
        $this->assertTrue($stack->isFull());
        $this->assertFalse($stack->isEmpty());
        $this->assertSame(1, $stack->length());

        $this->assertSame('only', $stack->pop());
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());

        // Can push again
        $stack->push('again');
        $this->assertSame('again', $stack->pop());
    }

    // ========== Data Type Tests ==========

    /**
     * Black-box test: Verify Stack handles different data types.
     */
    public function testDifferentDataTypes(): void
    {
        $stack = new Stack(10);

        $stack->push('string');
        $stack->push(123);
        $stack->push(45.67);
        $stack->push(true);
        $stack->push(false);
        $stack->push(null);
        $stack->push(['array' => 'value']);
        $stack->push((object)['property' => 'value']);

        $obj = $stack->pop();
        $this->assertIsObject($obj);
        $this->assertSame('value', $obj->property);

        $this->assertSame(['array' => 'value'], $stack->pop());
        $this->assertNull($stack->pop());
        $this->assertFalse($stack->pop());
        $this->assertTrue($stack->pop());
        $this->assertSame(45.67, $stack->pop());
        $this->assertSame(123, $stack->pop());
        $this->assertSame('string', $stack->pop());
    }

    /**
     * Black-box test: Verify Stack handles object references correctly.
     */
    public function testObjectReferences(): void
    {
        $stack = new Stack(3);

        $obj1 = new stdClass();
        $obj1->value = 1;
        $obj2 = new stdClass();
        $obj2->value = 2;

        $stack->push($obj1);
        $stack->push($obj2);

        $popped2 = $stack->pop();
        $popped1 = $stack->pop();

        // Should be same object instances
        $this->assertSame($obj2, $popped2);
        $this->assertSame($obj1, $popped1);

        // Modifying original should affect popped (same reference)
        $obj1->value = 999;
        $this->assertSame(999, $popped1->value);
    }

    // ========== Complex Operation Sequences ==========

    /**
     * Black-box test: Complex real-world usage scenario.
     */
    public function testComplexUsageScenario(): void
    {
        $stack = new Stack(5);

        // Phase 1: Build up
        $stack->push('task1');
        $stack->push('task2');
        $stack->push('task3');

        // Phase 2: Process some
        $this->assertSame('task3', $stack->pop());
        $this->assertSame('task2', $stack->pop());

        // Phase 3: Add more
        $stack->push('task4');
        $stack->push('task5');
        $stack->push('task6');

        // Phase 4: Verify LIFO order
        $this->assertSame('task6', $stack->pop());
        $this->assertSame('task5', $stack->pop());
        $this->assertSame('task4', $stack->pop());
        $this->assertSame('task1', $stack->pop());
    }

    /**
     * Black-box test: Rapid push/pop operations without exceeding capacity.
     */
    public function testRapidPushPop(): void
    {
        $stack = new Stack(10);

        // Rapid operations within capacity
        for ($i = 0; $i < 10; $i++) {
            $stack->push($i);
            if ($i % 2 === 1) {
                $popped = $stack->pop();
                $this->assertSame($i, $popped);
            }
        }

        // Verify remaining items
        $remaining = [];
        while (!$stack->isEmpty()) {
            $remaining[] = $stack->pop();
        }

        // Should be all even numbers in reverse order: 8, 6, 4, 2, 0
        $expected = [8, 6, 4, 2, 0];
        $this->assertSame($expected, $remaining);
    }

    /**
     * Black-box test: Verify Stack can handle many cycles of fill/empty.
     */
    public function testManyFillEmptyCycles(): void
    {
        $stack = new Stack(5);

        // Multiple fill/empty cycles
        for ($cycle = 0; $cycle < 10; $cycle++) {
            // Fill
            for ($i = 0; $i < 5; $i++) {
                $stack->push("cycle{$cycle}_item$i");
            }

            // Verify full
            $this->assertTrue($stack->isFull());
            $this->assertSame(5, $stack->length());

            // Empty
            for ($i = 4; $i >= 0; $i--) {
                $this->assertSame("cycle{$cycle}_item$i", $stack->pop());
            }

            // Verify empty
            $this->assertTrue($stack->isEmpty());
            $this->assertSame(0, $stack->length());
        }
    }

    // ========== State Consistency Tests ==========

    /**
     * White-box test: Verify length/isEmpty/isFull consistency.
     */
    public function testStateConsistency(): void
    {
        $stack = new Stack(3);

        // Empty state
        $this->assertSame(0, $stack->length());
        $this->assertTrue($stack->isEmpty());
        $this->assertFalse($stack->isFull());

        // After each push, verify consistency
        foreach ([1, 2, 3] as $value) {
            $stack->push($value);
            $this->assertSame($value, $stack->length());
            $this->assertFalse($stack->isEmpty());
            $this->assertSame($value === 3, $stack->isFull());
        }

        // After each pop, verify consistency
        foreach ([3, 2, 1] as $expected) {
            $popped = $stack->pop();
            $this->assertSame($expected, $popped);
            $remaining = $stack->length();
            $this->assertSame($expected - 1, $remaining);
            $this->assertSame($remaining === 0, $stack->isEmpty());
            $this->assertFalse($stack->isFull());
        }
    }

    // ========== Timeout Tests ==========

    /**
     * Black-box test: Verify pop with timeout on empty stack returns false.
     */
    public function testPopWithTimeoutOnEmptyStackReturnsFalse(): void
    {
        // Arrange
        $stack = new Stack(3);

        // Act
        $result = $stack->pop(1.0);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue($stack->isEmpty());
        $this->assertSame(0, $stack->length());
    }

    /**
     * Black-box test: Verify pop with timeout after consuming all items returns false.
     */
    public function testPopWithTimeoutAfterConsumingAllReturnsFalse(): void
    {
        // Arrange
        $stack = new Stack(2);
        $stack->push('a');
        $stack->push('b');
        $stack->pop();
        $stack->pop();

        // Act
        $result = $stack->pop(1.0);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue($stack->isEmpty());
    }

    // ========== Legacy Tests (kept for compatibility) ==========

    public function testPushAddsItemToStack(): void
    {
        $stack = new Stack(2);

        $stack->push('item1');

        $this->assertSame(1, $stack->length());
        $this->assertFalse($stack->isEmpty());
        $this->assertFalse($stack->isFull());
    }

    public function testPushThrowsExceptionWhenFull(): void
    {
        $stack = new Stack(1);

        $stack->push('item1');

        $this->expectException(ChannelFullException::class);

        $stack->push('item2');
    }

    public function testPopReturnsItemFromStack(): void
    {
        $stack = new Stack(2);

        $stack->push('item1');
        $stack->push('item2');

        $this->assertSame('item2', $stack->pop());
        $this->assertSame(1, $stack->length());

        $this->assertSame('item1', $stack->pop());
        $this->assertSame(0, $stack->length());
    }

    public function testPopThrowsExceptionWhenEmpty(): void
    {
        $stack = new Stack(1);

        $this->expectException(ChannelEmptyException::class);

        $stack->pop();
    }

    public function testIsEmptyReturnsTrueWhenEmpty(): void
    {
        $stack = new Stack(1);

        $this->assertTrue($stack->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasItems(): void
    {
        $stack = new Stack(1);

        $stack->push('item');

        $this->assertFalse($stack->isEmpty());
    }

    public function testIsFullReturnsTrueWhenFull(): void
    {
        $stack = new Stack(1);

        $stack->push('item');

        $this->assertTrue($stack->isFull());
    }

    public function testIsFullReturnsFalseWhenNotFull(): void
    {
        $stack = new Stack(2);

        $stack->push('item');

        $this->assertFalse($stack->isFull());
    }

    public function testLengthReturnsCorrectLength(): void
    {
        $stack = new Stack(3);

        $this->assertSame(0, $stack->length());

        $stack->push('item1');
        $this->assertSame(1, $stack->length());

        $stack->push('item2');
        $this->assertSame(2, $stack->length());

        $stack->pop();
        $this->assertSame(1, $stack->length());
    }

    public function testCapacityReturnsCorrectCapacity(): void
    {
        $stack = new Stack(5);

        $this->assertSame(5, $stack->capacity());
    }

    public function testPushPopSequence(): void
    {
        $stack = new Stack(3);

        $stack->push('a');
        $stack->push('b');
        $stack->push('c');

        $this->assertSame('c', $stack->pop());
        $this->assertSame('b', $stack->pop());
        $this->assertSame('a', $stack->pop());

        $this->assertTrue($stack->isEmpty());
    }

    public function testPushPopWithDifferentTypes(): void
    {
        $stack = new Stack(1);

        $stack->push('string');
        $this->assertSame('string', $stack->pop());

        $stack->push(123);
        $this->assertSame(123, $stack->pop());

        $stack->push(['array' => 'value']);
        $this->assertSame(['array' => 'value'], $stack->pop());
    }
}
