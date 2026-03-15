<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class BlockedDateConflictsDisplay extends Field
{
    protected string $view = 'filament.forms.components.blocked-date-conflicts-display';

    protected array | Closure $conflicts = [];

    public function conflicts(array | Closure $conflicts): static
    {
        $this->conflicts = $conflicts;
        return $this;
    }

    public function getConflicts(): array
    {
        return $this->evaluate($this->conflicts) ?? [];
    }
}
