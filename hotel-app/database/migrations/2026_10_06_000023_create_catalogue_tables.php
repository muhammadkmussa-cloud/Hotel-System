<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P09/P10 — reusable ingredient library, categories, editable meal drafts and
 * immutable published meal versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            // Staff-only facts. Never rendered as a customer safety claim.
            $table->string('allergen_notes', 500)->nullable();
            $table->string('preparation_notes', 500)->nullable();
            $table->uuid('media_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->string('active_name', 120)->nullable()->storedAs("CASE WHEN active = 1 THEN name ELSE NULL END");
            $table->unique('active_name', 'ingredients_active_name_unique');
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
        });

        Schema::create('ingredient_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ingredient_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->uuid('actor_staff_user_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['ingredient_id', 'version']);
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->restrictOnDelete();
        });

        Schema::create('ingredient_components', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_ingredient_id');
            $table->uuid('child_ingredient_id');
            $table->timestamps();
            $table->unique(['parent_ingredient_id', 'child_ingredient_id'], 'ingredient_components_pair_unique');
            $table->foreign('parent_ingredient_id')->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('child_ingredient_id')->references('id')->on('ingredients')->restrictOnDelete();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('meals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable();
            $table->string('name', 120);
            $table->string('description', 1000)->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->uuid('media_id')->nullable();
            $table->uuid('station_id')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            // Draft lifecycle. The digest covers every customer/kitchen-relevant
            // draft fact; approval is bound to one exact digest.
            $table->char('draft_digest', 64)->nullable();
            $table->char('recipe_approved_digest', 64)->nullable();
            $table->uuid('recipe_approved_by')->nullable();
            $table->timestamp('recipe_approved_at')->nullable();
            $table->string('recipe_review_note', 500)->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('archived')->default(false);
            // Availability (P14). Portions null = not counted.
            $table->boolean('sellable')->default(true);
            $table->integer('portions_remaining')->nullable();
            $table->string('availability_reason', 200)->nullable();
            $table->unsignedInteger('availability_version')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['archived', 'published_version']);
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            $table->foreign('station_id')->references('id')->on('stations')->nullOnDelete();
        });

        Schema::create('meal_ingredients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('meal_id');
            $table->uuid('ingredient_id');
            $table->enum('rule', ['fixed', 'removable', 'extra']);
            $table->unsignedBigInteger('extra_price_minor')->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['meal_id', 'ingredient_id']);
            $table->foreign('meal_id')->references('id')->on('meals')->cascadeOnDelete();
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->restrictOnDelete();
        });

        Schema::create('meal_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('meal_id');
            $table->unsignedInteger('version');
            $table->char('digest', 64);
            $table->json('snapshot');
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at');
            $table->unique(['meal_id', 'version']);
            $table->foreign('meal_id')->references('id')->on('meals')->restrictOnDelete();
        });

        Schema::create('availability_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('meal_id');
            $table->boolean('sellable');
            $table->integer('portions_remaining')->nullable();
            $table->string('reason', 200)->nullable();
            $table->uuid('actor_staff_user_id')->nullable();
            $table->timestamp('created_at');
            $table->index(['meal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['availability_changes', 'meal_versions', 'meal_ingredients', 'meals', 'categories', 'ingredient_components', 'ingredient_versions', 'ingredients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
