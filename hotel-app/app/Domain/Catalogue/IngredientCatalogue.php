<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/** P09 — reusable ingredient library with versions and compound composition. */
final class IngredientCatalogue
{
    public function __construct(private readonly MealCatalogue $meals) {}

    /** @return array{items:list<object>,total:int,page:int,pages:int} */
    public function list(string $search = '', int $page = 1, bool $archived = false, int $perPage = 24): array
    {
        $query = DB::table('ingredients')->where('active', $archived ? 0 : 1);
        if ($search !== '') {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
        }
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $items = $query->orderBy('name')->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();
        $ids = array_map(static fn ($i) => $i->id, $items);
        $usage = DB::table('meal_ingredients')->whereIn('ingredient_id', $ids)->groupBy('ingredient_id')
            ->selectRaw('ingredient_id, COUNT(*) as total')->pluck('total', 'ingredient_id')->all();
        foreach ($items as $item) {
            $item->meal_count = (int) ($usage[$item->id] ?? 0);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function find(string $id): ?object
    {
        return Ids::valid($id) ? DB::table('ingredients')->where('id', $id)->first() : null;
    }

    /** @return list<object> */
    public function all(): array
    {
        return DB::table('ingredients')->where('active', 1)->orderBy('name')->get()->all();
    }

    /** @param array{name:string,description?:?string,allergen_notes?:?string,preparation_notes?:?string,media_id?:?string} $data */
    public function create(array $data, string $actorId): string
    {
        $clean = $this->validate($data);

        return Tx::run(function () use ($clean, $actorId): string {
            if (DB::table('ingredients')->where('active', 1)->where('name', $clean['name'])->exists()) {
                throw DomainError::conflict('DUPLICATE_NAME', 'An active ingredient already has that name.');
            }
            $id = Ids::new();
            $now = now('UTC');
            DB::table('ingredients')->insert($clean + ['id' => $id, 'active' => 1, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->snapshot($id, $actorId);
            Audit::record('ingredient_created', $actorId, ['ingredient_id' => $id, 'name' => $clean['name']]);

            return $id;
        });
    }

    /**
     * Versioned edit. Draft meals using this ingredient get a new draft
     * digest, which invalidates any earlier kitchen approval. Published meal
     * versions keep their own frozen snapshot and are unaffected.
     *
     * @return list<string> impacted meal names needing review
     */
    public function update(string $id, int $expectedVersion, array $data, string $actorId): array
    {
        $clean = $this->validate($data);

        return Tx::run(function () use ($id, $expectedVersion, $clean, $actorId): array {
            $row = Tx::lock('ingredients', $id);
            if ($row === null) {
                throw DomainError::notFound('Ingredient not found.');
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'This ingredient changed since you opened it. Reload and review.', 412);
            }
            if ($clean['name'] !== $row->name && DB::table('ingredients')->where('active', 1)->where('name', $clean['name'])->where('id', '!=', $id)->exists()) {
                throw DomainError::conflict('DUPLICATE_NAME', 'An active ingredient already has that name.');
            }
            DB::table('ingredients')->where('id', $id)->update($clean + ['version' => $expectedVersion + 1, 'updated_at' => now('UTC')]);
            $this->snapshot($id, $actorId);
            Audit::record('ingredient_updated', $actorId, ['ingredient_id' => $id, 'version' => $expectedVersion + 1]);

            return $this->meals->refreshDigestsForIngredient($id);
        });
    }

    /** @param list<string> $childIds */
    public function setComponents(string $id, array $childIds, string $actorId): array
    {
        $childIds = array_values(array_unique(array_filter($childIds, [Ids::class, 'valid'])));

        return Tx::run(function () use ($id, $childIds, $actorId): array {
            if (Tx::lock('ingredients', $id) === null) {
                throw DomainError::notFound('Ingredient not found.');
            }
            if (in_array($id, $childIds, true)) {
                throw DomainError::invalid('An ingredient cannot contain itself.');
            }
            $known = DB::table('ingredients')->whereIn('id', $childIds)->pluck('id')->all();
            if (count($known) !== count($childIds)) {
                throw DomainError::invalid('One of the chosen components no longer exists.');
            }
            // Reject multi-level cycles: no chosen child may (transitively) contain $id.
            $edges = [];
            foreach (DB::table('ingredient_components')->where('parent_ingredient_id', '!=', $id)->get() as $edge) {
                $edges[$edge->parent_ingredient_id][] = $edge->child_ingredient_id;
            }
            $edges[$id] = $childIds;
            $stack = $childIds;
            $seen = [];
            while ($stack !== []) {
                $node = array_pop($stack);
                if ($node === $id) {
                    throw DomainError::invalid('That composition would create a loop (an ingredient containing itself through another).');
                }
                if (isset($seen[$node])) {
                    continue;
                }
                $seen[$node] = true;
                foreach ($edges[$node] ?? [] as $next) {
                    $stack[] = $next;
                }
            }
            DB::table('ingredient_components')->where('parent_ingredient_id', $id)->delete();
            $now = now('UTC');
            foreach ($childIds as $child) {
                DB::table('ingredient_components')->insert(['id' => Ids::new(), 'parent_ingredient_id' => $id, 'child_ingredient_id' => $child, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('ingredients')->where('id', $id)->increment('version', 1, ['updated_at' => $now]);
            $this->snapshot($id, $actorId);
            Audit::record('ingredient_composition_changed', $actorId, ['ingredient_id' => $id, 'components' => count($childIds)]);

            return $this->meals->refreshDigestsForIngredient($id);
        });
    }

    /** @return list<object> */
    public function components(string $id): array
    {
        return DB::table('ingredient_components')->join('ingredients', 'ingredients.id', '=', 'ingredient_components.child_ingredient_id')
            ->where('ingredient_components.parent_ingredient_id', $id)->orderBy('ingredients.name')
            ->get(['ingredients.id', 'ingredients.name'])->all();
    }

    /** @return list<string> nested component names, depth-first, bounded */
    public function flatComponentNames(string $id): array
    {
        return Composition::flatNames($id);
    }

    public function setActive(string $id, bool $active, string $actorId): void
    {
        Tx::run(function () use ($id, $active, $actorId): void {
            $row = Tx::lock('ingredients', $id);
            if ($row === null) {
                throw DomainError::notFound('Ingredient not found.');
            }
            if ($active && DB::table('ingredients')->where('active', 1)->where('name', $row->name)->where('id', '!=', $id)->exists()) {
                throw DomainError::conflict('DUPLICATE_NAME', 'Another active ingredient already uses this name.');
            }
            // Archiving keeps every reference: meals and published snapshots still resolve it.
            DB::table('ingredients')->where('id', $id)->update(['active' => $active ? 1 : 0, 'version' => (int) $row->version + 1, 'updated_at' => now('UTC')]);
            Audit::record($active ? 'ingredient_restored' : 'ingredient_archived', $actorId, ['ingredient_id' => $id]);
        });
    }

    /** @return list<object> */
    public function versions(string $id): array
    {
        return DB::table('ingredient_versions')->where('ingredient_id', $id)->orderByDesc('version')->limit(20)->get()->all();
    }

    private function snapshot(string $id, string $actorId): void
    {
        $row = DB::table('ingredients')->where('id', $id)->first();
        DB::table('ingredient_versions')->insert([
            'id' => Ids::new(), 'ingredient_id' => $id, 'version' => (int) $row->version,
            'snapshot' => json_encode([
                'name' => $row->name, 'description' => $row->description, 'allergen_notes' => $row->allergen_notes,
                'preparation_notes' => $row->preparation_notes, 'media_id' => $row->media_id,
                'components' => array_map(static fn ($c) => $c->id, $this->components($id)),
            ], JSON_THROW_ON_ERROR),
            'actor_staff_user_id' => Ids::valid($actorId) ? $actorId : null, 'created_at' => now('UTC'),
        ]);
    }

    /** @return array<string, ?string> */
    private function validate(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw DomainError::invalid('Enter an ingredient name of up to 120 characters.');
        }
        $out = ['name' => $name];
        foreach (['description' => 500, 'allergen_notes' => 500, 'preparation_notes' => 500] as $field => $max) {
            $value = trim((string) ($data[$field] ?? ''));
            if (mb_strlen($value) > $max) {
                throw DomainError::invalid(ucfirst(str_replace('_', ' ', $field)).' must be at most '.$max.' characters.');
            }
            $out[$field] = $value === '' ? null : $value;
        }
        $media = $data['media_id'] ?? null;
        if ($media !== null && $media !== '') {
            if (! Ids::valid($media) || ! DB::table('media')->where('id', $media)->exists()) {
                throw DomainError::invalid('Choose a valid uploaded photo.');
            }
            $out['media_id'] = $media;
        } else {
            $out['media_id'] = null;
        }

        return $out;
    }
}
