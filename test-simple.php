<?php

require_once 'vendor/autoload.php';

echo "=== TESTE SIMPLES DA BIBLIOTECA APIFORGE ===\n\n";

// Teste 1: Verificar se as classes existem
echo "1. Verificando Classes Principais...\n";

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

$classesFound = 0;
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class\n";
        $classesFound++;
    } else {
        echo "❌ $class (não encontrada)\n";
    }
}

echo "\nClasses encontradas: $classesFound/" . count($classes) . "\n";

// Teste 2: Validação de Virtual Fields (sem dependências do Laravel)
echo "\n2. Testando Validação de Virtual Fields...\n";
try {
    
    $validConfig = [
        'full_name' => [
            'type' => 'string',
            'callback' => function($model) { return $model['first_name'] . ' ' . $model['last_name']; },
            'dependencies' => ['first_name', 'last_name'],
            'operators' => ['eq', 'like']
        ]
    ];
    
    $errors = \MarcosBrendon\ApiForge\Support\VirtualFieldValidator::validateConfig($validConfig);
    if (empty($errors)) {
        echo "✅ Configuração de virtual field válida\n";
    } else {
        echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
    }
    
    // Teste com configuração inválida
    $invalidConfig = [
        'invalid_field' => [
            'type' => 'invalid_type',
            'callback' => 'not_a_function'
        ]
    ];
    
    $errors = \MarcosBrendon\ApiForge\Support\VirtualFieldValidator::validateConfig($invalidConfig);
    if (!empty($errors)) {
        echo "✅ Configuração inválida rejeitada corretamente\n";
    } else {
        echo "❌ Configuração inválida foi aceita (erro!)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na validação: " . $e->getMessage() . "\n";
}

// Teste 3: Validação de Model Hooks
echo "\n3. Testando Validação de Model Hooks...\n";
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
    
    $errors = \MarcosBrendon\ApiForge\Support\ModelHookValidator::validateConfig($validHooks);
    if (empty($errors)) {
        echo "✅ Configuração de hooks válida\n";
    } else {
        echo "❌ Erros encontrados: " . json_encode($errors) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na validação de hooks: " . $e->getMessage() . "\n";
}

// Teste 4: Verificar Exemplos
echo "\n4. Verificando Exemplos...\n";

$examples = [
    'examples/UserController.php',
    'examples/AdvancedBusinessLogicController.php',
    'examples/PerformanceOptimizationExamples.php'
];

$examplesFound = 0;
foreach ($examples as $example) {
    if (file_exists($example)) {
        echo "✅ $example\n";
        $examplesFound++;
    } else {
        echo "❌ $example (não encontrado)\n";
    }
}

echo "\nExemplos encontrados: $examplesFound/" . count($examples) . "\n";

// Teste 5: Verificar estrutura do projeto
echo "\n5. Verificando Estrutura do Projeto...\n";

$directories = [
    'src/Services',
    'src/Support',
    'src/Exceptions',
    'tests/Unit',
    'tests/Feature',
    'examples',
    'config'
];

$dirsFound = 0;
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "✅ $dir/\n";
        $dirsFound++;
    } else {
        echo "❌ $dir/ (não encontrado)\n";
    }
}

echo "\nDiretórios encontrados: $dirsFound/" . count($directories) . "\n";

// Teste 6: Verificar arquivos principais
echo "\n6. Verificando Arquivos Principais...\n";

$files = [
    'composer.json',
    'README.md',
    'src/ApiForgeServiceProvider.php',
    'config/apiforge.php'
];

$filesFound = 0;
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
        $filesFound++;
    } else {
        echo "❌ $file (não encontrado)\n";
    }
}

echo "\nArquivos encontrados: $filesFound/" . count($files) . "\n";

// Teste 7: Verificar composer.json
echo "\n7. Verificando composer.json...\n";
try {
    $composer = json_decode(file_get_contents('composer.json'), true);
    
    if (isset($composer['name']) && $composer['name'] === 'marcosbrendon/apiforge') {
        echo "✅ Nome do pacote correto\n";
    } else {
        echo "❌ Nome do pacote incorreto\n";
    }
    
    if (isset($composer['autoload']['psr-4']['MarcosBrendon\\ApiForge\\'])) {
        echo "✅ Autoload PSR-4 configurado\n";
    } else {
        echo "❌ Autoload PSR-4 não configurado\n";
    }
    
    if (isset($composer['require']['laravel/framework'])) {
        echo "✅ Dependência do Laravel configurada\n";
    } else {
        echo "❌ Dependência do Laravel não configurada\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar composer.json: " . $e->getMessage() . "\n";
}

echo "\n=== RESUMO DOS TESTES ===\n";
echo "✅ Classes principais: $classesFound/" . count($classes) . "\n";
echo "✅ Exemplos: $examplesFound/" . count($examples) . "\n";
echo "✅ Diretórios: $dirsFound/" . count($directories) . "\n";
echo "✅ Arquivos: $filesFound/" . count($files) . "\n";

$totalScore = $classesFound + $examplesFound + $dirsFound + $filesFound;
$maxScore = count($classes) + count($examples) + count($directories) + count($files);
$percentage = round(($totalScore / $maxScore) * 100, 1);

echo "\nPontuação geral: $totalScore/$maxScore ($percentage%)\n";

if ($percentage >= 90) {
    echo "🎉 EXCELENTE! A biblioteca está bem estruturada.\n";
} elseif ($percentage >= 70) {
    echo "👍 BOM! A biblioteca está funcional com alguns itens faltando.\n";
} elseif ($percentage >= 50) {
    echo "⚠️  REGULAR. A biblioteca precisa de alguns ajustes.\n";
} else {
    echo "❌ CRÍTICO. A biblioteca precisa de muitos ajustes.\n";
}

echo "\n=== PRÓXIMOS PASSOS PARA TESTE COMPLETO ===\n";
echo "1. Executar testes automatizados: composer test\n";
echo "2. Criar uma aplicação Laravel de teste\n";
echo "3. Instalar o pacote via composer\n";
echo "4. Configurar modelos e controllers usando os exemplos\n";
echo "5. Testar endpoints da API com virtual fields e hooks\n";
echo "6. Verificar performance e cache\n";
echo "7. Testar cenários de erro e validação\n\n";

echo "Para testar em uma aplicação Laravel real:\n";
echo "composer require marcosbrendon/apiforge\n";
echo "php artisan vendor:publish --provider=\"MarcosBrendon\\ApiForge\\ApiForgeServiceProvider\"\n";
echo "php artisan migrate\n\n";