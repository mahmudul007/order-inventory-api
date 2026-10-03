<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    public function test_pending_can_transition_to_confirmed_or_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_confirmed_can_transition_to_processing_or_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Processing));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_processing_can_transition_to_shipped(): void
    {
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Processing->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_shipped_can_transition_to_delivered(): void
    {
        $this->assertTrue(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_final_states_cannot_transition(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertEmpty(OrderStatus::Delivered->allowedTransitions());
        $this->assertEmpty(OrderStatus::Cancelled->allowedTransitions());
    }
}
