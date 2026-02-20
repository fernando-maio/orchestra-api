<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;

class EventsSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('email', 'demo@orchestra.local')->first();
        $user = User::where('email', 'demo@orchestra.local')->first();

        if (!$organization || !$user) {
            $this->command->warn('Demo organization or user not found. Skipping events seeding.');
            return;
        }

        $categories = Category::pluck('id')->toArray();

        $events = [
            [
                'name' => 'Conferência Tech 2026',
                'description' => 'Conferência anual de tecnologia com palestras e workshops.',
                'briefing' => 'Evento para 500 pessoas com palestras, workshops e área de networking.',
                'start_date' => now()->addDays(30)->toDateString(),
                'end_date' => now()->addDays(32)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '18:00',
                'venue_name' => 'Centro de Convenções São Paulo',
                'address' => 'Av. Tiradentes, 1500',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01102-000',
                'latitude' => -23.532880,
                'longitude' => -46.625290,
                'estimated_budget' => 250000.00,
                'currency' => 'BRL',
                'expected_attendees' => 500,
                'status' => 'active',
                'required_categories' => array_slice($categories, 0, 5),
            ],
            [
                'name' => 'Workshop de Liderança',
                'description' => 'Workshop intensivo sobre liderança e gestão de equipes.',
                'briefing' => 'Workshop de 1 dia para 50 executivos com coffee break e almoço.',
                'start_date' => now()->addDays(15)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'venue_name' => 'Hotel Grand Mercure',
                'address' => 'Rua Augusta, 800',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01305-100',
                'latitude' => -23.553780,
                'longitude' => -46.654620,
                'estimated_budget' => 35000.00,
                'currency' => 'BRL',
                'expected_attendees' => 50,
                'status' => 'active',
                'required_categories' => array_slice($categories, 0, 3),
            ],
            [
                'name' => 'Festa de Final de Ano',
                'description' => 'Celebração de fim de ano com jantar e apresentações.',
                'briefing' => 'Festa para 200 colaboradores com jantar, música ao vivo e premiações.',
                'start_date' => now()->addDays(60)->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
                'start_time' => '19:00',
                'end_time' => '00:00',
                'venue_name' => 'Espaço Torres',
                'address' => 'Av. das Nações Unidas, 12500',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '04578-000',
                'latitude' => -23.602410,
                'longitude' => -46.692640,
                'estimated_budget' => 80000.00,
                'currency' => 'BRL',
                'expected_attendees' => 200,
                'status' => 'draft',
                'required_categories' => array_slice($categories, 0, 6),
            ],
            [
                'name' => 'Lançamento de Produto',
                'description' => 'Evento de lançamento do novo produto XYZ.',
                'briefing' => 'Apresentação para imprensa e clientes VIP com coquetel.',
                'start_date' => now()->addDays(45)->toDateString(),
                'end_date' => now()->addDays(45)->toDateString(),
                'start_time' => '18:00',
                'end_time' => '22:00',
                'venue_name' => 'Museu da Imagem e do Som',
                'address' => 'Av. Europa, 158',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01449-000',
                'latitude' => -23.584820,
                'longitude' => -46.674350,
                'estimated_budget' => 120000.00,
                'currency' => 'BRL',
                'expected_attendees' => 150,
                'status' => 'active',
                'required_categories' => array_slice($categories, 0, 8),
            ],
            [
                'name' => 'Treinamento Regional Sul',
                'description' => 'Treinamento para equipe de vendas da região Sul.',
                'briefing' => 'Treinamento de 2 dias para 30 vendedores com material e refeições.',
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(21)->toDateString(),
                'start_time' => '08:30',
                'end_time' => '17:30',
                'venue_name' => 'Hotel Bourbon',
                'address' => 'Rua São Paulo, 800',
                'city' => 'Curitiba',
                'state' => 'PR',
                'zip_code' => '80010-100',
                'latitude' => -25.429720,
                'longitude' => -49.271080,
                'estimated_budget' => 45000.00,
                'currency' => 'BRL',
                'expected_attendees' => 30,
                'status' => 'active',
                'required_categories' => array_slice($categories, 0, 4),
            ],
        ];

        foreach ($events as $eventData) {
            $eventData['organization_id'] = $organization->id;
            $eventData['created_by'] = $user->id;

            Event::firstOrCreate(
                ['name' => $eventData['name'], 'organization_id' => $organization->id],
                $eventData
            );
        }

        $this->command->info('Events seeded successfully!');
    }
}
