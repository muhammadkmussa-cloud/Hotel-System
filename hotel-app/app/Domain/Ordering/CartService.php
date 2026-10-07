<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use App\Domain\Catalogue\MealCatalogue;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Domain\Media\MediaLibrary;
use App\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * P12 — session-bound draft carts and authoritative server quotes. Drafts
 * never create charges or kitchen work; browser-supplied prices are ignored.
 */
final class CartService
{
    public const MAX_LINES = 30;

    public const MAX_QUANTITY = 20;

    public const DRAFT_HOURS = 6;

    public function __construct(private readonly MealCatalogue $meals, private readonly MediaLibrary $media) {}

    /** @return list<object> */
    private function rows(string $ownerType, string $ownerId): array
    {
        DB::table('cart_lines')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('expires_at', '<', now('UTC'))->delete();

        return DB::table('cart_lines')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->orderBy('created_at')->get()->all();
    }

    /**
     * @param list<string> $removed
     * @param list<string> $extras
     */
    public function add(string $ownerType, string $ownerId, string $mealId, array $removed, array $extras, int $quantity, ?string $note): void
    {
        [$snapshot, $version] = $this->liveMeal($mealId);
        [$removed, $extras] = $this->validateChoices($snapshot, $removed, $extras);
        $this->validateQuantity($quantity);
        $note = $this->cleanNote($note);
        $rows = $this->rows($ownerType, $ownerId);
        foreach ($rows as $row) {
            if ($row->meal_id === $mealId && (int) $row->meal_version === $version
                && $this->same(json_decode($row->removed_ingredient_ids, true), $removed)
                && $this->same(json_decode($row->extra_ingredient_ids, true), $extras)
                && (string) $row->note === (string) $note) {
                $this->validateQuantity((int) $row->quantity + $quantity);
                DB::table('cart_lines')->where('id', $row->id)->update(['quantity' => (int) $row->quantity + $quantity, 'expires_at' => now('UTC')->addHours(self::DRAFT_HOURS), 'updated_at' => now('UTC')]);

                return;
            }
        }
        if (count($rows) >= self::MAX_LINES) {
            throw DomainError::conflict('CART_FULL', 'Your order already has the maximum number of lines. Submit it first, then add more.');
        }
        DB::table('cart_lines')->insert([
            'id' => Ids::new(), 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'meal_id' => $mealId, 'meal_version' => $version,
            'removed_ingredient_ids' => json_encode(array_values($removed)), 'extra_ingredient_ids' => json_encode(array_values($extras)),
            'quantity' => $quantity, 'note' => $note, 'expires_at' => now('UTC')->addHours(self::DRAFT_HOURS),
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
    }

    public function update(string $ownerType, string $ownerId, string $lineId, int $quantity, ?array $removed = null, ?array $extras = null, ?string $note = null): void
    {
        $row = DB::table('cart_lines')->where('id', $lineId)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->first();
        if ($row === null) {
            throw DomainError::notFound('That item is no longer in your order.');
        }
        if (now('UTC')->greaterThan($row->expires_at)) {
            DB::table('cart_lines')->where('id', $lineId)->delete();
            throw DomainError::notFound('That item is no longer in your order.');
        }
        if ($quantity === 0) {
            DB::table('cart_lines')->where('id', $lineId)->delete();

            return;
        }
        $this->validateQuantity($quantity);
        $changes = ['quantity' => $quantity, 'expires_at' => now('UTC')->addHours(self::DRAFT_HOURS), 'updated_at' => now('UTC')];
        if ($removed !== null || $extras !== null) {
            // Validate against the version the guest actually added; never
            // silently rebase the line to a newer published price (P12.07/P12.08).
            $snapshot = $this->meals->publishedSnapshot($row->meal_id, (int) $row->meal_version);
            if ($snapshot === null) {
                throw DomainError::conflict('CART_CHANGED', 'This dish changed. Review your order and accept the changes.');
            }
            [$r, $e] = $this->validateChoices($snapshot, $removed ?? json_decode($row->removed_ingredient_ids, true), $extras ?? json_decode($row->extra_ingredient_ids, true));
            $changes += ['removed_ingredient_ids' => json_encode($r), 'extra_ingredient_ids' => json_encode($e)];
        }
        if ($note !== null) {
            $changes['note'] = $this->cleanNote($note);
        }
        DB::table('cart_lines')->where('id', $lineId)->update($changes);
    }

    public function remove(string $ownerType, string $ownerId, string $lineId): void
    {
        DB::table('cart_lines')->where('id', $lineId)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();
    }

    public function clear(string $ownerType, string $ownerId): void
    {
        DB::table('cart_lines')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();
    }

    /**
     * Rebase every line onto the current published version, dropping choices
     * that are no longer permitted. Used after the guest reviews a conflict.
     */
    public function acceptCurrent(string $ownerType, string $ownerId): void
    {
        foreach ($this->rows($ownerType, $ownerId) as $row) {
            $meal = DB::table('meals')->where('id', $row->meal_id)->first();
            $snapshot = $meal && ! $meal->archived && $meal->published_version ? $this->meals->publishedSnapshot($row->meal_id) : null;
            if ($snapshot === null || ! $this->meals->isAvailable($meal)) {
                DB::table('cart_lines')->where('id', $row->id)->delete();
                continue;
            }
            $removable = $this->idsByRule($snapshot, 'removable');
            $extraIds = $this->idsByRule($snapshot, 'extra');
            DB::table('cart_lines')->where('id', $row->id)->update([
                'meal_version' => (int) $meal->published_version,
                'removed_ingredient_ids' => json_encode(array_values(array_intersect(json_decode($row->removed_ingredient_ids, true), $removable))),
                'extra_ingredient_ids' => json_encode(array_values(array_intersect(json_decode($row->extra_ingredient_ids, true), $extraIds))),
                'updated_at' => now('UTC'),
            ]);
        }
    }

    /**
     * Authoritative quote from current published data. Conflicts (changed
     * versions, unavailable meals, short portions) must be resolved by the
     * customer before submission; the digest pins exactly what was shown.
     *
     * @return array{lines:list<array>,totalMinor:int,total:string,conflicts:list<array>,digest:string,count:int}
     */
    public function quote(string $ownerType, string $ownerId): array
    {
        $lines = [];
        $conflicts = [];
        $total = 0;
        $count = 0;
        $demand = [];
        foreach ($this->rows($ownerType, $ownerId) as $row) {
            $meal = DB::table('meals')->where('id', $row->meal_id)->first();
            $current = $meal && ! $meal->archived && $meal->published_version !== null ? (int) $meal->published_version : null;
            $snapshot = $this->meals->publishedSnapshot($row->meal_id, (int) $row->meal_version);
            $removed = json_decode($row->removed_ingredient_ids, true);
            $extras = json_decode($row->extra_ingredient_ids, true);
            $line = $this->priceLine($row, $snapshot, $removed, $extras);
            if ($current === null) {
                $conflicts[] = ['lineId' => $row->id, 'type' => 'withdrawn', 'message' => ($line['name'] ?? 'An item').' is no longer on the menu.'];
            } elseif ($current !== (int) $row->meal_version) {
                $currentSnapshot = $this->meals->publishedSnapshot($row->meal_id, $current);
                $newLine = $this->priceLine($row, $currentSnapshot, $removed, $extras);
                $conflicts[] = ['lineId' => $row->id, 'type' => 'changed', 'message' => $line['name'].' was updated by the kitchen'.($newLine['unitPriceMinor'] !== $line['unitPriceMinor'] ? ' — the price is now '.Money::format($newLine['unitPriceMinor']) : '').'. Please review it.'];
            } elseif (! $this->meals->isAvailable($meal)) {
                $conflicts[] = ['lineId' => $row->id, 'type' => 'unavailable', 'message' => $line['name'].' is sold out right now.'];
            } else {
                $demand[$row->meal_id] = ($demand[$row->meal_id] ?? 0) + (int) $row->quantity;
                if ($meal->portions_remaining !== null && $demand[$row->meal_id] > (int) $meal->portions_remaining) {
                    $conflicts[] = ['lineId' => $row->id, 'type' => 'insufficient', 'message' => 'Only '.(int) $meal->portions_remaining.' × '.$line['name'].' left.'];
                }
            }
            $total += $line['lineTotalMinor'];
            $count += (int) $row->quantity;
            $lines[] = $line;
        }
        $canonical = array_map(static fn (array $l) => [$l['id'], $l['mealId'], $l['mealVersion'], $l['removedIds'], $l['extraIds'], $l['quantity'], $l['unitPriceMinor'], $l['extrasMinor'], $l['note']], $lines);

        return [
            'lines' => $lines, 'totalMinor' => $total, 'total' => Money::format($total), 'count' => $count,
            'conflicts' => $conflicts, 'digest' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)),
        ];
    }

    private function priceLine(object $row, ?array $snapshot, array $removed, array $extras): array
    {
        $byId = [];
        foreach ($snapshot['ingredients'] ?? [] as $ing) {
            $byId[$ing['id']] = $ing;
        }
        $extrasMinor = 0;
        foreach ($extras as $id) {
            $extrasMinor += (int) ($byId[$id]['extra_price_minor'] ?? 0);
        }
        $unit = (int) ($snapshot['price_minor'] ?? 0);
        $qty = (int) $row->quantity;

        return [
            'id' => $row->id, 'mealId' => $row->meal_id, 'mealVersion' => (int) $row->meal_version,
            'name' => $snapshot['name'] ?? 'Unavailable item', 'quantity' => $qty, 'note' => $row->note,
            'removedIds' => $removed, 'extraIds' => $extras,
            'removed' => array_values(array_map(static fn ($id) => $byId[$id]['name'] ?? '—', $removed)),
            'extras' => array_values(array_map(static fn ($id) => ['name' => $byId[$id]['name'] ?? '—', 'price' => Money::format((int) ($byId[$id]['extra_price_minor'] ?? 0))], $extras)),
            'unitPriceMinor' => $unit, 'extrasMinor' => $extrasMinor,
            'lineTotalMinor' => ($unit + $extrasMinor) * $qty, 'lineTotal' => Money::format(($unit + $extrasMinor) * $qty),
            'image' => $this->media->image($snapshot['media_id'] ?? null, 'thumb', 'meal'),
        ];
    }

    /** @return array{0:array,1:int} */
    private function liveMeal(string $mealId): array
    {
        $meal = Ids::valid($mealId) ? DB::table('meals')->where('id', $mealId)->first() : null;
        if ($meal === null || $meal->archived || $meal->published_version === null) {
            throw DomainError::notFound('That dish is not on the menu.');
        }
        if (! $this->meals->isAvailable($meal)) {
            throw DomainError::conflict('UNAVAILABLE', 'That dish is sold out right now.');
        }

        return [$this->meals->publishedSnapshot($mealId, (int) $meal->published_version), (int) $meal->published_version];
    }

    /** Fixed ingredients cannot be removed, even by a forged request. */
    private function validateChoices(array $snapshot, array $removed, array $extras): array
    {
        $removed = array_values(array_unique(array_map('strval', $removed)));
        $extras = array_values(array_unique(array_map('strval', $extras)));
        if (array_diff($removed, $this->idsByRule($snapshot, 'removable')) !== []) {
            throw DomainError::invalid('Only the ingredients marked as removable can be taken out of this dish.');
        }
        if (array_diff($extras, $this->idsByRule($snapshot, 'extra')) !== []) {
            throw DomainError::invalid('That extra is not offered with this dish.');
        }
        sort($removed);
        sort($extras);

        return [$removed, $extras];
    }

    /** @return list<string> */
    private function idsByRule(array $snapshot, string $rule): array
    {
        return array_values(array_map(static fn ($i) => $i['id'], array_filter($snapshot['ingredients'], static fn ($i) => $i['rule'] === $rule)));
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw DomainError::invalid('Choose a quantity between 1 and '.self::MAX_QUANTITY.'.');
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);
        if (mb_strlen($note) > 200) {
            throw DomainError::invalid('Keep the note under 200 characters.');
        }

        return $note === '' ? null : $note;
    }

    private function same(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
