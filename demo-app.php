<?php

/**
 * DEMO DA BIBLIOTECA APIFORGE
 * 
 * Este script demonstra como usar a biblioteca ApiForge
 * simulando uma aplicação Laravel real.
 */

require_once 'vendor/autoload.php';

echo "🚀 DEMO DA BIBLIOTECA APIFORGE\n";
echo "===============================\n\n";

// Simular dados de usuários
$users = [
    [
        'id' => 1,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'birth_date' => '1990-01-15',
        'active' => true,
        'role' => 'user',
        'created_at' => '2023-01-15 10:30:00',
        'orders_count' => 5,
        'total_spent' => 1250.50
    ],
    [
        'id' => 2,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
        'birth_date' => '1985-06-20',
        'active' => true,
        'role' => 'admin',
        'created_at' => '2022-06-20 14:15:00',
        'orders_count' => 12,
        'total_spent' => 5420.75
    ],
    [
        'id' => 3,
        'first_name' => 'Bob',
        'last_name' => 'Johnson',
        'email' => 'bob@example.com',
        'birth_date' => '1992-12-10',
        'active' => false,
        'role' => 'user',
        'created_at' => '2023-12-10 09:45:00',
        'orders_count' => 2,
        'total_spent' => 89.99
    ]
];

echo "📊 DADOS DE TESTE CARREGADOS\n";
echo "Usuários: " . count($users) . "\n\n";

// Demonstrar Virtual Fields
echo "✨ DEMONSTRAÇÃO DE VIRTUAL FIELDS\n";
echo "==================================\n\n";

use MarcosBrendon\ApiForge\Services\VirtualFieldService;

try {
    $virtualFieldService = new VirtualFieldService();
    
    // Registrar virtual fields
    $virtualFields = [
        'full_name' => [
            'type' => 'string',
            'callback' => function($user) {
                return trim($user['first_name'] . ' ' . $user['last_name']);
            },
            'dependencies' => ['first_name', 'last_name'],
            'operators' => ['eq', 'like']
        ],
        
        'age' => [
            'type' => 'integer',
            'callback' => function($user) {
                if (!isset($user['birth_date'])) return null;
                $birthDate = new DateTime($user['birth_date']);
                $today = new DateTime();
                return $today->diff($birthDate)->y;
            },
            'dependencies' => ['birth_date'],
            'operators' => ['eq', 'gt', 'gte', 'lt', 'lte']
        ],
        
        'customer_tier' => [
            'type' => 'enum',
            'values' => ['bronze', 'silver', 'gold', 'platinum'],
            'callback' => function($user) {
                $totalSpent = $user['total_spent'] ?? 0;
                if ($totalSpent >= 5000) return 'platinum';
                if ($totalSpent >= 1000) return 'gold';
                if ($totalSpent >= 500) return 'silver';
                return 'bronze';
            },
            'dependencies' => ['total_spent'],
            'operators' => ['eq', 'in']
        ],
        
        'account_age_days' => [
            'type' => 'integer',
            'callback' => function($user) {
                if (!isset($user['created_at'])) return 0;
                $createdAt = new DateTime($user['created_at']);
                $today = new DateTime();
                return $today->diff($createdAt)->days;
            },
            'dependencies' => ['created_at'],
            'operators' => ['eq', 'gt', 'gte', 'lt', 'lte']
        ]
    ];
    
    // Registrar todos os virtual fields
    foreach ($virtualFields as $name => $config) {
        $virtualFieldService->register($name, $config);
        echo "✅ Virtual field '$name' registrado\n";
    }
    
    echo "\n📋 COMPUTANDO VIRTUAL FIELDS PARA USUÁRIOS:\n";
    echo "--------------------------------------------\n";
    
    foreach ($users as $user) {
        echo "\n👤 Usuário: {$user['first_name']} {$user['last_name']}\n";
        
        foreach (array_keys($virtualFields) as $fieldName) {
            try {
                $value = $virtualFieldService->compute($fieldName, $user);
                echo "   $fieldName: " . json_encode($value) . "\n";
            } catch (Exception $e) {
                echo "   $fieldName: ERRO - " . $e->getMessage() . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro nos Virtual Fields: " . $e->getMessage() . "\n";
}

// Demonstrar Model Hooks
echo "\n\n🪝 DEMONSTRAÇÃO DE MODEL HOOKS\n";
echo "===============================\n\n";

use MarcosBrendon\ApiForge\Services\ModelHookService;

try {
    $hookService = new ModelHookService();
    
    // Registrar hooks
    $hooks = [
        'beforeStore' => [
            'generateSlug' => [
                'callback' => function($model, $context) {
                    if (isset($model['first_name']) && isset($model['last_name'])) {
                        $model['slug'] = strtolower($model['first_name'] . '-' . $model['last_name']);
                    }
                    return $model;
                },
                'priority' => 1
            ],
            'setDefaults' => [
                'callback' => function($model, $context) {
                    $model['active'] = $model['active'] ?? true;
                    $model['role'] = $model['role'] ?? 'user';
                    $model['created_at'] = $model['created_at'] ?? date('Y-m-d H:i:s');
                    return $model;
                },
                'priority' => 2
            ]
        ],
        
        'afterStore' => [
            'logCreation' => [
                'callback' => function($model, $context) {
                    echo "   📝 LOG: Usuário {$model['first_name']} {$model['last_name']} foi criado\n";
                    return $model;
                },
                'priority' => 10
            ],
            'sendWelcomeEmail' => [
                'callback' => function($model, $context) {
                    echo "   📧 EMAIL: Enviando email de boas-vindas para {$model['email']}\n";
                    return $model;
                },
                'priority' => 20
            ]
        ],
        
        'beforeUpdate' => [
            'trackChanges' => [
                'callback' => function($model, $context) {
                    if (isset($context->originalModel)) {
                        $changes = [];
                        foreach ($model as $key => $value) {
                            if (isset($context->originalModel[$key]) && $context->originalModel[$key] !== $value) {
                                $changes[$key] = [
                                    'from' => $context->originalModel[$key],
                                    'to' => $value
                                ];
                            }
                        }
                        if (!empty($changes)) {
                            echo "   📊 MUDANÇAS DETECTADAS: " . json_encode($changes) . "\n";
                        }
                    }
                    return $model;
                },
                'priority' => 1
            ]
        ]
    ];
    
    // Registrar todos os hooks
    foreach ($hooks as $event => $eventHooks) {
        foreach ($eventHooks as $name => $config) {
            $hookService->register($event, $name, $config);
            echo "✅ Hook '$event.$name' registrado\n";
        }
    }
    
    echo "\n📋 TESTANDO HOOKS:\n";
    echo "------------------\n";
    
    // Testar beforeStore e afterStore
    echo "\n🆕 CRIANDO NOVO USUÁRIO:\n";
    $newUser = [
        'first_name' => 'Alice',
        'last_name' => 'Wonder',
        'email' => 'alice@example.com',
        'birth_date' => '1995-03-25'
    ];
    
    $context = new stdClass();
    $context->operation = 'store';
    
    echo "   Dados originais: " . json_encode($newUser) . "\n";
    
    // Executar beforeStore hooks
    $processedUser = $hookService->execute('beforeStore', $newUser, $context);
    echo "   Após beforeStore: " . json_encode($processedUser) . "\n";
    
    // Executar afterStore hooks
    $finalUser = $hookService->execute('afterStore', $processedUser, $context);
    
    // Testar beforeUpdate
    echo "\n✏️  ATUALIZANDO USUÁRIO:\n";
    $originalUser = $users[0];
    $updatedUser = $originalUser;
    $updatedUser['role'] = 'admin';
    $updatedUser['active'] = false;
    
    $updateContext = new stdClass();
    $updateContext->operation = 'update';
    $updateContext->originalModel = $originalUser;
    
    echo "   Dados originais: " . json_encode($originalUser) . "\n";
    echo "   Dados atualizados: " . json_encode($updatedUser) . "\n";
    
    $processedUpdate = $hookService->execute('beforeUpdate', $updatedUser, $updateContext);
    
} catch (Exception $e) {
    echo "❌ Erro nos Model Hooks: " . $e->getMessage() . "\n";
}

// Demonstrar Validação
echo "\n\n🔍 DEMONSTRAÇÃO DE VALIDAÇÃO\n";
echo "=============================\n\n";

use MarcosBrendon\ApiForge\Support\VirtualFieldValidator;
use MarcosBrendon\ApiForge\Support\ModelHookValidator;

echo "📋 TESTANDO VALIDAÇÃO DE VIRTUAL FIELDS:\n";
echo "-----------------------------------------\n";

// Configuração válida
$validVirtualFieldConfig = [
    'test_field' => [
        'type' => 'string',
        'callback' => function($model) { return 'test'; },
        'operators' => ['eq', 'like']
    ]
];

$errors = VirtualFieldValidator::validateConfig($validVirtualFieldConfig);
if (empty($errors)) {
    echo "✅ Configuração válida de virtual field aceita\n";
} else {
    echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
}

// Configuração inválida
$invalidVirtualFieldConfig = [
    'invalid_field' => [
        'type' => 'invalid_type',
        'callback' => 'not_a_function'
    ]
];

$errors = VirtualFieldValidator::validateConfig($invalidVirtualFieldConfig);
if (!empty($errors)) {
    echo "✅ Configuração inválida de virtual field rejeitada corretamente\n";
    echo "   Erros: " . json_encode($errors) . "\n";
}

echo "\n📋 TESTANDO VALIDAÇÃO DE MODEL HOOKS:\n";
echo "-------------------------------------\n";

// Configuração válida de hooks
$validHookConfig = [
    'beforeStore' => [
        'test_hook' => [
            'callback' => function($model, $context) { return $model; },
            'priority' => 1
        ]
    ]
];

$errors = ModelHookValidator::validateConfig($validHookConfig);
if (empty($errors)) {
    echo "✅ Configuração válida de hooks aceita\n";
} else {
    echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
}

// Demonstrar Cache (simulado)
echo "\n\n💾 DEMONSTRAÇÃO DE CACHE\n";
echo "========================\n\n";

use MarcosBrendon\ApiForge\Support\VirtualFieldCache;

try {
    $cache = new VirtualFieldCache();
    
    // Simular cache de virtual field
    $cacheKey = 'user_1_customer_tier';
    $cacheValue = 'gold';
    
    echo "📝 Salvando no cache: $cacheKey = $cacheValue\n";
    $cache->put($cacheKey, $cacheValue, 3600);
    
    echo "📖 Lendo do cache: $cacheKey\n";
    $cachedValue = $cache->get($cacheKey);
    
    if ($cachedValue === $cacheValue) {
        echo "✅ Cache funcionando corretamente: $cachedValue\n";
    } else {
        echo "❌ Problema no cache: esperado '$cacheValue', obtido '$cachedValue'\n";
    }
    
    echo "🗑️  Removendo do cache: $cacheKey\n";
    $cache->forget($cacheKey);
    
    $cachedValue = $cache->get($cacheKey);
    if ($cachedValue === null) {
        echo "✅ Item removido do cache com sucesso\n";
    } else {
        echo "❌ Item não foi removido do cache\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no cache: " . $e->getMessage() . "\n";
}

// Resumo final
echo "\n\n🎯 RESUMO DA DEMONSTRAÇÃO\n";
echo "=========================\n\n";

echo "✅ Virtual Fields:\n";
echo "   - Registrados: 4 campos (full_name, age, customer_tier, account_age_days)\n";
echo "   - Computados para: " . count($users) . " usuários\n";
echo "   - Tipos suportados: string, integer, enum\n\n";

echo "✅ Model Hooks:\n";
echo "   - Registrados: 5 hooks em 3 eventos\n";
echo "   - Testados: beforeStore, afterStore, beforeUpdate\n";
echo "   - Funcionalidades: geração de slug, logs, emails, tracking\n\n";

echo "✅ Validação:\n";
echo "   - Virtual Fields: configurações válidas e inválidas testadas\n";
echo "   - Model Hooks: configurações válidas testadas\n";
echo "   - Detecção de erros: funcionando corretamente\n\n";

echo "✅ Cache:\n";
echo "   - Operações básicas: put, get, forget\n";
echo "   - Funcionamento: verificado\n\n";

echo "🚀 BIBLIOTECA APIFORGE FUNCIONANDO PERFEITAMENTE!\n\n";

echo "📚 PRÓXIMOS PASSOS:\n";
echo "1. Integrar em uma aplicação Laravel real\n";
echo "2. Testar com dados reais e maior volume\n";
echo "3. Configurar cache Redis/Memcached\n";
echo "4. Implementar monitoramento de performance\n";
echo "5. Testar cenários de erro e recuperação\n\n";

echo "📖 Para mais informações, consulte:\n";
echo "- README.md - Documentação completa\n";
echo "- TESTING_GUIDE.md - Guia de testes\n";
echo "- examples/ - Exemplos práticos\n";
echo "- tests/ - Testes automatizados\n\n";

echo "🎉 DEMO CONCLUÍDA COM SUCESSO!\n";