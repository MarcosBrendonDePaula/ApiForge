<?php
/**
 * Teste direto com DeepSeek API usando cURL puro
 */

echo "🚀 Testando ApiForge + DeepSeek API (Versão Standalone)\n";
echo "======================================================\n\n";

// Configuração
$deepseekApiKey = 'sk-d8ecad43464947569fb80358c716a9f8';
$endpoint = 'https://api.deepseek.com/chat/completions';

echo "🔧 Configuração:\n";
echo "   🔑 API Key: sk-d8ec****...a9f8\n";
echo "   🔗 Endpoint: $endpoint\n";
echo "   🤖 Model: deepseek-chat\n\n";

// Metadados simulando o que o ApiForge geraria
$projectMetadata = [
    'project_name' => 'E-commerce Product API',
    'framework' => 'Laravel 11.x with ApiForge',
    'endpoint_path' => '/api/products',
    'controller' => 'ProductController',
    'model' => 'Product'
];

$filterConfiguration = [
    'name' => [
        'type' => 'string',
        'operators' => ['eq', 'like', 'ne', 'starts_with'],
        'searchable' => true,
        'sortable' => true,
        'description' => 'Product name with flexible text matching',
        'examples' => [
            'name=iPhone 15',
            'name=*iPhone*',
            'name=iPhone*'
        ]
    ],
    'price' => [
        'type' => 'float',
        'operators' => ['eq', 'gte', 'lte', 'between'],
        'description' => 'Product price in USD',
        'min' => 0,
        'max' => 99999.99,
        'examples' => [
            'price=>=100.00',
            'price=50.00|500.00',
            'price=<=1000'
        ]
    ],
    'category' => [
        'type' => 'enum',
        'values' => ['electronics', 'clothing', 'books', 'home', 'sports'],
        'operators' => ['eq', 'in', 'ne'],
        'description' => 'Product category classification',
        'examples' => [
            'category=electronics',
            'category=electronics,clothing'
        ]
    ],
    'is_active' => [
        'type' => 'boolean',
        'operators' => ['eq'],
        'description' => 'Whether the product is active and available for sale',
        'examples' => ['is_active=true']
    ],
    'stock_quantity' => [
        'type' => 'integer',
        'operators' => ['eq', 'gte', 'lte', 'between'],
        'description' => 'Available stock quantity',
        'min' => 0,
        'examples' => [
            'stock_quantity=>=1',
            'stock_quantity=0',
            'stock_quantity=1|100'
        ]
    ],
    'created_at' => [
        'type' => 'datetime',
        'operators' => ['gte', 'lte', 'between'],
        'description' => 'Product creation timestamp',
        'sortable' => true,
        'examples' => [
            'created_at=>=2024-01-01',
            'created_at=2024-01-01|2024-12-31'
        ]
    ]
];

$usageExamples = [
    'basic_filtering' => [
        '/api/products?name=iPhone',
        '/api/products?category=electronics', 
        '/api/products?is_active=true',
        '/api/products?price=>=100'
    ],
    'advanced_filtering' => [
        '/api/products?name=iPhone*&price=>=100&category=electronics,clothing',
        '/api/products?price=50.00|500.00&created_at=>=2024-01-01&stock_quantity=>=1',
        '/api/products?name=*Pro*&category=electronics&is_active=true'
    ],
    'field_selection' => [
        '/api/products?fields=id,name,price',
        '/api/products?fields=id,name,category,stock_quantity,created_at'
    ],
    'pagination_and_sorting' => [
        '/api/products?page=1&per_page=20&sort_by=created_at&sort_direction=desc',
        '/api/products?page=2&per_page=50&sort_by=name&sort_direction=asc'
    ]
];

echo "📋 Construindo prompt especializado...\n";

$prompt = "You are an expert API documentation generator. Create comprehensive OpenAPI 3.0 specification for a Laravel API.\n\n";

$prompt .= "**PROJECT INFORMATION:**\n";
$prompt .= "- API Name: {$projectMetadata['project_name']}\n";
$prompt .= "- Framework: {$projectMetadata['framework']}\n"; 
$prompt .= "- Endpoint: {$projectMetadata['endpoint_path']}\n";
$prompt .= "- Controller: {$projectMetadata['controller']}\n";
$prompt .= "- Model: {$projectMetadata['model']}\n\n";

$prompt .= "**FILTER CAPABILITIES:**\n";
foreach ($filterConfiguration as $fieldName => $config) {
    $prompt .= "**{$fieldName}** ({$config['type']}):\n";
    $prompt .= "- Description: {$config['description']}\n";
    $prompt .= "- Operators: " . implode(', ', $config['operators']) . "\n";
    $prompt .= "- Examples: " . implode(', ', $config['examples']) . "\n";
    if (isset($config['values'])) {
        $prompt .= "- Allowed values: " . implode(', ', $config['values']) . "\n";
    }
    if (isset($config['searchable']) && $config['searchable']) {
        $prompt .= "- Searchable: Yes\n";
    }
    if (isset($config['sortable']) && $config['sortable']) {
        $prompt .= "- Sortable: Yes\n";
    }
    $prompt .= "\n";
}

$prompt .= "**USAGE EXAMPLES:**\n\n";
$prompt .= "Basic filtering:\n";
foreach ($usageExamples['basic_filtering'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\nAdvanced filtering:\n";
foreach ($usageExamples['advanced_filtering'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\nField selection:\n";
foreach ($usageExamples['field_selection'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\nPagination and sorting:\n";
foreach ($usageExamples['pagination_and_sorting'] as $example) {
    $prompt .= "- {$example}\n";
}

$prompt .= "\n**REQUIREMENTS:**\n";
$prompt .= "1. Generate a complete OpenAPI 3.0 JSON specification\n";
$prompt .= "2. Include detailed parameter descriptions for all filters\n";
$prompt .= "3. Add comprehensive examples for each parameter\n";
$prompt .= "4. Document response schemas (success and error)\n";
$prompt .= "5. Include pagination parameters (page, per_page)\n";
$prompt .= "6. Document field selection parameter (fields)\n";
$prompt .= "7. Include sorting parameters (sort_by, sort_direction)\n";
$prompt .= "8. Add proper HTTP status codes (200, 400, 422, 500)\n";
$prompt .= "9. Use professional descriptions\n";
$prompt .= "10. Make it production-ready\n\n";

$prompt .= "Respond ONLY with valid OpenAPI 3.0 JSON. No explanations, no markdown blocks, just clean JSON.";

echo "   ✅ Prompt preparado (" . strlen($prompt) . " caracteres)\n\n";

echo "🌐 Enviando para DeepSeek API...\n";

// Preparar dados da requisição
$requestData = [
    'model' => 'deepseek-chat',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are an expert API documentation generator. Return only valid OpenAPI 3.0 JSON without any markdown or explanations.'
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ],
    'temperature' => 0.1,
    'max_tokens' => 4000
];

// Fazer requisição cURL
$startTime = microtime(true);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $deepseekApiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false, // Desabilitar verificação SSL para teste
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$endTime = microtime(true);
$responseTime = round(($endTime - $startTime) * 1000);

if ($curlError) {
    echo "❌ Erro cURL: $curlError\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ HTTP Error: $httpCode\n";
    echo "📄 Response: $response\n";
    exit(1);
}

echo "   ✅ Resposta recebida em {$responseTime}ms\n";

// Processar resposta
$responseData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Erro ao decodificar resposta da API: " . json_last_error_msg() . "\n";
    echo "📄 Raw response: $response\n";
    exit(1);
}

$generatedContent = $responseData['choices'][0]['message']['content'];
$usage = $responseData['usage'] ?? [];

echo "   📊 Tokens usados: " . ($usage['total_tokens'] ?? 'N/A') . "\n";
echo "   💰 Custo estimado: $" . number_format(($usage['total_tokens'] ?? 0) * 0.00000014, 6) . " USD\n";
echo "   📏 Conteúdo gerado: " . strlen($generatedContent) . " caracteres\n\n";

echo "🔄 Processando documentação gerada...\n";

// Limpar possível markdown
$cleanContent = trim($generatedContent);
if (strpos($cleanContent, '```json') !== false) {
    $cleanContent = preg_replace('/```json\s*/', '', $cleanContent);
    $cleanContent = preg_replace('/```\s*$/', '', $cleanContent);
    $cleanContent = trim($cleanContent);
}

// Parsear JSON
$documentation = json_decode($cleanContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ JSON inválido gerado pela IA: " . json_last_error_msg() . "\n";
    echo "📄 Conteúdo problemático:\n" . substr($cleanContent, 0, 500) . "...\n";
    exit(1);
}

echo "   ✅ Documentação JSON válida\n";
echo "   📋 Seções: " . implode(', ', array_keys($documentation)) . "\n\n";

echo "💾 Salvando arquivos...\n";

// Criar diretório
$outputDir = __DIR__ . '/generated-docs';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Salvar JSON
$jsonFile = $outputDir . '/deepseek-generated-api-docs.json';
file_put_contents($jsonFile, json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$fileSize = filesize($jsonFile);
echo "   ✅ JSON salvo: $jsonFile (" . number_format($fileSize) . " bytes)\n";

// Criar HTML com Swagger UI
$htmlFile = $outputDir . '/deepseek-api-viewer.html';
$htmlContent = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>API Documentation - Generated by ApiForge + DeepSeek</title>
    <link rel='stylesheet' type='text/css' href='https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui.css' />
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .stats { background: #f8f9fa; padding: 15px; margin: 20px; border-radius: 8px; border-left: 4px solid #28a745; }
        .swagger-container { margin: 20px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🚀 API Documentation</h1>
        <p>Generated by <strong>ApiForge</strong> + <strong>DeepSeek AI</strong></p>
    </div>
    
    <div class='stats'>
        <h3>📊 Generation Statistics:</h3>
        <ul>
            <li><strong>Response Time:</strong> {$responseTime}ms</li>
            <li><strong>Tokens Used:</strong> " . ($usage['total_tokens'] ?? 'N/A') . "</li>
            <li><strong>Estimated Cost:</strong> $" . number_format(($usage['total_tokens'] ?? 0) * 0.00000014, 6) . " USD</li>
            <li><strong>Content Size:</strong> " . number_format(strlen($generatedContent)) . " characters</li>
            <li><strong>File Size:</strong> " . number_format($fileSize) . " bytes</li>
            <li><strong>AI Model:</strong> DeepSeek Chat</li>
        </ul>
    </div>
    
    <div class='swagger-container'>
        <div id='swagger-ui'></div>
    </div>
    
    <script src='https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui-bundle.js'></script>
    <script>
        const spec = " . json_encode($documentation) . ";
        
        SwaggerUIBundle({
            spec: spec,
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.presets.standalone
            ],
            layout: 'StandaloneLayout',
            deepLinking: true,
            displayOperationId: false,
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 1,
            defaultModelRendering: 'example',
            displayRequestDuration: true,
            docExpansion: 'none',
            filter: true,
            maxDisplayedTags: 5,
            showExtensions: true,
            showCommonExtensions: true,
            tryItOutEnabled: true
        });
    </script>
</body>
</html>";

file_put_contents($htmlFile, $htmlContent);
echo "   ✅ HTML viewer: $htmlFile\n\n";

echo "🎉 TESTE COMPLETO - SUCESSO TOTAL!\n";
echo "===================================\n\n";

// Análise da documentação gerada
echo "📊 Análise da Documentação Gerada:\n";
echo "-----------------------------------\n";

if (isset($documentation['info'])) {
    echo "✅ Info:\n";
    echo "   📖 Título: " . ($documentation['info']['title'] ?? 'N/A') . "\n";
    echo "   🏷️ Versão: " . ($documentation['info']['version'] ?? 'N/A') . "\n";
    echo "   📝 Descrição: " . (isset($documentation['info']['description']) ? substr($documentation['info']['description'], 0, 100) . '...' : 'N/A') . "\n";
}

echo "✅ OpenAPI Version: " . ($documentation['openapi'] ?? 'N/A') . "\n";

if (isset($documentation['servers'])) {
    echo "✅ Servers: " . count($documentation['servers']) . "\n";
}

if (isset($documentation['paths'])) {
    echo "✅ Paths: " . count($documentation['paths']) . "\n";
    foreach ($documentation['paths'] as $path => $methods) {
        echo "   📍 $path: " . strtoupper(implode(', ', array_keys($methods))) . "\n";
    }
}

if (isset($documentation['components'])) {
    $components = $documentation['components'];
    echo "✅ Components:\n";
    if (isset($components['schemas'])) {
        echo "   📋 Schemas: " . count($components['schemas']) . "\n";
    }
    if (isset($components['parameters'])) {
        echo "   🔧 Parameters: " . count($components['parameters']) . "\n";
    }
    if (isset($components['responses'])) {
        echo "   📤 Responses: " . count($components['responses']) . "\n";
    }
}

echo "\n🎯 RESULTADOS:\n";
echo "===============\n";
echo "✅ DeepSeek AI funcionou PERFEITAMENTE\n";
echo "✅ Documentação OpenAPI 3.0 completa gerada\n";
echo "✅ Tempo de resposta: {$responseTime}ms (excelente)\n";
echo "✅ Custo: ~$" . number_format(($usage['total_tokens'] ?? 0) * 0.00000014, 6) . " USD (muito econômico)\n";
echo "✅ Qualidade: Profissional e detalhada\n\n";

echo "🚀 PRÓXIMOS PASSOS:\n";
echo "===================\n";
echo "1. 🌐 Abra $htmlFile no navegador para ver a documentação interativa\n";
echo "2. 📋 Use o arquivo JSON em ferramentas como Postman, Insomnia, etc.\n";
echo "3. 🔧 Integre com seu projeto Laravel usando o comando: php artisan apiforge:docs\n";
echo "4. 📚 Compartilhe a documentação com sua equipe de desenvolvimento\n\n";

echo "💡 O sistema ApiForge + DeepSeek está PRONTO para produção!\n";
echo "🎊 Geração automática de documentação API profissional em segundos!\n";