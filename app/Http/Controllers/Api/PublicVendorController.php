<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\VendorResource;
use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicVendorController extends Controller
{
    /**
     * Lista categorias ativas para o formulário de cadastro
     */
    public function categories(): AnonymousResourceCollection
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Cadastro público de fornecedor (self-register)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Dados da empresa
            'trade_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vendors', 'cnpj')->whereNull('deleted_at'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],

            // Endereço
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],

            // Raio de atendimento
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],

            // Descrição
            'description' => ['nullable', 'string', 'max:2000'],

            // Características
            'accepts_urgent' => ['boolean'],
            'is_local_business' => ['boolean'],
            'is_sustainable' => ['boolean'],
            'is_minority_owned' => ['boolean'],

            // Contato principal
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],

            // Categorias
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ], [
            'trade_name.required' => 'O nome fantasia é obrigatório.',
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'city.required' => 'A cidade é obrigatória.',
            'state.required' => 'O estado é obrigatório.',
            'contact_name.required' => 'O nome do contato é obrigatório.',
            'contact_email.required' => 'O e-mail do contato é obrigatório.',
            'contact_phone.required' => 'O telefone do contato é obrigatório.',
            'category_ids.required' => 'Selecione pelo menos uma categoria.',
            'category_ids.min' => 'Selecione pelo menos uma categoria.',
        ]);

        $categoryIds = $validated['category_ids'];
        unset($validated['category_ids']);

        // Define campos do marketplace
        $validated['approval_status'] = 'pending';
        $validated['source'] = 'self_register';
        $validated['is_active'] = false; // Inativo até aprovação
        $validated['service_radius_km'] = $validated['service_radius_km'] ?? 50;

        $vendor = DB::transaction(function () use ($validated, $categoryIds) {
            $vendor = Vendor::create($validated);
            $vendor->categories()->attach($categoryIds);

            return $vendor;
        });

        $vendor->load('categories');

        // TODO: Enviar e-mail de confirmação para o fornecedor
        // TODO: Notificar admins sobre novo cadastro pendente

        return response()->json([
            'message' => 'Cadastro realizado com sucesso! Seu cadastro será analisado e você receberá um e-mail com o resultado.',
            'data' => new VendorResource($vendor),
        ], 201);
    }

    /**
     * Verifica se CNPJ já está cadastrado
     */
    public function checkCnpj(Request $request): JsonResponse
    {
        $request->validate([
            'cnpj' => ['required', 'string'],
        ]);

        $exists = Vendor::where('cnpj', $request->input('cnpj'))
            ->whereNull('deleted_at')
            ->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este CNPJ já está cadastrado.' : null,
        ]);
    }

    /**
     * Verifica se e-mail já está cadastrado
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $exists = Vendor::where('email', $request->input('email'))
            ->whereNull('deleted_at')
            ->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este e-mail já está cadastrado.' : null,
        ]);
    }
}
