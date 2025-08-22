# 🤖 AI-Powered Documentation Generator

O ApiForge agora inclui um poderoso gerador de documentação OpenAPI 3.0 que usa **inteligência artificial** para criar documentações profissionais e abrangentes automaticamente.

## ✨ Recursos Principais

- **🧠 Integração com múltiplos LLMs**: OpenAI, Claude (Anthropic), DeepSeek
- **📋 OpenAPI 3.0 Completo**: Especificações profissionais com todos os detalhes
- **🎯 Detecção Automática**: Encontra todos os controladores com `HasAdvancedFilters`
- **💾 Múltiplos Formatos**: JSON, YAML, HTML
- **⚡ Cache Inteligente**: Evita reprocessamento desnecessário
- **🔧 Configuração Flexível**: Personalização total via configuração

## 🚀 Instalação e Configuração

### 1. Configuração das APIs de LLM

Adicione as chaves de API no seu `.env`:

```bash
# OpenAI (recomendado para performance)
OPENAI_API_KEY=sk-your-openai-key-here
APIFORGE_OPENAI_ENABLED=true
APIFORGE_OPENAI_MODEL=gpt-4o

# Claude (excelente qualidade)
CLAUDE_API_KEY=your-claude-key-here
APIFORGE_CLAUDE_ENABLED=true
APIFORGE_CLAUDE_MODEL=claude-3-sonnet-20240229

# DeepSeek (econômico)
DEEPSEEK_API_KEY=your-deepseek-key-here
APIFORGE_DEEPSEEK_ENABLED=true
APIFORGE_DEEPSEEK_MODEL=deepseek-chat

# Configurações opcionais
API_CONTACT_NAME="Sua Equipe de API"
API_CONTACT_EMAIL="api@suaempresa.com"
API_CONTACT_URL="https://suaempresa.com/support"
```

### 2. Publicar Configuração

```bash
php artisan vendor:publish --provider="MarcosBrendon\ApiForge\ApiForgeServiceProvider" --tag="config"
```

## 📖 Como Usar

### Comando Principal

```bash
# Modo interativo - escolhe o controlador
php artisan apiforge:docs

# Controlador específico
php artisan apiforge:docs "App\Http\Controllers\Api\UserController"

# Todos os controladores com ApiForge
php artisan apiforge:docs --all

# Com opções avançadas
php artisan apiforge:docs --all --format=yaml --output=/caminho/customizado --force
```

### Opções Disponíveis

| Opção | Descrição | Exemplo |
|-------|-----------|---------|
| `controller` | Classe específica do controlador | `UserController` |
| `--output` | Diretório de saída | `--output=storage/docs` |
| `--format` | Formato (json/yaml/html) | `--format=yaml` |
| `--endpoint` | Endpoint específico | `--endpoint=/api/users` |
| `--force` | Sobrescrever documentação existente | `--force` |
| `--cache-clear` | Limpar cache antes de gerar | `--cache-clear` |
| `--all` | Processar todos os controladores | `--all` |
| `--scan-path` | Caminho para escanear controladores | `--scan-path=app/Controllers` |

### Exemplos Práticos

```bash
# Gerar docs para UserController em YAML
php artisan apiforge:docs "App\Http\Controllers\Api\UserController" --format=yaml

# Forçar regeneração de todas as docs
php artisan apiforge:docs --all --force --cache-clear

# Gerar em diretório customizado
php artisan apiforge:docs --all --output=public/api-docs --format=html
```

## 🧠 Como Funciona a IA

### 1. Extração de Metadados

O sistema extrai informações completas do seu controlador:

- **Configuração de filtros** (tipos, operadores, validações)
- **Relacionamentos** disponíveis
- **Configuração de paginação**
- **Field selection** permitida
- **Exemplos** automáticos baseados nos filtros
- **Validações** existentes

### 2. Contexto Inteligente

Monta um contexto rico para a IA:

```php
[
    'project_info' => [
        'name' => 'Minha API',
        'framework' => 'Laravel 11.x',
        'package' => 'ApiForge'
    ],
    'endpoint_metadata' => [
        'filters' => [...], // Todos os filtros configurados
        'examples' => [...], // Exemplos gerados
        'relationships' => [...], // Relacionamentos
        'validation' => [...] // Regras de validação
    ]
]
```

### 3. Prompt Especializado

Envia um prompt otimizado para documentação técnica:

- Instruções específicas para OpenAPI 3.0
- Exemplos detalhados de cada filtro
- Documentação de errors e respostas
- Esquemas de validação
- Melhores práticas de API

### 4. Pós-Processamento

- Valida o JSON gerado
- Mescla com esquemas base
- Adiciona componentes reutilizáveis
- Cache inteligente para performance

## 🎯 Exemplo de Saída

### JSON OpenAPI 3.0 Gerado

```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "User API",
    "version": "1.0.0",
    "description": "Advanced API with filtering, pagination, and field selection capabilities"
  },
  "paths": {
    "/api/users": {
      "get": {
        "summary": "List users with advanced filtering",
        "description": "Retrieve a paginated list of users with powerful filtering capabilities",
        "parameters": [
          {
            "name": "name",
            "in": "query",
            "description": "Filter by user name (supports wildcards with *)",
            "schema": { "type": "string" },
            "examples": {
              "exact": { "value": "John Doe" },
              "wildcard": { "value": "John*" },
              "contains": { "value": "*Doe*" }
            }
          },
          {
            "name": "age",
            "in": "query",
            "description": "Filter by age with comparison operators",
            "schema": { "type": "integer" },
            "examples": {
              "exact": { "value": "25" },
              "gte": { "value": ">=18" },
              "between": { "value": "18|65" }
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "$ref": "#/components/schemas/PaginatedUserResponse"
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "schemas": {
      "PaginatedUserResponse": {
        "type": "object",
        "properties": {
          "success": { "type": "boolean" },
          "data": {
            "type": "array",
            "items": { "$ref": "#/components/schemas/User" }
          },
          "pagination": {
            "$ref": "#/components/schemas/PaginationMeta"
          }
        }
      }
    }
  }
}
```

## ⚙️ Configuração Avançada

### Prioridade dos LLMs

O sistema tenta os provedores na ordem configurada:

```php
'llm' => [
    'priority' => ['claude', 'openai', 'deepseek'],
]
```

### Customização do Template

```php
'templates' => [
    'info' => [
        'contact' => [
            'name' => 'Minha Equipe',
            'email' => 'suporte@api.com',
        ],
        'license' => [
            'name' => 'MIT',
            'url' => 'https://opensource.org/licenses/MIT',
        ],
    ],
    'servers' => [
        [
            'url' => 'https://api.exemplo.com',
            'description' => 'Servidor de Produção',
        ],
        [
            'url' => 'https://staging-api.exemplo.com', 
            'description' => 'Servidor de Staging',
        ]
    ],
]
```

### Controle de Cache

```php
'cache' => [
    'enabled' => true,
    'ttl' => 7200, // 2 horas
    'key_prefix' => 'minha_api_docs_',
]
```

## 🔄 Integração com CI/CD

### GitHub Actions

```yaml
name: Generate API Documentation

on:
  push:
    branches: [ main ]
    paths: [ 'app/Http/Controllers/**' ]

jobs:
  docs:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Generate Documentation
      env:
        OPENAI_API_KEY: ${{ secrets.OPENAI_API_KEY }}
        APIFORGE_OPENAI_ENABLED: true
      run: |
        php artisan apiforge:docs --all --format=json --output=public/docs
        
    - name: Deploy to GitHub Pages
      uses: peaceiris/actions-gh-pages@v3
      with:
        github_token: ${{ secrets.GITHUB_TOKEN }}
        publish_dir: ./public/docs
```

### Laravel Forge

```bash
# Script de deploy
cd $FORGE_SITE_PATH

# Gerar documentação após deploy
php artisan apiforge:docs --all --cache-clear --output=public/api-docs

# Tornar acessível via web
chmod -R 755 public/api-docs
```

## 📊 Exemplo Completo

### 1. Controlador

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class UserController extends Controller
{
    use HasAdvancedFilters;

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function setupFilterConfiguration(): void
    {
        $this->configureFilters([
            'name' => [
                'type' => 'string',
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'sortable' => true,
                'description' => 'Nome completo do usuário'
            ],
            'email' => [
                'type' => 'string', 
                'operators' => ['eq', 'like'],
                'searchable' => true,
                'description' => 'Email do usuário'
            ],
            'age' => [
                'type' => 'integer',
                'operators' => ['eq', 'gte', 'lte', 'between'],
                'description' => 'Idade do usuário'
            ],
            'status' => [
                'type' => 'enum',
                'values' => ['active', 'inactive', 'pending'],
                'operators' => ['eq', 'in'],
                'description' => 'Status da conta'
            ]
        ]);
    }

    public function index(Request $request)
    {
        return $this->indexWithFilters($request);
    }
}
```

### 2. Comando

```bash
php artisan apiforge:docs "App\Http\Controllers\Api\UserController" --format=json
```

### 3. Resultado

```
🚀 ApiForge Documentation Generator
===================================
📁 Created output directory: /var/www/storage/app/docs
📝 Generating documentation for: App\Http\Controllers\Api\UserController
🔗 Endpoint: /api/users
⚙️  Processing App\Http\Controllers\Api\UserController...
🤖 Generating enhanced documentation with LLM...
🤖 Processing with AI... ✅
💾 Saved: apiforge-user.json

✅ Documentation generation completed!
📂 Output location: /var/www/storage/app/docs
```

### 4. Documentação Gerada

Arquivo `apiforge-user.json` com especificação OpenAPI 3.0 completa, incluindo:

- ✅ Todos os filtros disponíveis com exemplos
- ✅ Parâmetros de paginação
- ✅ Field selection
- ✅ Respostas de erro detalhadas
- ✅ Esquemas de validação
- ✅ Exemplos práticos de uso
- ✅ Descrições profissionais geradas por IA

## 🎨 Visualização

### Swagger UI

Você pode usar a documentação gerada com Swagger UI:

```html
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui.css" />
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.0.0/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: './apiforge-user.json',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.presets.standalone
            ]
        });
    </script>
</body>
</html>
```

## 🚨 Solução de Problemas

### Problemas Comuns

#### 1. "No LLM provider available"

```bash
# Verificar configuração
php artisan config:cache

# Verificar variáveis de ambiente
php artisan tinker
>>> config('apiforge.documentation.llm.providers.openai.enabled')
>>> env('OPENAI_API_KEY')
```

#### 2. "Invalid JSON response from LLM"

- Tente outro provedor: `--force --cache-clear`
- Verifique cotas da API
- Reduza a complexidade dos metadados

#### 3. Cache não está limpando

```bash
php artisan cache:clear
php artisan apiforge:docs --cache-clear
```

### Debug Mode

Ative logs detalhados:

```php
'debug' => [
    'enabled' => true,
    'log_queries' => true,
]
```

## 💡 Dicas e Melhores Práticas

### 1. Performance

- **Use cache**: Para desenvolvimento, mantenha cache ativo
- **Priorize provedores**: Claude tem melhor qualidade, OpenAI é mais rápido
- **Gere em batch**: Use `--all` para processar todos de uma vez

### 2. Qualidade

- **Descrições detalhadas**: Adicione `description` nos filtros
- **Exemplos customizados**: Configure `example` nos filtros
- **Validações**: Implemente métodos de validação nos controladores

### 3. Integração

- **Versionamento**: Commit as docs geradas no Git
- **Automação**: Configure CI/CD para gerar automaticamente
- **Distribuição**: Publique em GitHub Pages ou servidor web

---

## 🌟 Próximos Passos

1. **Configure** suas chaves de API
2. **Execute** o comando em um controlador
3. **Visualize** a documentação gerada
4. **Integre** com seu workflow de desenvolvimento
5. **Compartilhe** a documentação com sua equipe

O gerador de documentação do ApiForge transforma seus controladores em documentações profissionais automaticamente, economizando horas de trabalho manual e garantindo sempre documentações atualizadas! 🚀