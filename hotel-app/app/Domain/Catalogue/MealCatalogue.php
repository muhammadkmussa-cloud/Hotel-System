<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Domain\Media\MediaLibrary;
use App\Domain\Money;
use App\Domain\Outbox;
use App\Domain\Tx;
use Illuminate\Support\Facades\DB;

/**
 * P10 — meal drafts, kitchen recipe review bound to an exact draft digest,
 * guarded publication of immutable versions, and published menu reads.
 */
final class MealCatalogue
{
    public const MAX_INGREDIENTS = 24;

    public function __construct(private readonly MediaLibrary $media) {}

    // ----- Categories (P10.01) -----

    /** @return list<object> */
    public function categories(bool $includeInactive = false): array
    {
        $q = DB::table('categories')->orderBy('display_order')->orderBy('name');
        if (! $includeInactive) {
            $q->where('active', 1);
        }

        return $q->get()->all();
    }

    public function saveCategory(?string $id, string $name, int $order, bool $active, string $actorId): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw DomainError::invalid('Enter a category name of up to 80 characters.');
        }
        $now = now('UTC');
        if ($id === null) {
            $id = Ids::new();
            DB::table('categories')->insert(['id' => $id, 'name' => $name, 'display_order' => max(0, $order), 'active' => $active ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
        } else {
            if (DB::table('categories')->where('id', $id)->update(['name' => $name, 'display_order' => max(0, $order), 'active' => $active ? 1 : 0, 'updated_at' => $now]) === 0) {
                throw DomainError::notFound('Category not found.');
            }
        }
        Audit::record('category_saved', $actorId, ['category_id' => $id, 'name' => $name]);
        Outbox::emit('menu.changed', 'menu');

        return $id;
    }

    // ----- Drafts (P10.02–P10.05) -----

    public function find(string $id): ?object
    {
        return Ids::valid($id) ? DB::table('meals')->where('id', $id)->first() : null;
    }

    /** @return list<object> */
    public function adminList(bool $archived = false): array
    {
        $meals = DB::table('meals')->leftJoin('categories', 'categories.id', '=', 'meals.category_id')
            ->where('meals.archived', $archived ? 1 : 0)
            ->orderBy('categories.display_order')->orderBy('meals.display_order')->orderBy('meals.name')
            ->get(['meals.*', 'categories.name as category_name'])->all();
        foreach ($meals as $meal) {
            $meal->status = $this->status($meal);
        }

        return $meals;
    }

    public function status(object $meal): string
    {
        if ($meal->archived) {
            return 'archived';
        }
        $approved = $meal->recipe_approved_digest !== null && $meal->recipe_approved_digest === $meal->draft_digest;
        $publishedDigest = $meal->published_version === null ? null
            : DB::table('meal_versions')->where('meal_id', $meal->id)->where('version', $meal->published_version)->value('digest');
        if ($publishedDigest !== null && $publishedDigest === $meal->draft_digest) {
            return 'published';
        }
        if ($approved) {
            return 'approved';
        }

        return $meal->published_version === null ? 'draft' : 'published_with_changes';
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, string $actorId): string
    {
        $clean = $this->validateDraft($data);

        return Tx::run(function () use ($clean, $actorId): string {
            $id = Ids::new();
            $now = now('UTC');
            DB::table('meals')->insert($clean + ['id' => $id, 'version' => 1, 'archived' => 0, 'sellable' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->refreshDigest($id);
            Audit::record('meal_draft_created', $actorId, ['meal_id' => $id, 'name' => $clean['name']]);

            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    public function updateDraft(string $id, int $expectedVersion, array $data, string $actorId): void
    {
        $clean = $this->validateDraft($data);
        Tx::run(function () use ($id, $expectedVersion, $clean, $actorId): void {
            $row = Tx::lock('meals', $id);
            if ($row === null) {
                throw DomainError::notFound('Meal not found.');
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'This meal changed since you opened it. Reload to see the latest draft.', 412);
            }
            DB::table('meals')->where('id', $id)->update($clean + ['version' => $expectedVersion + 1, 'updated_at' => now('UTC')]);
            $this->refreshDigest($id);
            Audit::record('meal_draft_updated', $actorId, ['meal_id' => $id, 'version' => $expectedVersion + 1]);
        });
    }

    /**
     * Replace the meal's ingredient rules. Rules belong to this meal only;
     * the reusable ingredient is never modified.
     *
     * @param list<array{ingredient_id:string,rule:string,extra_price_minor?:int}> $rules
     */
    public function setIngredients(string $id, int $expectedVersion, array $rules, string $actorId): void
    {
        if (count($rules) > self::MAX_INGREDIENTS) {
            throw DomainError::invalid('A meal can list at most '.self::MAX_INGREDIENTS.' ingredients.');
        }
        $seen = [];
        foreach ($rules as $rule) {
            if (! Ids::valid($rule['ingredient_id'] ?? null) || ! in_array($rule['rule'] ?? '', ['fixed', 'removable', 'extra'], true)) {
                throw DomainError::invalid('Each ingredient needs a rule: fixed, removable or extra.');
            }
            if (isset($seen[$rule['ingredient_id']])) {
                throw DomainError::invalid('An ingredient can appear only once per meal.');
            }
            $seen[$rule['ingredient_id']] = true;
            $extra = (int) ($rule['extra_price_minor'] ?? 0);
            if ($extra < 0 || $extra > 100_000_00) {
                throw DomainError::invalid('Extra prices must be zero or positive.');
            }
        }
        Tx::run(function () use ($id, $expectedVersion, $rules, $actorId): void {
            $row = Tx::lock('meals', $id);
            if ($row === null) {
                throw DomainError::notFound('Meal not found.');
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'This meal changed since you opened it. Reload to see the latest draft.', 412);
            }
            $ids = array_column($rules, 'ingredient_id');
            if (DB::table('ingredients')->whereIn('id', $ids)->count() !== count($ids)) {
                throw DomainError::invalid('One of the ingredients no longer exists.');
            }
            DB::table('meal_ingredients')->where('meal_id', $id)->delete();
            $now = now('UTC');
            foreach (array_values($rules) as $i => $rule) {
                DB::table('meal_ingredients')->insert([
                    'id' => Ids::new(), 'meal_id' => $id, 'ingredient_id' => $rule['ingredient_id'], 'rule' => $rule['rule'],
                    'extra_price_minor' => $rule['rule'] === 'extra' ? (int) ($rule['extra_price_minor'] ?? 0) : 0,
                    'display_order' => $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('meals')->where('id', $id)->update(['version' => $expectedVersion + 1, 'updated_at' => $now]);
            $this->refreshDigest($id);
            Audit::record('meal_ingredients_updated', $actorId, ['meal_id' => $id, 'count' => count($rules)]);
        });
    }

    /** @return list<object> */
    public function draftIngredients(string $id): array
    {
        return DB::table('meal_ingredients')->join('ingredients', 'ingredients.id', '=', 'meal_ingredients.ingredient_id')
            ->where('meal_ingredients.meal_id', $id)->orderBy('meal_ingredients.display_order')
            ->get(['meal_ingredients.*', 'ingredients.name', 'ingredients.media_id', 'ingredients.description', 'ingredients.allergen_notes', 'ingredients.active'])->all();
    }

    /** Canonical draft facts; any change here changes the digest. */
    public function draftFacts(string $id): array
    {
        $meal = DB::table('meals')->where('id', $id)->first();
        $category = $meal->category_id ? DB::table('categories')->where('id', $meal->category_id)->value('name') : null;
        $station = $meal->station_id ? DB::table('stations')->where('id', $meal->station_id)->value('name') : null;
        $ingredients = [];
        foreach ($this->draftIngredients($id) as $row) {
            $ingredients[] = [
                'id' => $row->ingredient_id, 'name' => $row->name, 'rule' => $row->rule,
                'extra_price_minor' => (int) $row->extra_price_minor, 'media_id' => $row->media_id,
                'description' => $row->description, 'allergen_notes' => $row->allergen_notes,
                'components' => Composition::flatNames($row->ingredient_id),
            ];
        }

        return [
            'id' => $meal->id, 'name' => $meal->name, 'description' => $meal->description,
            'price_minor' => (int) $meal->price_minor, 'media_id' => $meal->media_id,
            'category_id' => $meal->category_id, 'category_name' => $category,
            'station_id' => $meal->station_id, 'station_name' => $station,
            'ingredients' => $ingredients,
        ];
    }

    public function refreshDigest(string $id): string
    {
        $digest = hash('sha256', json_encode($this->draftFacts($id), JSON_THROW_ON_ERROR));
        DB::table('meals')->where('id', $id)->update(['draft_digest' => $digest]);

        return $digest;
    }

    /** @return list<string> names of meals whose draft changed because of this ingredient */
    public function refreshDigestsForIngredient(string $ingredientId): array
    {
        $names = [];
        $mealIds = DB::table('meal_ingredients')->where('ingredient_id', $ingredientId)->pluck('meal_id')->all();
        foreach (DB::table('meals')->whereIn('id', $mealIds)->get(['id', 'name', 'draft_digest', 'recipe_approved_digest']) as $meal) {
            $new = $this->refreshDigest($meal->id);
            if ($new !== $meal->draft_digest) {
                $names[] = $meal->name;
            }
        }

        return $names;
    }

    // ----- Review and publication (P10.07/P10.08) -----

    public function approveRecipe(string $id, string $digest, string $actorId, ?string $note): void
    {
        Tx::run(function () use ($id, $digest, $actorId, $note): void {
            $row = Tx::lock('meals', $id);
            if ($row === null) {
                throw DomainError::notFound('Meal not found.');
            }
            $current = $this->refreshDigest($id);
            if (! hash_equals($current, $digest)) {
                throw DomainError::conflict('DRAFT_CHANGED', 'The recipe changed while you were reviewing it. Review the latest draft.');
            }
            if (DB::table('meal_ingredients')->where('meal_id', $id)->count() === 0) {
                throw DomainError::conflict('NO_INGREDIENTS', 'Add the meal\'s ingredients before kitchen review.');
            }
            DB::table('meals')->where('id', $id)->update([
                'recipe_approved_digest' => $current, 'recipe_approved_by' => $actorId,
                'recipe_approved_at' => now('UTC'), 'recipe_review_note' => $note !== null ? mb_substr($note, 0, 500) : null,
            ]);
            Audit::record('meal_recipe_approved', $actorId, ['meal_id' => $id, 'digest' => substr($current, 0, 16)]);
        });
    }

    public function publish(string $id, string $actorId): int
    {
        return Tx::run(function () use ($id, $actorId): int {
            $row = Tx::lock('meals', $id);
            if ($row === null) {
                throw DomainError::notFound('Meal not found.');
            }
            if ($row->archived) {
                throw DomainError::conflict('ARCHIVED', 'Restore this meal before publishing it.');
            }
            $digest = $this->refreshDigest($id);
            if ($row->recipe_approved_digest === null || ! hash_equals($row->recipe_approved_digest, $digest)) {
                throw DomainError::conflict('REVIEW_REQUIRED', 'The kitchen must approve this exact recipe before it can be published.');
            }
            $facts = $this->draftFacts($id);
            if ($facts['media_id'] !== null) {
                $state = DB::table('media')->where('id', $facts['media_id'])->value('publication_state');
                if (! in_array($state, ['published', 'demo'], true)) {
                    throw DomainError::conflict('IMAGE_NOT_PUBLISHED', 'Publish the meal photo (with alt text) before publishing the meal.');
                }
            }
            $last = (int) DB::table('meal_versions')->where('meal_id', $id)->max('version');
            $latestDigest = $last > 0 ? DB::table('meal_versions')->where('meal_id', $id)->where('version', $last)->value('digest') : null;
            if ($latestDigest === $digest && (int) $row->published_version === $last) {
                return $last; // Already live; publishing is idempotent.
            }
            $version = $last + 1;
            DB::table('meal_versions')->insert([
                'id' => Ids::new(), 'meal_id' => $id, 'version' => $version, 'digest' => $digest,
                'snapshot' => json_encode($facts, JSON_THROW_ON_ERROR), 'published_by' => $actorId, 'published_at' => now('UTC'),
            ]);
            DB::table('meals')->where('id', $id)->update(['published_version' => $version, 'published_at' => now('UTC')]);
            Audit::record('meal_published', $actorId, ['meal_id' => $id, 'version' => $version]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $id]);

            return $version;
        });
    }

    public function unpublish(string $id, string $actorId): void
    {
        Tx::run(function () use ($id, $actorId): void {
            if (Tx::lock('meals', $id) === null) {
                throw DomainError::notFound('Meal not found.');
            }
            DB::table('meals')->where('id', $id)->update(['published_version' => null, 'updated_at' => now('UTC')]);
            Audit::record('meal_unpublished', $actorId, ['meal_id' => $id]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $id]);
        });
    }

    public function setArchived(string $id, bool $archived, string $actorId): void
    {
        Tx::run(function () use ($id, $archived, $actorId): void {
            if (Tx::lock('meals', $id) === null) {
                throw DomainError::notFound('Meal not found.');
            }
            $changes = ['archived' => $archived ? 1 : 0, 'updated_at' => now('UTC')];
            if ($archived) {
                $changes['published_version'] = null;
            }
            DB::table('meals')->where('id', $id)->update($changes);
            Audit::record($archived ? 'meal_archived' : 'meal_restored', $actorId, ['meal_id' => $id]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $id]);
        });
    }

    // ----- Availability (P14.01) -----

    public function setAvailability(string $id, bool $sellable, ?int $portions, ?string $reason, int $expectedVersion, string $actorId): void
    {
        if ($portions !== null && ($portions < 0 || $portions > 100000)) {
            throw DomainError::invalid('Portions must be between 0 and 100000, or left blank for uncounted.');
        }
        Tx::run(function () use ($id, $sellable, $portions, $reason, $expectedVersion, $actorId): void {
            $row = Tx::lock('meals', $id);
            if ($row === null) {
                throw DomainError::notFound('Meal not found.');
            }
            if ((int) $row->availability_version !== $expectedVersion) {
                throw new DomainError('VERSION_CONFLICT', 'Availability changed while you were editing (an order may have used a portion). Reload and retry.', 412);
            }
            $reason = $reason !== null ? mb_substr(trim($reason), 0, 200) : null;
            DB::table('meals')->where('id', $id)->update([
                'sellable' => $sellable ? 1 : 0, 'portions_remaining' => $portions,
                'availability_reason' => $reason === '' ? null : $reason,
                'availability_version' => $expectedVersion + 1, 'updated_at' => now('UTC'),
            ]);
            DB::table('availability_changes')->insert([
                'id' => Ids::new(), 'meal_id' => $id, 'sellable' => $sellable ? 1 : 0, 'portions_remaining' => $portions,
                'reason' => $reason, 'actor_staff_user_id' => $actorId, 'created_at' => now('UTC'),
            ]);
            Audit::record('availability_changed', $actorId, ['meal_id' => $id, 'sellable' => $sellable, 'portions' => $portions]);
            Outbox::emit('menu.changed', 'menu', ['meal_id' => $id]);
        });
    }

    // ----- Published reads (P10.09, P11) -----

    /** Snapshot of the live published version, or null if not on the menu. */
    public function publishedSnapshot(string $mealId, ?int $version = null): ?array
    {
        $meal = DB::table('meals')->where('id', $mealId)->first(['published_version', 'archived']);
        if ($meal === null) {
            return null;
        }
        $version ??= $meal->published_version;
        if ($version === null) {
            return null;
        }
        $json = DB::table('meal_versions')->where('meal_id', $mealId)->where('version', $version)->value('snapshot');

        return is_string($json) ? json_decode($json, true) : null;
    }

    /** Customer-safe projection of a published meal (no staff allergen notes). */
    public function customerMeal(string $mealId): ?array
    {
        $meal = DB::table('meals')->where('id', $mealId)->where('archived', 0)->whereNotNull('published_version')->first();
        if ($meal === null) {
            return null;
        }
        $snapshot = $this->publishedSnapshot($mealId, (int) $meal->published_version);
        if ($snapshot === null) {
            return null;
        }
        $available = $this->isAvailable($meal);
        $ingredients = [];
        foreach ($snapshot['ingredients'] as $ing) {
            $ingredients[] = [
                'id' => $ing['id'], 'name' => $ing['name'], 'rule' => $ing['rule'],
                'extraPriceMinor' => (int) $ing['extra_price_minor'],
                'extraPrice' => $ing['rule'] === 'extra' ? Money::format((int) $ing['extra_price_minor']) : null,
                'description' => $ing['description'], 'components' => $ing['components'],
                'image' => $this->media->image($ing['media_id'], 'portrait', 'ingredient'),
            ];
        }

        return [
            'id' => $meal->id, 'version' => (int) $meal->published_version,
            'name' => $snapshot['name'], 'description' => $snapshot['description'],
            'priceMinor' => (int) $snapshot['price_minor'], 'price' => Money::format((int) $snapshot['price_minor']),
            'categoryId' => $snapshot['category_id'],
            'available' => $available,
            'availabilityNote' => $available ? (($meal->portions_remaining !== null && (int) $meal->portions_remaining <= 3) ? 'Only '.(int) $meal->portions_remaining.' left' : null)
                : ($meal->availability_reason ?: 'Sold out for now'),
            'image' => $this->media->image($snapshot['media_id'], 'card', 'meal'),
            'hero' => $this->media->image($snapshot['media_id'], 'hero', 'meal'),
            'ingredients' => $ingredients,
        ];
    }

    public function isAvailable(object $meal): bool
    {
        return (bool) $meal->sellable && ($meal->portions_remaining === null || (int) $meal->portions_remaining > 0);
    }

    /** @return array{categories:list<array>,meals:list<array>,version:int} */
    public function menu(): array
    {
        $meals = [];
        $ids = DB::table('meals')->where('archived', 0)->whereNotNull('published_version')
            ->orderBy('display_order')->orderBy('name')->pluck('id')->all();
        foreach ($ids as $id) {
            $meal = $this->customerMeal($id);
            if ($meal !== null) {
                $meals[] = $meal;
            }
        }
        $used = array_unique(array_filter(array_column($meals, 'categoryId')));
        $categories = [];
        foreach ($this->categories() as $cat) {
            if (in_array($cat->id, $used, true)) {
                $categories[] = ['id' => $cat->id, 'name' => $cat->name];
            }
        }
        if (in_array(null, array_column($meals, 'categoryId'), true)) {
            $categories[] = ['id' => null, 'name' => 'More'];
        }

        return ['categories' => $categories, 'meals' => $meals, 'version' => Outbox::latestId()];
    }

    /** @param array<string,mixed> $data */
    private function validateDraft(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw DomainError::invalid('Enter a meal name of up to 120 characters.');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            throw DomainError::invalid('The description must be at most 1000 characters.');
        }
        $price = $data['price_minor'] ?? null;
        if (! is_int($price) || $price < 0 || $price > 10_000_000_00) {
            throw DomainError::invalid('Enter a valid price in Kenyan shillings.');
        }
        $out = ['name' => $name, 'description' => $description === '' ? null : $description, 'price_minor' => $price,
            'display_order' => max(0, (int) ($data['display_order'] ?? 0))];
        foreach (['category_id' => 'categories', 'station_id' => 'stations', 'media_id' => 'media'] as $field => $table) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                $out[$field] = null;
                continue;
            }
            if (! Ids::valid($value) || ! DB::table($table)->where('id', $value)->exists()) {
                throw DomainError::invalid('Choose a valid '.str_replace('_id', '', $field).'.');
            }
            $out[$field] = $value;
        }

        return $out;
    }
}
