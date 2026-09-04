<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorsSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('email', 'demo@orchestra.local')->first();

        if (! $organization) {
            $this->command->warn('Demo organization not found. Skipping vendors seeding.');

            return;
        }

        $vendors = [
            [
                'trade_name' => 'Delícias Buffet',
                'legal_name' => 'Delícias Buffet e Eventos Ltda',
                'cnpj' => '12.345.678/0001-01',
                'email' => 'contato@deliciasbuffet.com.br',
                'phone' => '(11) 3456-7890',
                'whatsapp' => '(11) 99876-5432',
                'website' => 'https://deliciasbuffet.com.br',
                'address' => 'Rua das Flores, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-567',
                'latitude' => -23.550520,
                'longitude' => -46.633309,
                'service_radius_km' => 50,
                'description' => 'Buffet especializado em eventos corporativos com mais de 15 anos de experiência.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => true,
                'is_sustainable' => true,
                'is_active' => true,
                'categories' => ['buffet'],
            ],
            [
                'trade_name' => 'SomMax Produções',
                'legal_name' => 'SomMax Produções de Eventos Ltda',
                'cnpj' => '23.456.789/0001-02',
                'email' => 'comercial@sommax.com.br',
                'phone' => '(11) 2345-6789',
                'whatsapp' => '(11) 98765-4321',
                'website' => 'https://sommax.com.br',
                'address' => 'Av. Paulista, 1000',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01310-100',
                'latitude' => -23.561414,
                'longitude' => -46.656074,
                'service_radius_km' => 100,
                'description' => 'Referência em som e iluminação para grandes eventos.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => false,
                'is_sustainable' => false,
                'is_active' => true,
                'categories' => ['som-iluminacao', 'audiovisual'],
            ],
            [
                'trade_name' => 'Estruturas ABC',
                'legal_name' => 'ABC Estruturas para Eventos ME',
                'cnpj' => '34.567.890/0001-03',
                'email' => 'contato@estruturasabc.com.br',
                'phone' => '(11) 4567-8901',
                'whatsapp' => '(11) 97654-3210',
                'address' => 'Rua Industrial, 500',
                'city' => 'Santo André',
                'state' => 'SP',
                'zip_code' => '09080-100',
                'latitude' => -23.668270,
                'longitude' => -46.540890,
                'service_radius_km' => 80,
                'description' => 'Montagem de palcos, tendas e estruturas para eventos de todos os portes.',
                'accepts_urgent' => false,
                'is_verified' => true,
                'is_local_business' => true,
                'is_sustainable' => true,
                'is_minority_owned' => true,
                'is_active' => true,
                'categories' => ['estrutura'],
            ],
            [
                'trade_name' => 'Visual Tech',
                'legal_name' => 'Visual Tech Audiovisual Eireli',
                'cnpj' => '45.678.901/0001-04',
                'email' => 'comercial@visualtech.com.br',
                'phone' => '(11) 5678-9012',
                'whatsapp' => '(11) 96543-2109',
                'website' => 'https://visualtech.com.br',
                'address' => 'Rua da Tecnologia, 200',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '04543-000',
                'latitude' => -23.597080,
                'longitude' => -46.688940,
                'service_radius_km' => 150,
                'description' => 'Soluções completas em audiovisual, transmissão ao vivo e LED.',
                'accepts_urgent' => true,
                'is_verified' => false,
                'is_local_business' => false,
                'is_active' => true,
                'categories' => ['audiovisual', 'tecnologia'],
            ],
            [
                'trade_name' => 'Decor Eventos',
                'legal_name' => 'Decor Eventos e Cenografia Ltda',
                'cnpj' => '56.789.012/0001-05',
                'email' => 'atendimento@decoreventos.com.br',
                'phone' => '(11) 6789-0123',
                'whatsapp' => '(11) 95432-1098',
                'address' => 'Av. das Artes, 300',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01310-200',
                'latitude' => -23.563270,
                'longitude' => -46.651720,
                'service_radius_km' => 60,
                'description' => 'Decoração e cenografia para eventos corporativos e sociais.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => true,
                'is_sustainable' => true,
                'is_active' => true,
                'categories' => ['decoracao', 'mobiliario'],
            ],
            [
                'trade_name' => 'Energia Total',
                'legal_name' => 'Energia Total Geradores Ltda',
                'cnpj' => '67.890.123/0001-06',
                'email' => 'contato@energiatotal.com.br',
                'phone' => '(11) 7890-1234',
                'whatsapp' => '(11) 94321-0987',
                'address' => 'Av. Industrial, 1500',
                'city' => 'Guarulhos',
                'state' => 'SP',
                'zip_code' => '07190-000',
                'latitude' => -23.454640,
                'longitude' => -46.518680,
                'service_radius_km' => 120,
                'description' => 'Locação de geradores e infraestrutura elétrica para eventos.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => false,
                'is_active' => true,
                'categories' => ['geradores'],
            ],
            [
                'trade_name' => 'Segurança Premium',
                'legal_name' => 'Segurança Premium Eventos Ltda',
                'cnpj' => '78.901.234/0001-07',
                'email' => 'comercial@segurancapremium.com.br',
                'phone' => '(11) 8901-2345',
                'whatsapp' => '(11) 93210-9876',
                'address' => 'Rua da Segurança, 50',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '03112-000',
                'latitude' => -23.530780,
                'longitude' => -46.606160,
                'service_radius_km' => 100,
                'description' => 'Segurança patrimonial e pessoal para eventos corporativos.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => true,
                'is_active' => true,
                'categories' => ['seguranca'],
            ],
            [
                'trade_name' => 'Foto & Vídeo Pro',
                'legal_name' => 'FV Pro Produções Audiovisuais ME',
                'cnpj' => '89.012.345/0001-08',
                'email' => 'contato@fvpro.com.br',
                'phone' => '(11) 9012-3456',
                'whatsapp' => '(11) 92109-8765',
                'website' => 'https://fvpro.com.br',
                'address' => 'Rua dos Fotógrafos, 88',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01414-000',
                'latitude' => -23.556180,
                'longitude' => -46.669570,
                'service_radius_km' => 80,
                'description' => 'Cobertura fotográfica e videográfica profissional para eventos.',
                'accepts_urgent' => false,
                'is_verified' => true,
                'is_local_business' => true,
                'is_minority_owned' => true,
                'is_active' => true,
                'categories' => ['foto-video'],
            ],
            [
                'trade_name' => 'Clean Service',
                'legal_name' => 'Clean Service Terceirização Ltda',
                'cnpj' => '90.123.456/0001-09',
                'email' => 'atendimento@cleanservice.com.br',
                'phone' => '(11) 3012-3456',
                'whatsapp' => '(11) 91098-7654',
                'address' => 'Av. da Limpeza, 200',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '02112-000',
                'latitude' => -23.510620,
                'longitude' => -46.633970,
                'service_radius_km' => 70,
                'description' => 'Serviços de limpeza especializada para eventos.',
                'accepts_urgent' => true,
                'is_verified' => false,
                'is_local_business' => true,
                'is_active' => true,
                'categories' => ['limpeza'],
            ],
            [
                'trade_name' => 'TransLog Eventos',
                'legal_name' => 'TransLog Transportes e Logística Ltda',
                'cnpj' => '01.234.567/0001-10',
                'email' => 'comercial@translog.com.br',
                'phone' => '(11) 4012-3456',
                'whatsapp' => '(11) 90987-6543',
                'address' => 'Rodovia Anhanguera, km 25',
                'city' => 'Osasco',
                'state' => 'SP',
                'zip_code' => '06268-000',
                'latitude' => -23.530710,
                'longitude' => -46.763100,
                'service_radius_km' => 200,
                'description' => 'Transporte de pessoas e equipamentos para eventos em todo Brasil.',
                'accepts_urgent' => true,
                'is_verified' => true,
                'is_local_business' => false,
                'is_sustainable' => true,
                'is_active' => true,
                'categories' => ['transporte'],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $categoryIds = [];
            if (isset($vendorData['categories'])) {
                $categorySlugs = $vendorData['categories'];
                unset($vendorData['categories']);
                $categoryIds = Category::whereIn('slug', $categorySlugs)->pluck('id')->toArray();
            }

            $vendorData['organization_id'] = $organization->id;
            $vendorData['average_rating'] = rand(35, 50) / 10;
            $vendorData['total_ratings'] = rand(5, 50);
            $vendorData['approval_status'] = 'approved';
            $vendorData['source'] = 'admin';
            $vendorData['approved_at'] = now();

            $vendor = Vendor::firstOrCreate(
                ['cnpj' => $vendorData['cnpj']],
                $vendorData
            );

            if (! empty($categoryIds)) {
                $vendor->categories()->syncWithoutDetaching($categoryIds);
            }
        }

        $this->command->info('Vendors seeded successfully!');
    }
}
