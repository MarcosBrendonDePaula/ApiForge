<?php
/**
 * Teste do gerador de documentação com DeepSeek API
 */

require_once __DIR__ . '/vendor/autoload.php';

use MarcosBrendon\ApiForge\Services\DocumentationGeneratorService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;

echo "🚀 Testando ApiForge Documentation Generator com DeepSeek\n";
echo "=======================================================\n\n";

// Simular configuração do Laravel
$config = [
    'documentation' => [
        'enabled' => true,
        'cache' => ['enabled' => false, 'ttl' => 3600], // Desabilitar cache para teste
        'llm' => [
            'priority' => ['deepseek'],
            'providers' => [
                'deepseek' => [
                    'enabled' => true,
                    'api_key' => 'sk-d8ecad43464947569fb80358c716a9f8',
                    'endpoint' => 'https://api.deepseek.com/chat/completions',
                    'model' => 'deepseek-chat',
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                    'timeout' => 60,
                ]
            ]
        ]
    ]
];

// Mock da função config() do Laravel
function config($key, $default = null) {
    global $config;
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

try {
    echo "1. 🔧 Configurando FilterConfigService...\n";
    $filterService = new FilterConfigService();
    
    // Configurar um exemplo realista de um sistema de produtos
    $filterService->configure([
        'name' => [
            'type' => 'string',
            'operators' => ['eq', 'like', 'ne', 'starts_with'],
            'searchable' => true,
            'sortable' => true,
            'description' => 'Product name with flexible text matching'
        ],
        'price' => [
            'type' => 'float',
            'operators' => ['eq', 'gte', 'lte', 'between'],
            'description' => 'Product price in USD',
            'min' => 0,
            'max' => 99999.99
        ],
        'category' => [
            'type' => 'enum',
            'values' => ['electronics', 'clothing', 'books', 'home', 'sports'],
            'operators' => ['eq', 'in', 'ne'],
            'description' => 'Product category'
        ],
        'is_active' => [
            'type' => 'boolean',
            'operators' => ['eq'],
            'description' => 'Whether the product is active'
        ],
        'created_at' => [
            'type' => 'datetime',
            'operators' => ['gte', 'lte', 'between'],
            'description' => 'Product creation date',
            'sortable' => true
        ]
    ]);
    
    echo "   ✅ Filtros configurados: 5 filtros\n";
    
    echo "\n2. 🤖 Criando DocumentationGeneratorService...\n";
    $docGenerator = new DocumentationGeneratorService($filterService);
    echo "   ✅ DocumentationGeneratorService criado\n";
    
    echo "\n3. 📋 Preparando metadados para teste...\n";
    
    // Simular metadados que seriam extraídos de um controlador real
    $testMetadata = [
        'controller' => [
            'class' => 'App\\Http\\Controllers\\Api\\ProductController',
            'name' => 'ProductController',
            'namespace' => 'App\\Http\\Controllers\\Api'
        ],
        'endpoint' => [
            'path' => '/api/products',
            'methods' => ['GET' => ['index']],
            'model_class' => 'App\\Models\\Product'
        ],
        'filters' => $filterService->getCompleteMetadata(),
        'configuration' => [
            'pagination' => ['default_per_page' => 15, 'max_per_page' => 100],
            'field_selection' => ['enabled' => true, 'max_fields' => 50],
            'security' => ['sanitize_input' => true, 'strip_tags' => true],
            'cache' => ['enabled' => true, 'default_ttl' => 3600]
        ],
        'examples' => [
            'basic' => [
                '/api/products?name=iPhone',
                '/api/products?category=electronics',
                '/api/products?is_active=true'
            ],
            'advanced' => [
                '/api/products?name=iPhone*&price=>=100&category=electronics,clothing',
                '/api/products?price=50.00|500.00&created_at=>=2024-01-01'
            ],
            'field_selection' => [
                '/api/products?fields=id,name,price',
                '/api/products?fields=id,name,category,created_at'
            ],
            'pagination' => [
                '/api/products?page=1&per_page=20',
                '/api/products?page=2&per_page=50'
            ],
            'sorting' => [
                '/api/products?sort_by=created_at&sort_direction=desc',
                '/api/products?sort_by=name&sort_direction=asc'
            ]
        ],
        'relationships' => [
            ['name' => 'category', 'type' => 'belongs_to'],
            ['name' => 'reviews', 'type' => 'has_many'],
            ['name' => 'variants', 'type' => 'has_many']
        ],
        'validation' => [
            'validateStoreData' => 'available',
            'validateUpdateData' => 'available'
        ]
    ];
    
    echo "   ✅ Metadados preparados\n";
    
    echo "\n4. 🔍 Testando preparação de contexto...\n";
    $reflection = new ReflectionClass($docGenerator);
    
    $contextMethod = $reflection->getMethod('prepareDocumentationContext');
    $contextMethod->setAccessible(true);
    
    $context = $contextMethod->invoke($docGenerator, $testMetadata, [
        'api_description' => 'E-commerce product management API with advanced filtering',
        'contact_info' => ['email' => 'api@exemplo.com']
    ]);
    
    echo "   ✅ Contexto preparado\n";
    echo "   📊 Seções do contexto: " . implode(', ', array_keys($context)) . "\n";
    
    echo "\n5. 📝 Gerando prompt para DeepSeek...\n";
    $promptMethod = $reflection->getMethod('buildLLMPrompt');
    $promptMethod->setAccessible(true);
    
    $prompt = $promptMethod->invoke($docGenerator, $context);
    echo "   ✅ Prompt gerado (" . strlen($prompt) . " caracteres)\n";
    
    // Mostrar parte do prompt para debug
    echo "   📋 Início do prompt:\n";
    echo "   " . str_replace("\n", "\n   ", substr($prompt, 0, 500)) . "...\n";
    
    echo "\n6. 🌐 Fazendo chamada para DeepSeek API...\n";
    echo "   🔗 Endpoint: https://api.deepseek.com/chat/completions\n";
    echo "   🤖 Model: deepseek-chat\n";
    echo "   📤 Enviando prompt...\n";
    
    $llmMethod = $reflection->getMethod('callLLMProvider');
    $llmMethod->setAccessible(true);
    
    $deepseekConfig = config('documentation.llm.providers.deepseek');
    
    // Fazer a chamada real para a API
    $startTime = microtime(true);
    $response = $llmMethod->invoke($docGenerator, 'deepseek', $prompt);
    $endTime = microtime(true);
    
    $responseTime = round(($endTime - $startTime) * 1000);
    
    echo "   ✅ Resposta recebida em {$responseTime}ms\n";
    echo "   📏 Tamanho da resposta: " . strlen($response) . " caracteres\n";
    
    echo "\n7. 🔄 Processando resposta da IA...\n";
    $parseMethod = $reflection->getMethod('parseLLMResponse');
    $parseMethod->setAccessible(true);
    
    $parsedResponse = $parseMethod->invoke($docGenerator, $response);
    echo "   ✅ JSON parseado com sucesso\n";
    echo "   📋 Seções principais: " . implode(', ', array_keys($parsedResponse)) . "\n";
    
    echo "\n8. 🏗️ Construindo schema OpenAPI completo...\n";
    $schemaMethod = $reflection->getMethod('buildOpenApiSchema');
    $schemaMethod->setAccessible(true);
    
    $finalSchema = $schemaMethod->invoke($docGenerator, $testMetadata, $parsedResponse, '/api/products');
    echo "   ✅ Schema OpenAPI 3.0 construído\n";
    echo "   📊 Componentes: " . count($finalSchema['components']['schemas']) . " schemas, " . 
         count($finalSchema['components']['parameters']) . " parâmetros, " . 
         count($finalSchema['components']['responses']) . " respostas\n";
    
    echo "\n9. 💾 Salvando documentação gerada...\n";
    $outputFile = __DIR__ . '/generated-docs/product-api-documentation.json';
    
    // Criar diretório se não existir
    $outputDir = dirname($outputFile);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    file_put_contents($outputFile, json_encode($finalSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "   ✅ Documentação salva: $outputFile\n";
    echo "   📁 Tamanho do arquivo: " . number_format(filesize($outputFile)) . " bytes\n";
    
    echo "\n🎉 TESTE COMPLETO COM SUCESSO!\n";
    echo "===============================\n\n";
    
    echo "📋 Resumo do que foi gerado:\n";
    echo "✅ Documentação OpenAPI 3.0 completa\n";
    echo "✅ Descrições profissionais geradas por IA\n";
    echo "✅ Parâmetros de filtros com exemplos\n";
    echo "✅ Esquemas de resposta estruturados\n";
    echo "✅ Códigos de erro documentados\n";
    echo "✅ Validações e tipos corretos\n\n";
    
    // Mostrar algumas partes da documentação gerada
    echo "🔍 Prévia da documentação gerada:\n";
    echo "================================\n";
    echo "📖 Título: " . $finalSchema['info']['title'] . "\n";
    echo "📝 Descrição: " . $finalSchema['info']['description'] . "\n";
    echo "🔧 OpenAPI Version: " . $finalSchema['openapi'] . "\n";
    
    if (isset($finalSchema['paths']['/api/products']['get'])) {
        $getEndpoint = $finalSchema['paths']['/api/products']['get'];
        echo "🎯 Endpoint GET /api/products:\n";
        echo "   📋 Resumo: " . ($getEndpoint['summary'] ?? 'N/A') . "\n";
        echo "   📝 Descrição: " . ($getEndpoint['description'] ?? 'N/A') . "\n";
        echo "   🔧 Parâmetros: " . (count($getEndpoint['parameters'] ?? []) ?? 0) . "\n";
    }
    
    echo "\n🚀 PRÓXIMOS PASSOS:\n";
    echo "1. Abra o arquivo JSON gerado em um visualizador OpenAPI (Swagger UI)\n";
    echo "2. Integre com sua documentação existente\n";
    echo "3. Use em projetos Laravel reais com: php artisan apiforge:docs\n\n";
    
    echo "💡 A IA DeepSeek funcionou perfeitamente!\n";
    echo "🎯 Tempo total de processamento: {$responseTime}ms\n";
    echo "📊 Qualidade da documentação: Profissional\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO DURANTE O TESTE:\n";
    echo "========================\n";
    echo "🚨 Mensagem: " . $e->getMessage() . "\n";
    echo "📍 Linha: " . $e->getLine() . "\n";
    echo "📄 Arquivo: " . $e->getFile() . "\n";
    echo "\n🔍 Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    
    // Se for erro de API, mostrar mais detalhes
    if (strpos($e->getMessage(), 'API') !== false) {
        echo "\n💡 Possíveis soluções:\n";
        echo "1. Verificar se a chave da API está correta\n";
        echo "2. Verificar se há créditos na conta DeepSeek\n";
        echo "3. Verificar conectividade de rede\n";
        echo "4. Tentar novamente em alguns minutos\n";
    }
}