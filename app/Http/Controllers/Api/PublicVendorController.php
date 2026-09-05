<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\CategoryServiceInterface;
use App\Contracts\Services\VendorServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicVendor\CheckCnpjRequest;
use App\Http\Requests\PublicVendor\CheckEmailRequest;
use App\Http\Requests\PublicVendor\RegisterVendorRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicVendorController extends Controller
{
    public function __construct(
        private readonly VendorServiceInterface $vendorService,
        private readonly CategoryServiceInterface $categoryService,
    ) {}

    /**
     * Lista categorias ativas para o formulário de cadastro.
     */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categoryService->getAllActive()
        );
    }

    /**
     * Cadastro público de fornecedor (self-register).
     */
    public function register(RegisterVendorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        $vendor = $this->vendorService->registerSelfService($data, $categoryIds);

        // TODO: Enviar e-mail de confirmação para o fornecedor
        // TODO: Notificar admins sobre novo cadastro pendente

        return response()->json([
            'message' => 'Cadastro realizado com sucesso! Seu cadastro será analisado e você receberá um e-mail com o resultado.',
            'data' => new VendorResource($vendor),
        ], 201);
    }

    /**
     * Verifica se o CNPJ já está cadastrado.
     */
    public function checkCnpj(CheckCnpjRequest $request): JsonResponse
    {
        $exists = $this->vendorService->cnpjExists($request->validated('cnpj'));

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este CNPJ já está cadastrado.' : null,
        ]);
    }

    /**
     * Verifica se o e-mail já está cadastrado.
     */
    public function checkEmail(CheckEmailRequest $request): JsonResponse
    {
        $exists = $this->vendorService->emailExists($request->validated('email'));

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este e-mail já está cadastrado.' : null,
        ]);
    }
}
