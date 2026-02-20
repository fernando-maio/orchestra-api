<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Buffet e Alimentação',
                'slug' => 'buffet',
                'description' => 'Serviços de alimentação, coffee break, almoços e jantares',
                'ai_description' => 'Inclui catering, coffee break, finger food, refeições completas, bebidas, garçons',
                'icon' => 'utensils',
                'color' => '#FF6B6B',
                'sort_order' => 1,
            ],
            [
                'name' => 'Som e Iluminação',
                'slug' => 'som-iluminacao',
                'description' => 'Equipamentos de áudio e iluminação profissional',
                'ai_description' => 'Sistema de som, PA, microfones, iluminação cênica, refletores, mesas de luz',
                'icon' => 'lightbulb',
                'color' => '#4ECDC4',
                'sort_order' => 2,
            ],
            [
                'name' => 'Estrutura e Montagem',
                'slug' => 'estrutura',
                'description' => 'Palcos, tendas, stands e estruturas físicas',
                'ai_description' => 'Palcos, tendas, coberturas, stands, gradis, pisos, arquibancadas',
                'icon' => 'building',
                'color' => '#45B7D1',
                'sort_order' => 3,
            ],
            [
                'name' => 'Audiovisual',
                'slug' => 'audiovisual',
                'description' => 'Projetores, telões, LED e equipamentos AV',
                'ai_description' => 'Projetores, telões, painéis LED, switchers, câmeras, transmissão ao vivo',
                'icon' => 'tv',
                'color' => '#96CEB4',
                'sort_order' => 4,
            ],
            [
                'name' => 'Decoração',
                'slug' => 'decoracao',
                'description' => 'Decoração, cenografia e ambientação',
                'ai_description' => 'Flores, arranjos, cenografia, mobiliário decorativo, tapetes, cortinas',
                'icon' => 'palette',
                'color' => '#DDA0DD',
                'sort_order' => 5,
            ],
            [
                'name' => 'Mobiliário',
                'slug' => 'mobiliario',
                'description' => 'Mesas, cadeiras e mobiliário para eventos',
                'ai_description' => 'Mesas, cadeiras, sofás, lounges, balcões, poltronas, bistrôs',
                'icon' => 'couch',
                'color' => '#F7DC6F',
                'sort_order' => 6,
            ],
            [
                'name' => 'Geradores e Energia',
                'slug' => 'geradores',
                'description' => 'Geradores de energia e infraestrutura elétrica',
                'ai_description' => 'Geradores, quadros de energia, cabos, distribuição elétrica, nobreaks',
                'icon' => 'bolt',
                'color' => '#F39C12',
                'sort_order' => 7,
            ],
            [
                'name' => 'Segurança',
                'slug' => 'seguranca',
                'description' => 'Serviços de segurança patrimonial e pessoal',
                'ai_description' => 'Segurança patrimonial, controle de acesso, brigadistas, bombeiros civis',
                'icon' => 'shield',
                'color' => '#E74C3C',
                'sort_order' => 8,
            ],
            [
                'name' => 'Limpeza',
                'slug' => 'limpeza',
                'description' => 'Serviços de limpeza e conservação',
                'ai_description' => 'Limpeza durante e pós evento, banheiros químicos, coleta de lixo',
                'icon' => 'broom',
                'color' => '#3498DB',
                'sort_order' => 9,
            ],
            [
                'name' => 'Transporte e Logística',
                'slug' => 'transporte',
                'description' => 'Transporte de pessoas e cargas',
                'ai_description' => 'Vans, ônibus, transfers, transporte de carga, empilhadeiras, guindastes',
                'icon' => 'truck',
                'color' => '#9B59B6',
                'sort_order' => 10,
            ],
            [
                'name' => 'Tecnologia e TI',
                'slug' => 'tecnologia',
                'description' => 'Internet, rede, credenciamento e sistemas',
                'ai_description' => 'Internet, wi-fi, credenciamento, totens, impressoras, apps de evento',
                'icon' => 'wifi',
                'color' => '#1ABC9C',
                'sort_order' => 11,
            ],
            [
                'name' => 'Fotografia e Vídeo',
                'slug' => 'foto-video',
                'description' => 'Cobertura fotográfica e videográfica',
                'ai_description' => 'Fotógrafos, cinegrafistas, drone, cabine de fotos, edição',
                'icon' => 'camera',
                'color' => '#E91E63',
                'sort_order' => 12,
            ],
            [
                'name' => 'Recepção e Cerimonial',
                'slug' => 'recepcao',
                'description' => 'Recepcionistas, cerimonialistas e MCs',
                'ai_description' => 'Recepcionistas, hosts, cerimonialistas, mestres de cerimônia, tradutores',
                'icon' => 'users',
                'color' => '#FF9800',
                'sort_order' => 13,
            ],
            [
                'name' => 'Entretenimento',
                'slug' => 'entretenimento',
                'description' => 'Atrações, shows e entretenimento',
                'ai_description' => 'Bandas, DJs, artistas, mágicos, caricaturistas, atividades interativas',
                'icon' => 'music',
                'color' => '#673AB7',
                'sort_order' => 14,
            ],
            [
                'name' => 'Brindes e Materiais',
                'slug' => 'brindes',
                'description' => 'Brindes promocionais e materiais gráficos',
                'ai_description' => 'Brindes, kits, sacolas, crachás, banners, totens, materiais impressos',
                'icon' => 'gift',
                'color' => '#00BCD4',
                'sort_order' => 15,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
