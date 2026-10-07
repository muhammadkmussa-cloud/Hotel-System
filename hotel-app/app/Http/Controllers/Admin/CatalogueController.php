<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalogue\IngredientCatalogue;
use App\Domain\Catalogue\MealCatalogue;
use App\Domain\DomainError;
use App\Domain\Media\MediaLibrary;
use App\Domain\Money;
use App\Security\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** S24–S27 — ingredients, meals, media and availability. */
final class CatalogueController
{
    public function __construct(
        private readonly MealCatalogue $meals,
        private readonly IngredientCatalogue $ingredients,
        private readonly MediaLibrary $media,
    ) {}

    private function actor(Request $request): string
    {
        return (string) Staff::id($request);
    }

    private function price(Request $request, string $field = 'price'): int
    {
        $v = Money::parse((string) $request->input($field, ''));
        if ($v === null) {
            throw DomainError::invalid('Enter the price in shillings, for example 850 or 850.50.');
        }

        return $v;
    }

    /** @return list<object> */
    private function mediaChoices(string $kind): array
    {
        return DB::table('media')->where('kind', $kind)->whereIn('publication_state', ['draft', 'reviewing', 'published', 'demo'])
            ->orderByDesc('created_at')->limit(200)->get(['id', 'label', 'alt_text', 'original_stem', 'publication_state'])->all();
    }

    // ----- Meals -----

    public function meals(Request $request): View
    {
        $archived = $request->boolean('archived');
        $list = $this->meals->adminList($archived);
        foreach ($list as $m) {
            $m->thumb = $this->media->image($m->media_id, 'thumb', 'meal', false);
        }

        return view('admin.meals', ['meals' => $list, 'archived' => $archived, 'categories' => $this->meals->categories(true),
            'stations' => DB::table('stations')->where('active', 1)->orderBy('name')->get()->all()]);
    }

    public function storeMeal(Request $request): RedirectResponse
    {
        $id = $this->meals->create([
            'name' => $request->input('name'), 'description' => $request->input('description'),
            'price_minor' => $this->price($request), 'category_id' => $request->input('category_id'),
            'station_id' => $request->input('station_id'), 'display_order' => (int) $request->input('display_order', 0),
        ], $this->actor($request));

        return redirect('/admin/meals/'.$id)->with('status', 'Draft created. Add ingredients, then send for kitchen review.');
    }

    public function saveCategory(Request $request): RedirectResponse
    {
        $id = $request->input('id');
        $this->meals->saveCategory(is_string($id) && $id !== '' ? $id : null, (string) $request->input('name', ''), (int) $request->input('display_order', 0), $request->input('active', '1') === '1', $this->actor($request));

        return back()->with('status', 'Category saved.');
    }

    public function meal(Request $request, string $mealId): View
    {
        abort_unless(Staff::can($request, 'catalogue.edit') || Staff::can($request, 'orders.review') || Staff::can($request, 'catalogue.publish'), 403);
        $meal = $this->meals->find($mealId);
        abort_if($meal === null, 404);
        $meal->status = $this->meals->status($meal);
        $published = $meal->published_version ? DB::table('meal_versions')->where('meal_id', $mealId)->where('version', $meal->published_version)->first() : null;

        return view('admin.meal', [
            'meal' => $meal, 'rules' => $this->meals->draftIngredients($mealId),
            'allIngredients' => $this->ingredients->all(), 'categories' => $this->meals->categories(true),
            'stations' => DB::table('stations')->where('active', 1)->orderBy('name')->get()->all(),
            'mediaChoices' => $this->mediaChoices('meal'), 'image' => $this->media->image($meal->media_id, 'card', 'meal', false),
            'published' => $published, 'facts' => $this->meals->draftFacts($mealId),
            'versions' => DB::table('meal_versions')->where('meal_id', $mealId)->orderByDesc('version')->limit(10)->get()->all(),
            'canPublish' => Staff::can($request, 'catalogue.publish'), 'canReview' => Staff::can($request, 'orders.review'),
            'canAvailability' => Staff::can($request, 'availability.manage'), 'canEdit' => Staff::can($request, 'catalogue.edit'),
        ]);
    }

    public function updateMeal(Request $request, string $mealId): RedirectResponse
    {
        $this->meals->updateDraft($mealId, (int) $request->input('version'), [
            'name' => $request->input('name'), 'description' => $request->input('description'),
            'price_minor' => $this->price($request), 'category_id' => $request->input('category_id'),
            'station_id' => $request->input('station_id'), 'media_id' => $request->input('media_id'),
            'display_order' => (int) $request->input('display_order', 0),
        ], $this->actor($request));

        return back()->with('status', 'Draft saved. Published menu is unchanged until you publish.');
    }

    public function mealIngredients(Request $request, string $mealId): RedirectResponse
    {
        $rules = [];
        foreach ((array) $request->input('rules', []) as $row) {
            if (! is_array($row) || ($row['ingredient_id'] ?? '') === '' || ($row['rule'] ?? '') === 'none') {
                continue;
            }
            $extra = 0;
            if (($row['rule'] ?? '') === 'extra') {
                $extra = Money::parse((string) ($row['extra_price'] ?? '0'));
                if ($extra === null) {
                    throw DomainError::invalid('Enter extra prices in shillings.');
                }
            }
            $rules[] = ['ingredient_id' => (string) $row['ingredient_id'], 'rule' => (string) $row['rule'], 'extra_price_minor' => $extra];
        }
        $add = $request->input('add_ingredient_id');
        if (is_string($add) && $add !== '' && ! in_array($add, array_column($rules, 'ingredient_id'), true)) {
            $rules[] = ['ingredient_id' => $add, 'rule' => 'removable', 'extra_price_minor' => 0];
        }
        $this->meals->setIngredients($mealId, (int) $request->input('version'), $rules, $this->actor($request));

        return back()->with('status', 'Ingredients saved.');
    }

    public function approveRecipe(Request $request, string $mealId): RedirectResponse
    {
        $this->meals->approveRecipe($mealId, (string) $request->input('digest'), $this->actor($request), $request->input('note'));

        return back()->with('status', 'Recipe approved by the kitchen.');
    }

    public function publishMeal(Request $request, string $mealId): RedirectResponse
    {
        $v = $this->meals->publish($mealId, $this->actor($request));

        return back()->with('status', 'Published version '.$v.'. Customers see it now.');
    }

    public function unpublishMeal(Request $request, string $mealId): RedirectResponse
    {
        $this->meals->unpublish($mealId, $this->actor($request));

        return back()->with('status', 'Removed from the menu. Existing orders are unaffected.');
    }

    public function archiveMeal(Request $request, string $mealId): RedirectResponse
    {
        $this->meals->setArchived($mealId, $request->input('archived') === '1', $this->actor($request));

        return back()->with('status', $request->input('archived') === '1' ? 'Meal archived.' : 'Meal restored as a draft.');
    }

    public function availability(Request $request): View
    {
        return view('staff.availability', ['meals' => array_values(array_filter($this->meals->adminList(), fn ($m) => $m->published_version !== null))]);
    }

    public function setAvailability(Request $request, string $mealId): RedirectResponse
    {
        $raw = trim((string) $request->input('portions', ''));
        if ($raw !== '' && ! ctype_digit($raw)) {
            throw DomainError::invalid('Portions must be a whole number, or blank for no count.');
        }
        $this->meals->setAvailability($mealId, $request->input('sellable') === '1', $raw === '' ? null : (int) $raw, $request->input('reason'), (int) $request->input('availability_version'), $this->actor($request));

        return back()->with('status', 'Availability updated.');
    }

    // ----- Ingredients -----

    public function ingredientsIndex(Request $request): View
    {
        $result = $this->ingredients->list((string) $request->query('q', ''), max(1, (int) $request->query('page', 1)), $request->boolean('archived'));
        foreach ($result['items'] as $i) {
            $i->thumb = $this->media->image($i->media_id, 'thumb', 'ingredient', false);
        }

        return view('admin.ingredients', ['result' => $result, 'q' => (string) $request->query('q', ''), 'archived' => $request->boolean('archived')]);
    }

    public function storeIngredient(Request $request): RedirectResponse
    {
        $id = $this->ingredients->create($request->only(['name', 'description', 'allergen_notes', 'preparation_notes']), $this->actor($request));

        return redirect('/admin/ingredients/'.$id)->with('status', 'Ingredient created.');
    }

    public function ingredient(string $ingredientId): View
    {
        $row = $this->ingredients->find($ingredientId);
        abort_if($row === null, 404);

        return view('admin.ingredient', [
            'ing' => $row, 'components' => $this->ingredients->components($ingredientId),
            'flat' => $this->ingredients->flatComponentNames($ingredientId),
            'all' => array_values(array_filter($this->ingredients->all(), fn ($i) => $i->id !== $ingredientId)),
            'usedBy' => DB::table('meal_ingredients')->join('meals', 'meals.id', '=', 'meal_ingredients.meal_id')->where('meal_ingredients.ingredient_id', $ingredientId)->get(['meals.id', 'meals.name'])->all(),
            'versions' => $this->ingredients->versions($ingredientId),
            'mediaChoices' => $this->mediaChoices('ingredient'), 'image' => $this->media->image($row->media_id, 'portrait', 'ingredient', false),
        ]);
    }

    public function updateIngredient(Request $request, string $ingredientId): RedirectResponse
    {
        $affected = $this->ingredients->update($ingredientId, (int) $request->input('version'), $request->only(['name', 'description', 'allergen_notes', 'preparation_notes', 'media_id']), $this->actor($request));
        $n = count($affected);

        return back()->with('status', 'Ingredient saved.'.($n > 0 ? ' '.$n.' published meal(s) now need kitchen re-approval and republishing.' : ''));
    }

    public function ingredientComponents(Request $request, string $ingredientId): RedirectResponse
    {
        $ids = array_values(array_filter((array) $request->input('component_ids', []), 'is_string'));
        $this->ingredients->setComponents($ingredientId, $ids, $this->actor($request));

        return back()->with('status', 'Components saved.');
    }

    public function archiveIngredient(Request $request, string $ingredientId): RedirectResponse
    {
        $this->ingredients->setActive($ingredientId, $request->input('active') === '1', $this->actor($request));

        return back()->with('status', $request->input('active') === '1' ? 'Ingredient restored.' : 'Ingredient archived.');
    }

    // ----- Media -----

    public function mediaIndex(Request $request): View
    {
        $kind = $request->query('kind') === 'ingredient' ? 'ingredient' : 'meal';
        $items = DB::table('media')->where('kind', $kind)->orderByDesc('created_at')->limit(120)->get()->all();
        foreach ($items as $m) {
            $m->thumb = $this->media->image($m->id, 'thumb', $kind, false);
        }

        return view('admin.media', ['items' => $items, 'kind' => $kind]);
    }

    public function uploadMedia(Request $request): RedirectResponse
    {
        $file = $request->files->get('file');
        if (! $file instanceof UploadedFile || $file->getError() !== UPLOAD_ERR_OK) {
            throw DomainError::invalid($file instanceof UploadedFile && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'That photo is too large.' : 'Choose a JPEG, PNG or WebP photo to upload.');
        }
        $max = app(\App\Support\MediaUploadLimits::class)->maxBytes();
        if ($file->getSize() > $max) {
            throw DomainError::invalid('That photo is too large (limit '.round($max / 1048576).' MB).');
        }
        $bytes = (string) file_get_contents($file->getPathname());
        $result = $this->media->upload($bytes, $file->getClientOriginalName(), $request->input('kind') === 'ingredient' ? 'ingredient' : 'meal', $this->actor($request), $request->boolean('demo'));

        return redirect('/admin/media/'.$result['id'])->with('status', $result['duplicate'] ? 'This exact photo was already uploaded; opened the existing one.' : 'Uploaded. Add alt text and rights, then publish.');
    }

    public function mediaItem(string $mediaId): View
    {
        $m = DB::table('media')->where('id', $mediaId)->first();
        abort_if($m === null, 404);

        return view('admin.media-item', [
            'm' => $m, 'card' => $this->media->image($mediaId, 'card', $m->kind === 'ingredient' ? 'ingredient' : 'meal', false),
            'variants' => DB::table('media_variants')->where('media_id', $mediaId)->orderBy('purpose')->get()->all(),
            'usedBy' => DB::table('meals')->where('media_id', $mediaId)->get(['id', 'name'])->all(),
            'edits' => DB::table('media_edits')->where('media_id', $mediaId)->orderByDesc('created_at')->limit(10)->get()->all(),
        ]);
    }

    public function updateMedia(Request $request, string $mediaId): RedirectResponse
    {
        $changes = $request->only(['label', 'alt_text', 'rights_owner', 'rights_summary', 'rights_restriction']);
        $granted = trim((string) $request->input('rights_granted_at', ''));
        $changes['rights_granted_at'] = $granted === '' ? null : $granted;
        $changes['focal_x'] = (int) round(((float) $request->input('focal_x', 50)) * 100);
        $changes['focal_y'] = (int) round(((float) $request->input('focal_y', 50)) * 100);
        $this->media->edit($mediaId, (int) $request->input('version'), $changes, $this->actor($request));

        return back()->with('status', 'Image details saved.');
    }

    public function mediaState(Request $request, string $mediaId): RedirectResponse
    {
        $state = (string) $request->input('state');
        if (! in_array($state, ['draft', 'reviewing', 'published', 'archived', 'demo'], true)) {
            throw DomainError::invalid('Choose a valid image state.');
        }
        $this->media->setState($mediaId, $state, $this->actor($request));

        return back()->with('status', 'Image is now '.$state.'.');
    }
}
