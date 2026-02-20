<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\CategoryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryServiceInterface $categoryService
    ) {}

    /**
     * Lista todas as categorias
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $request->boolean('active_only')
            ? $this->categoryService->getAllActive()
            : $this->categoryService->getAllOrdered();

        return CategoryResource::collection($categories);
    }

    /**
     * Cria uma nova categoria
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return response()->json([
            'message' => 'Categoria criada com sucesso.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Exibe uma categoria específica
     */
    public function show(string $id): CategoryResource
    {
        $category = $this->categoryService->findOrFail($id);
        $category->loadCount('vendors');

        return new CategoryResource($category);
    }

    /**
     * Atualiza uma categoria
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = $this->categoryService->update($id, $request->validated());

        return response()->json([
            'message' => 'Categoria atualizada com sucesso.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove uma categoria
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('categories.delete');

        $category = $this->categoryService->findOrFail($id);

        // Verifica se tem fornecedores associados
        if ($category->vendors()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir uma categoria com fornecedores associados.',
            ], 422);
        }

        $this->categoryService->delete($id);

        return response()->json([
            'message' => 'Categoria excluída com sucesso.',
        ]);
    }

    /**
     * Alterna o status ativo/inativo
     */
    public function toggleActive(string $id): JsonResponse
    {
        $this->authorize('categories.update');

        $category = $this->categoryService->toggleActive($id);

        return response()->json([
            'message' => $category->is_active
                ? 'Categoria ativada com sucesso.'
                : 'Categoria desativada com sucesso.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Reordena as categorias
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('categories.update');

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'uuid', 'exists:categories,id'],
        ]);

        $this->categoryService->reorder($request->input('ids'));

        return response()->json([
            'message' => 'Categorias reordenadas com sucesso.',
        ]);
    }
}
