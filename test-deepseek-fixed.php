<?php
/**
 * Teste standalone do gerador com DeepSeek API (sem Laravel)
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

echo "🚀 Testando Geração de Documentação com DeepSeek API\n";
echo "====================================================\n\n";

// Configuração da API
$deepseekConfig = [
    'api_key' => 'sk-d8ecad43464947569fb80358c716a9f8',
    'endpoint' => 'https://api.deepseek.com/chat/completions',
    'model' => 'deepseek-chat',
    'temperature' => 0.1,
    'max_tokens' => 4000,
    'timeout' => 60
];

echo "🔧 Configuração da API:\n";
echo "   🔗 Endpoint: {$deepseekConfig['endpoint']}\n";
echo "   🤖 Model: {$deepseekConfig['model']}\n";
echo "   🌡️ Temperature: {$deepseekConfig['temperature']}\n";
echo "   📊 Max Tokens: {$deepseekConfig['max_tokens']}\n\n";

// Simular metadados ricos como os gerados pelo ApiForge
$apiMetadata = [
    'project_info' => [
        'name' => 'E-commerce API',
        'description' => 'Advanced product management API with filtering capabilities',
        'version' => '1.0.0',
        'framework' => 'Laravel 11.x',
        'package' => 'ApiForge - Advanced API Filters'
    ],
    'endpoint_metadata' => [
        'controller' => ['name' => 'ProductController'],
        'endpoint' => ['path' => '/api/products', 'model_class' => 'Product'],
        'filters' => [
            'filter_config' => [
                'name' => [
                    'type' => 'string',
                    'operators' => ['eq', 'like', 'ne', 'starts_with'],
                    'searchable' => true,
                    'sortable' => true,
                    'description' => 'Product name with flexible text matching',
                    'example' => [
                        'eq' => 'name=iPhone 15',
                        'like' => 'name=*iPhone*',
                        'starts_with' => 'name=iPhone*'
                    ]
                ],
                'price' => [
                    'type' => 'float',
                    'operators' => ['eq', 'gte', 'lte', 'between'],
                    'description' => 'Product price in USD',
                    'min' => 0,
                    'max' => 99999.99,
                    'example' => [
                        'gte' => 'price=>=100.00',
                        'between' => 'price=50.00|500.00',
                        'lte' => 'price=<=1000'
                    ]
                ],
                'category' => [
                    'type' => 'enum',
                    'values' => ['electronics', 'clothing', 'books', 'home', 'sports'],
                    'operators' => ['eq', 'in', 'ne'],
                    'description' => 'Product category classification',
                    'example' => [
                        'eq' => 'category=electronics',
                        'in' => 'category=electronics,clothing'
                    ]
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'operators' => ['eq'],
                    'description' => 'Whether the product is active and available',
                    'example' => ['eq' => 'is_active=true']
                ],
                'stock_quantity' => [
                    'type' => 'integer',
                    'operators' => ['eq', 'gte', 'lte', 'between'],
                    'description' => 'Available stock quantity',
                    'min' => 0,
                    'example' => [
                        'gte' => 'stock_quantity=>=1',
                        'eq' => 'stock_quantity=0',
                        'between' => 'stock_quantity=1|100'
                    ]
                ],
                'created_at' => [
                    'type' => 'datetime',
                    'operators' => ['gte', 'lte', 'between'],
                    'description' => 'Product creation date and time',
                    'sortable' => true,
                    'example' => [
                        'gte' => 'created_at=>=2024-01-01',
                        'between' => 'created_at=2024-01-01|2024-12-31'
                    ]
                ]
            ],
            'searchable_fields' => ['name'],
            'sortable_fields' => ['name', 'price', 'created_at'],
            'available_operators' => [
                'eq' => ['name' => 'Equals', 'example' => 'field=value'],
                'like' => ['name' => 'Like', 'example' => 'field=*value*'],
                'gte' => ['name' => 'Greater than or equal', 'example' => 'field=>=100'],
                'lte' => ['name' => 'Less than or equal', 'example' => 'field=<=100'],
                'between' => ['name' => 'Between values', 'example' => 'field=10|20'],
                'in' => ['name' => 'In array', 'example' => 'field=val1,val2'],
            ]
        ],
        'examples' => [
            'basic' => [
                '/api/products?name=iPhone',
                '/api/products?category=electronics',
                '/api/products?is_active=true',
                '/api/products?price=>=100'
            ],
            'advanced' => [
                '/api/products?name=iPhone*&price=>=100&category=electronics,clothing',
                '/api/products?price=50.00|500.00&created_at=>=2024-01-01&stock_quantity=>=1',
                '/api/products?name=*Pro*&category=electronics&is_active=true&sort_by=price'
            ],
            'field_selection' => [
                '/api/products?fields=id,name,price',
                '/api/products?fields=id,name,category,stock_quantity,created_at'
            ],
            'pagination' => [
                '/api/products?page=1&per_page=20',
                '/api/products?page=2&per_page=50'
            ],
            'sorting' => [
                '/api/products?sort_by=created_at&sort_direction=desc',
                '/api/products?sort_by=name&sort_direction=asc',
                '/api/products?sort_by=price&sort_direction=desc'
            ]
        ]
    ],
    'documentation_requirements' => [
        'format' => 'OpenAPI 3.0',
        'include_examples' => true,
        'include_error_responses' => true,
        'detailed_descriptions' => true,
        'parameter_validation' => true,
        'response_schemas' => true
    ]
];

echo "📋 Preparando prompt especializado...\n";

$prompt = "You are an expert technical writer specializing in API documentation. Generate comprehensive, professional OpenAPI 3.0 documentation.\n\n";

$prompt .= "**PROJECT CONTEXT:**\n";
$prompt .= "- Name: {$apiMetadata['project_info']['name']}\n";
$prompt .= "- Framework: {$apiMetadata['project_info']['framework']}\n";
$prompt .= "- Package: {$apiMetadata['project_info']['package']}\n";
$prompt .= "- Description: {$apiMetadata['project_info']['description']}\n\n";

$prompt .= "**ENDPOINT INFORMATION:**\n";
$prompt .= "- Controller: {$apiMetadata['endpoint_metadata']['controller']['name']}\n";
$prompt .= "- Path: {$apiMetadata['endpoint_metadata']['endpoint']['path']}\n";
$prompt .= "- Model: {$apiMetadata['endpoint_metadata']['endpoint']['model_class']}\n\n";

$prompt .= "**AVAILABLE FILTERS:**\n";
foreach ($apiMetadata['endpoint_metadata']['filters']['filter_config'] as $field => $config) {
    $prompt .= "- {$field}: {$config['type']} (operators: " . implode(', ', $config['operators']) . ")\n";
    if (!empty($config['description'])) {
        $prompt .= "  Description: {$config['description']}\n";
    }
    if (!empty($config['example'])) {
        $prompt .= "  Examples: " . implode(', ', $config['example']) . "\n";
    }
}

$prompt .= "\n**API USAGE EXAMPLES:**\n";
$prompt .= "Basic filtering:\n";
foreach ($apiMetadata['endpoint_metadata']['examples']['basic'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\nAdvanced filtering:\n";
foreach ($apiMetadata['endpoint_metadata']['examples']['advanced'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\n**REQUIREMENTS:**\n";
$prompt .= "1. Generate complete OpenAPI 3.0 specification in JSON format\n";
$prompt .= "2. Include detailed parameter descriptions for all filters\n";
$prompt .= "3. Add comprehensive examples for all filter types and operators\n";
$prompt .= "4. Document response schemas with proper data types\n";
$prompt .= "5. Include error response documentation (400, 422, 500)\n";
$prompt .= "6. Add pagination parameters (page, per_page)\n";
$prompt .= "7. Document field selection parameter (fields)\n";
$prompt .= "8. Include sorting parameters (sort_by, sort_direction)\n";
$prompt .= "9. Use professional, clear descriptions\n";
$prompt .= "10. Include security considerations\n\n";

$prompt .= "Generate ONLY the OpenAPI 3.0 specification in valid JSON format. Focus on creating a complete, production-ready API documentation.";

echo "   ✅ Prompt gerado (" . strlen($prompt) . " caracteres)\n\n";

echo "📤 Enviando requisição para DeepSeek...\n";

try {
    $startTime = microtime(true);
    
    // Fazer chamada HTTP para DeepSeek
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $deepseekConfig['api_key'],
        'Content-Type' => 'application/json',
    ])->timeout($deepseekConfig['timeout'])->post($deepseekConfig['endpoint'], [
        'model' => $deepseekConfig['model'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert API documentation generator. Generate only valid OpenAPI 3.0 JSON specification.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $deepseekConfig['temperature'],
        'max_tokens' => $deepseekConfig['max_tokens'],
    ]);

    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000);

    if (!$response->successful()) {
        throw new Exception("DeepSeek API error: " . $response->status() . " - " . $response->body());
    }

    $apiResponse = $response->json();
    $generatedContent = $apiResponse['choices'][0]['message']['content'];

    echo "   ✅ Resposta recebida em {$responseTime}ms\n";
    echo "   📏 Tamanho da resposta: " . strlen($generatedContent) . " caracteres\n";
    echo "   🎯 Tokens usados: " . ($apiResponse['usage']['total_tokens'] ?? 'N/A') . "\n\n";

    echo "🔄 Processando resposta da IA...\n";
    
    // Limpar markdown se presente
    $cleanedContent = preg_replace('/```json\s*/', '', $generatedContent);
    $cleanedContent = preg_replace('/```\s*$/', '', $cleanedContent);
    $cleanedContent = trim($cleanedContent);

    // Parsear JSON
    $documentation = json_decode($cleanedContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from DeepSeek: ' . json_last_error_msg());
    }

    echo "   ✅ JSON parseado com sucesso\n";
    echo "   📋 Seções principais: " . implode(', ', array_keys($documentation)) . "\n\n";

    echo "💾 Salvando documentação...\n";
    
    $outputDir = __DIR__ . '/generated-docs';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputFile = $outputDir . '/deepseek-product-api-docs.json';
    file_put_contents($outputFile, json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    
    $fileSize = filesize($outputFile);
    echo "   ✅ Arquivo salvo: $outputFile\n";
    echo "   📁 Tamanho: " . number_format($fileSize) . " bytes\n\n";
    
    // Criar versão HTML para visualização
    $htmlFile = $outputDir . '/deepseek-product-api-docs.html';
    $htmlContent = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>API Documentation - Generated by DeepSeek</title>
    <link rel='stylesheet' href='https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui.css' />
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        .header { background: #1f2937; color: white; padding: 20px; margin: -20px -20px 20px -20px; }
        .info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🚀 API Documentation</h1>
        <p>Generated by ApiForge + DeepSeek AI in {$responseTime}ms</p>
    </div>
    
    <div class='info'>
        <h3>📊 Generation Stats:</h3>
        <ul>
            <li><strong>Response Time:</strong> {$responseTime}ms</li>
            <li><strong>Generated Content:</strong> " . number_format(strlen($generatedContent)) . " characters</li>
            <li><strong>File Size:</strong> " . number_format($fileSize) . " bytes</li>
            <li><strong>AI Model:</strong> DeepSeek Chat</li>
        </ul>
    </div>
    
    <div id='swagger-ui'></div>
    
    <script src='https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui-bundle.js'></script>
    <script>
        const spec = " . json_encode($documentation) . ";
        
        SwaggerUIBundle({
            url: 'data:application/json;base64,' + btoa(JSON.stringify(spec)),
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.presets.standalone
            ],
            layout: 'StandaloneLayout'
        });
    </script>
</body>
</html>";
    
    file_put_contents($htmlFile, $htmlContent);
    echo "   ✅ HTML preview: $htmlFile\n\n";

    echo "🎉 SUCESSO COMPLETO!\n";
    echo "=====================\n\n";

    echo "📋 Documentação gerada pela IA:\n";
    echo "✅ Título: " . ($documentation['info']['title'] ?? 'N/A') . "\n";
    echo "✅ Versão: " . ($documentation['info']['version'] ?? 'N/A') . "\n";
    echo "✅ OpenAPI: " . ($documentation['openapi'] ?? 'N/A') . "\n";
    
    if (isset($documentation['paths'])) {
        echo "✅ Endpoints: " . count($documentation['paths']) . "\n";
        foreach ($documentation['paths'] as $path => $methods) {
            echo "   📍 $path: " . implode(', ', array_keys($methods)) . "\n";
        }
    }
    
    if (isset($documentation['components']['parameters'])) {
        echo "✅ Parâmetros: " . count($documentation['components']['parameters']) . "\n";
    }
    
    if (isset($documentation['components']['schemas'])) {
        echo "✅ Schemas: " . count($documentation['components']['schemas']) . "\n";
    }
    
    echo "\n🚀 PRÓXIMOS PASSOS:\n";
    echo "1. Abra $htmlFile no navegador para ver a documentação\n";
    echo "2. Use o arquivo JSON em ferramentas como Postman ou Insomnia\n";
    echo "3. Integre com seu projeto Laravel usando: php artisan apiforge:docs\n\n";
    
    echo "💡 DeepSeek AI funcionou PERFEITAMENTE!\n";
    echo "⚡ Tempo de geração: {$responseTime}ms\n";
    echo "🎯 Qualidade: Documentação profissional OpenAPI 3.0\n";
    echo "💰 Custo estimado: ~$0.01 USD\n";

} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo "========\n";
    echo "🚨 " . $e->getMessage() . "\n";
    echo "📍 Linha: " . $e->getLine() . "\n";
    echo "📄 Arquivo: " . $e->getFile() . "\n\n";
    
    echo "💡 Soluções possíveis:\n";
    echo "1. Verificar chave da API DeepSeek\n";
    echo "2. Verificar conectividade de internet\n";
    echo "3. Verificar se há créditos na conta\n";
    echo "4. Tentar novamente em alguns segundos\n";
}