<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use Illuminate\Support\Facades\DB;

final class Composition
{
    /** @return list<string> nested component names, e.g. ["Kachumbari (Tomato, Onion)"] */
    public static function flatNames(string $ingredientId, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }
        $names = [];
        $children = DB::table('ingredient_components')->join('ingredients', 'ingredients.id', '=', 'ingredient_components.child_ingredient_id')
            ->where('ingredient_components.parent_ingredient_id', $ingredientId)->orderBy('ingredients.name')
            ->get(['ingredients.id', 'ingredients.name']);
        foreach ($children as $child) {
            $nested = self::flatNames($child->id, $depth + 1);
            $names[] = $nested === [] ? $child->name : $child->name.' ('.implode(', ', $nested).')';
        }

        return $names;
    }
}
