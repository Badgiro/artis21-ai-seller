<?php

namespace App\Services;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Models\Furniture;
use Illuminate\Database\Eloquent\Collection;

class FurnitureRecommendationService
{
    /**
     * @return Collection<int, Furniture>
     */
    public function recommend(
        string $furnitureType,
        ?string $budget,
        ?string $style = null,
    ): Collection {
        $type = FurnitureType::tryFrom($furnitureType);

        if ($type === null) {
            return new Collection;
        }

        $normalizedBudget = $this->normalizeBudget($budget);
        $normalizedStyle = FurnitureStyle::tryFrom((string) $style)?->value;

        return Furniture::query()
            ->where('type', $type->value)
            ->get()
            ->sortBy(function (Furniture $furniture) use ($normalizedBudget, $normalizedStyle): array {
                $price = (float) $furniture->price;
                $stylePriority = $normalizedStyle !== null && $furniture->style->value === $normalizedStyle
                    ? 0
                    : 1;

                if ($normalizedBudget === null) {
                    return [$stylePriority, $price];
                }

                $budgetValue = (float) $normalizedBudget;
                $withinBudget = $price <= $budgetValue;

                return [
                    $withinBudget ? 0 : 1,
                    $stylePriority,
                    $withinBudget ? -$price : $price - $budgetValue,
                ];
            })
            ->take(3)
            ->values();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Furniture>
     */
    public function findByIds(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id),
        )));

        if ($ids === []) {
            return new Collection;
        }

        $furnitureById = Furniture::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Furniture $furniture): int => $furniture->getKey());

        return new Collection(array_values(array_filter(array_map(
            fn (int|string $id): ?Furniture => $furnitureById->get((int) $id),
            $ids,
        ))));
    }

    private function normalizeBudget(?string $budget): ?string
    {
        if ($budget === null || preg_match('/^\d+(?:\.\d+)?$/', $budget) !== 1) {
            return null;
        }

        return $budget;
    }
}
