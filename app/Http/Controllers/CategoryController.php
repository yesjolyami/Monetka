<?php

namespace App\Http\Controllers;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Enums\CategoryKind;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('view', $workspace);

        $categories = $workspace->categories()->orderBy('name')->get();

        return Inertia::render('categories/Index', [
            'expense' => $this->payloads(
                $categories
                    ->filter(fn (Category $category): bool => $category->kind === CategoryKind::Expense)
                    ->values(),
            ),
            'income' => $this->payloads(
                $categories
                    ->filter(fn (Category $category): bool => $category->kind === CategoryKind::Income)
                    ->values(),
            ),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $data = $request->validated();

        $createCategory->execute($this->currentWorkspace($request), [
            'kind' => $data['kind'],
            'name' => $data['name'],
            'emoji' => $data['emoji'],
            'color' => $data['color'],
        ]);

        return back();
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        Gate::authorize('update', $category);
        $this->assertInWorkspace($request, $category->workspace_id);

        $data = $request->validated();

        $updateCategory->execute($category, [
            'kind' => $data['kind'],
            'name' => $data['name'],
            'emoji' => $data['emoji'],
            'color' => $data['color'],
        ]);

        return back();
    }

    public function destroy(Request $request, Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        Gate::authorize('delete', $category);
        $this->assertInWorkspace($request, $category->workspace_id);

        $deleteCategory->execute($category);

        return back();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, array{id: int, kind: string, name: string, emoji: string, color: string}>
     */
    private function payloads(Collection $categories): array
    {
        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'kind' => $category->kind->value,
                'name' => $category->name,
                'emoji' => $category->emoji,
                'color' => $category->color,
            ])
            ->values()
            ->all();
    }

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        return $workspace;
    }

    private function assertInWorkspace(Request $request, int $workspaceId): Workspace
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($workspaceId === $workspace->id, 404);

        return $workspace;
    }
}
