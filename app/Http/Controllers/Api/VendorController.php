<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\VendorServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorRequest;
use App\Http\Requests\Vendor\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorController extends Controller
{
    public function __construct(
        protected VendorServiceInterface $vendorService
    ) {}

    /**
     * Lista fornecedores com filtros e paginação
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'category_id',
            'city',
            'state',
            'is_verified',
            'accepts_urgent',
            'is_active',
            'is_local_business',
            'is_sustainable',
            'is_minority_owned',
            'approval_status',
            'source',
            'subscription_tier',
        ]);

        $vendors = $this->vendorService->paginateWithFilters(
            $filters,
            $request->integer('per_page', 15)
        );

        return VendorResource::collection($vendors);
    }

    /**
     * Cria um novo fornecedor
     */
    public function store(StoreVendorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        // Define organization_id do usuário autenticado
        $data['organization_id'] = $request->user()->organization_id;

        $vendor = $this->vendorService->createWithCategories($data, $categoryIds);
        $vendor->load('categories');

        return response()->json([
            'message' => 'Fornecedor criado com sucesso.',
            'data' => new VendorResource($vendor),
        ], 201);
    }

    /**
     * Exibe um fornecedor específico
     */
    public function show(string $id): VendorResource
    {
        $vendor = $this->vendorService->findOrFail($id);
        $vendor->load(['categories', 'organization']);

        return new VendorResource($vendor);
    }

    /**
     * Atualiza um fornecedor
     */
    public function update(UpdateVendorRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        if ($categoryIds !== null) {
            $vendor = $this->vendorService->updateWithCategories($id, $data, $categoryIds);
        } else {
            $vendor = $this->vendorService->update($id, $data);
        }

        $vendor->load('categories');

        return response()->json([
            'message' => 'Fornecedor atualizado com sucesso.',
            'data' => new VendorResource($vendor),
        ]);
    }

    /**
     * Remove um fornecedor
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('vendors.delete');

        $vendor = $this->vendorService->findOrFail($id);

        // Verifica se tem propostas associadas
        if ($vendor->proposals()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um fornecedor com propostas associadas.',
            ], 422);
        }

        $this->vendorService->delete($id);

        return response()->json([
            'message' => 'Fornecedor excluído com sucesso.',
        ]);
    }

    /**
     * Alterna o status ativo/inativo
     */
    public function toggleActive(string $id): JsonResponse
    {
        $this->authorize('vendors.update');

        $vendor = $this->vendorService->toggleActive($id);

        return response()->json([
            'message' => $vendor->is_active
                ? 'Fornecedor ativado com sucesso.'
                : 'Fornecedor desativado com sucesso.',
            'data' => new VendorResource($vendor),
        ]);
    }

    /**
     * Marca fornecedor como verificado
     */
    public function verify(string $id): JsonResponse
    {
        $this->authorize('vendors.verify');

        $vendor = $this->vendorService->verify($id);

        return response()->json([
            'message' => 'Fornecedor verificado com sucesso.',
            'data' => new VendorResource($vendor),
        ]);
    }

    /**
     * Busca fornecedores próximos por geolocalização
     */
    public function nearby(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
        ]);

        $filters = $request->only(['category_id', 'is_verified', 'accepts_urgent']);

        $vendors = $this->vendorService->findNearby(
            $request->float('latitude'),
            $request->float('longitude'),
            $request->integer('radius_km', 50),
            $filters
        );

        return VendorResource::collection($vendors);
    }

    /**
     * Lista fornecedores por categoria
     */
    public function byCategory(string $categoryId): AnonymousResourceCollection
    {
        $vendors = $this->vendorService->findByCategory($categoryId);

        return VendorResource::collection($vendors);
    }

    /**
     * Retorna status de compliance do fornecedor
     */
    public function compliance(string $id): JsonResponse
    {
        $this->authorize('vendors.view');

        $status = $this->vendorService->getComplianceStatus($id);

        return response()->json(['data' => $status]);
    }

    /**
     * Aprova um fornecedor pendente
     */
    public function approve(string $id): JsonResponse
    {
        $this->authorize('vendors.approve');

        $vendor = $this->vendorService->findOrFail($id);

        if ($vendor->approval_status !== 'pending') {
            return response()->json([
                'message' => 'Somente fornecedores pendentes podem ser aprovados.',
            ], 422);
        }

        $vendor->approve(auth()->user());
        $vendor->load('categories');

        return response()->json([
            'message' => 'Fornecedor aprovado com sucesso.',
            'data' => new VendorResource($vendor),
        ]);
    }

    /**
     * Rejeita um fornecedor pendente
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $this->authorize('vendors.approve');

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendor = $this->vendorService->findOrFail($id);

        if ($vendor->approval_status !== 'pending') {
            return response()->json([
                'message' => 'Somente fornecedores pendentes podem ser rejeitados.',
            ], 422);
        }

        $vendor->reject(auth()->user(), $request->input('reason'));
        $vendor->load('categories');

        return response()->json([
            'message' => 'Fornecedor rejeitado.',
            'data' => new VendorResource($vendor),
        ]);
    }
}
