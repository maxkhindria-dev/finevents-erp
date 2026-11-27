<?php

namespace App\Access;

use App\Models\Person;
use Illuminate\Support\Facades\Auth;

/**
 * Who is asking for data right now?
 *
 *  - A signed-in person (the normal case): records are filtered to what they may see.
 *  - The system (migrations, seeders, scheduled jobs): no filtering. Must be
 *    switched on explicitly with runAsSystem(), so it is visible in code review.
 *  - Nobody: every scoped query returns nothing (deny by default).
 */
final class AccessContext
{
    private bool $system = false;

    private ?Person $actingAs = null;

    public function viewer(): ?Person
    {
        if ($this->actingAs !== null) {
            return $this->actingAs;
        }

        $user = Auth::user();

        return $user instanceof Person ? $user : null;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    /**
     * Run trusted code without record filtering. Keep the closure small and
     * never pass user input straight into it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAsSystem(callable $callback): mixed
    {
        $previous = $this->system;
        $this->system = true;

        try {
            return $callback();
        } finally {
            $this->system = $previous;
        }
    }

    /**
     * Run code as a specific person, e.g. a queued job acting for whoever started it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Person $person, callable $callback): mixed
    {
        [$previousPerson, $previousSystem] = [$this->actingAs, $this->system];
        $this->actingAs = $person;
        $this->system = false;

        try {
            return $callback();
        } finally {
            [$this->actingAs, $this->system] = [$previousPerson, $previousSystem];
        }
    }
}
