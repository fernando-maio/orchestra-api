<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preço mensal por plano
    |--------------------------------------------------------------------------
    |
    | Base de cálculo do MRR. Estava embutido num CASE de SQL, DUPLICADO em duas
    | queries do dashboard administrativo — mudar um preço exigia editar SQL em
    | dois lugares, com risco de os dois divergirem.
    |
    | A chave precisa bater com o enum de organizations.subscription_plan.
    | Plano ausente aqui conta como zero no MRR.
    |
    */

    'plan_prices' => [
        'starter' => 199,
        'professional' => 499,
        'enterprise' => 1499,
    ],

];
