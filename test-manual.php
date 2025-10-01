<?php

require_once 'vendor/autoload.php';

use MarcosBrendon\ApiForge\Services\VirtualFieldService;
use MarcosBrendon\ApiForge\Services\ModelHookService;
use MarcosBrendon\ApiForge\Support\VirtualFieldValidator;
use MarcosBrendon\ApiForge\Support\ModelHookValidator;

echo "=== TESTE MANUAL DA BIBLIOTECA APIFORGE ===\n\n";

// Teste 1: Validação de Virtual Fields
echo "1. Testando Validação de Virtual Fields...\n";
try {
    $validConfig = [
        'full_name' => [
            'type' => 'string',
            'callback' => function($model) { return $model['first_name'] . ' ' . $model['last_name']; },
            'dependencies' => ['first_name', 'last_name'],
            'operators' => ['eq', 'like']
        ]
    ];
    
    $errors = VirtualFieldValidator::validateConfig($validConfig);
    if (empty($errors)) {
        echo "✅ Configuração válida aceita\n";
    } else {
        echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na validação: " . $e->getMessage() . "\n";
}

// Teste 2: Validação de Model Hooks
echo "\n2. Testando Validação de Model Hooks...\n";
try {
    $validHooks = [
        'beforeStore' => [
            'generateSlug' => [
                'callback' => function($model, $context) { 
                    $model['slug'] = strtolower($model['name']); 
                    return $model;
                },
                'priority' => 1
            ]
        ]
    ];
    
    $errors = ModelHookValidator::validateConfig($validHooks);
    if (empty($errors)) {
        echo "✅ Hooks válidos aceitos\n";
    } else {
        echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na validação de hooks: " . $e->getMessage() . "\n";
}

// Teste 3: Virtual Field Service
echo "\n3. Testando Virtual Field Service...\n";
try {
    $virtualFieldService = new VirtualFieldService();
    
    // Registrar um virtual field
    $virtualFieldService->register('test_field', [
        'type' => 'string',
        'callback' => function($model) { 
            return 'Test: ' . ($model['name'] ?? 'Unknown'); 
        },
        'operators' => ['eq', 'like']
    ]);
    
    // Simular um modelo
    $testModel = ['name' => 'John Doe', 'email' => 'john@example.com'];
    
    // Computar o virtual field
    $result = $virtualFieldService->compute('test_field', $testModel);
    echo "✅ Virtual field computado: '$result'\n";
    
} catch (Exception $e) {
    echo "❌ Erro no Virtual Field Service: " . $e->getMessage() . "\n";
}

// Teste 4: Model Hook Service
echo "\n4. Testando Model Hook Service...\n";
try {
    $hookService = new ModelHookService();
    
    // Registrar um hook
    $hookService->register('beforeStore', 'testHook', [
        'callback' => function($model, $context) {
            $model['processed'] = true;
            return $model;
        },
        'priority' => 1
    ]);
    
    // Simular execução do hook
    $testModel = ['name' => 'Test User'];
    $context = new stdClass();
    $context->operation = 'store';
    
    $result = $hookService->execute('beforeStore', $testModel, $context);
    
    if (isset($result['processed']) && $result['processed'] === true) {
        echo "✅ Hook executado com sucesso\n";
    } else {
        echo "❌ Hook não executou corretamente\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no Model Hook Service: " . $e->getMessage() . "\n";
}

// Teste 5: Configuração do Config
echo "\n5. Testando Configuração...\n";
try {
    $configPath = __DIR__ . '/config/apiforge.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        
        if (isset($config['virtual_fields']) && isset($config['model_hooks'])) {
            echo "✅ Arquivo de configuração encontrado e contém as novas seções\n";
            echo "   - Virtual Fields configurado: " . (isset($config['virtual_fields']['enabled']) ? 'Sim' : 'Não') . "\n";
            echo "   - Model Hooks configurado: " . (isset($config['model_hooks']['enabled']) ? 'Sim' : 'Não') . "\n";
        } else {
            echo "⚠️  Arquivo de configuração existe mas não contém as novas seções\n";
        }
    } else {
        echo "⚠️  Arquivo de configuração não encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar configuração: " . $e->getMessage() . "\n";
}

// Teste 6: Verificar Classes Principais
echo "\n6. Verificando Classes Principais...\n";

$classes = [
    'MarcosBrendon\\ApiForge\\Services\\VirtualFieldService',
    'MarcosBrendon\\ApiForge\\Services\\ModelHookService',
    'MarcosBrendon\\ApiForge\\Support\\VirtualFieldValidator',
    'MarcosBrendon\\ApiForge\\Support\\ModelHookValidator',
    'MarcosBrendon\\ApiForge\\Support\\VirtualFieldCache',
    'MarcosBrendon\\ApiForge\\Support\\VirtualFieldPerformanceManager',
    'MarcosBrendon\\ApiForge\\Exceptions\\VirtualFieldConfigurationException',
    'MarcosBrendon\\ApiForge\\Exceptions\\VirtualFieldComputationException'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class\n";
    } else {
        echo "❌ $class (não encontrada)\n";
    }
}

// Teste 7: Verificar Exemplos
echo "\n7. Verificando Exemplos...\n";

$examples = [
    'examples/UserController.php',
    'examples/AdvancedBusinessLogicController.php',
    'examples/PerformanceOptimizationExamples.php'
];

foreach ($examples as $example) {
    if (file_exists($example)) {
        echo "✅ $example\n";
    } else {
        echo "❌ $example (não encontrado)\n";
    }
}

echo "\n=== RESUMO DOS TESTES ===\n";
echo "Os testes manuais básicos foram executados.\n";
echo "Para testes mais completos, execute: composer test\n";
echo "Para testar em uma aplicação Laravel real, siga as instruções no README.md\n\n";

echo "=== PRÓXIMOS PASSOS PARA TESTE COMPLETO ===\n";
echo "1. Criar uma aplicação Laravel de teste\n";
echo "2. Instalar o pacote via composer\n";
echo "3. Configurar modelos e controllers\n";
echo "4. Testar endpoints da API\n";
echo "5. Verificar virtual fields e hooks em ação\n";