# Guia de Testes - ApiForge

Este guia mostra como testar a biblioteca ApiForge em diferentes cenários.

## 🧪 Testes Automatizados

### Executar todos os testes
```bash
composer test
```

### Executar testes específicos
```bash
# Apenas testes de Virtual Fields
composer test -- --filter="VirtualField"

# Apenas testes de Model Hooks
composer test -- --filter="ModelHook"

# Testes de integração
composer test -- --filter="Integration"
```

### Executar com cobertura
```bash
composer test-coverage
```

## 🚀 Teste Manual Rápido

Execute o teste simples para verificar se tudo está funcionando:

```bash
php test-simple.php
```

Este teste verifica:
- ✅ Classes principais carregadas
- ✅ Validação de virtual fields
- ✅ Validação de model hooks
- ✅ Estrutura do projeto
- ✅ Exemplos disponíveis

## 🏗️ Teste em Aplicação Laravel

### 1. Criar Nova Aplicação Laravel

```bash
composer create-project laravel/laravel apiforge-test
cd apiforge-test
```

### 2. Instalar ApiForge Localmente

Adicione ao `composer.json` da aplicação de teste:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-advanced-api-filters"
        }
    ],
    "require": {
        "marcosbrendon/apiforge": "*"
    }
}
```

```bash
composer install
```

### 3. Publicar Configuração

```bash
php artisan vendor:publish --provider="MarcosBrendon\ApiForge\ApiForgeServiceProvider"
```

### 4. Criar Modelos de Teste

```bash
php artisan make:model User -m
php artisan make:model Order -m
php artisan make:model Product -m
```

#### Migration para User:
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->boolean('active')->default(true);
    $table->enum('role', ['admin', 'user', 'moderator'])->default('user');
    $table->date('birth_date')->nullable();
    $table->timestamps();
});
```

#### Migration para Order:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('order_number');
    $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled']);
    $table->decimal('total', 10, 2);
    $table->timestamp('processed_at')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();
});
```

### 5. Configurar Relacionamentos

#### User Model:
```php
class User extends Authenticatable
{
    use HasAdvancedFilters;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'active', 'role', 'birth_date'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
```

#### Order Model:
```php
class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'status', 'total', 'processed_at', 'shipped_at', 'delivered_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### 6. Criar Controller de Teste

```bash
php artisan make:controller Api/UserController
```

Copie o conteúdo do `examples/UserController.php` para o controller criado.

### 7. Configurar Rotas

```php
// routes/api.php
Route::apiResource('users', App\Http\Controllers\Api\UserController::class);
Route::get('users/{user}/analytics', [App\Http\Controllers\Api\UserController::class, 'customerAnalytics']);
Route::get('users/premium-customers', [App\Http\Controllers\Api\UserController::class, 'premiumCustomers']);
Route::get('users/risk-assessment', [App\Http\Controllers\Api\UserController::class, 'riskAssessment']);
```

### 8. Executar Migrações e Seeders

```bash
php artisan migrate
```

#### Criar Seeder:
```bash
php artisan make:seeder UserSeeder
```

```php
// database/seeders/UserSeeder.php
public function run()
{
    $users = [
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'active' => true,
            'role' => 'user',
            'birth_date' => '1990-01-15'
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'active' => true,
            'role' => 'admin',
            'birth_date' => '1985-06-20'
        ],
        [
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'active' => false,
            'role' => 'user',
            'birth_date' => '1992-12-10'
        ]
    ];

    foreach ($users as $userData) {
        $user = User::create($userData);
        
        // Criar alguns pedidos para teste
        for ($i = 1; $i <= rand(1, 5); $i++) {
            Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . date('Y') . '-' . str_pad($user->id * 100 + $i, 6, '0', STR_PAD_LEFT),
                'status' => ['pending', 'processing', 'shipped', 'delivered'][rand(0, 3)],
                'total' => rand(50, 1000),
                'processed_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                'shipped_at' => rand(0, 1) ? now()->subDays(rand(1, 20)) : null,
                'delivered_at' => rand(0, 1) ? now()->subDays(rand(1, 10)) : null,
            ]);
        }
    }
}
```

```bash
php artisan db:seed --class=UserSeeder
```

### 9. Iniciar Servidor

```bash
php artisan serve
```

## 🔍 Testes de API

### Testes Básicos

```bash
# Listar usuários
curl "http://localhost:8000/api/users"

# Filtrar por nome
curl "http://localhost:8000/api/users?first_name=John"

# Selecionar campos específicos
curl "http://localhost:8000/api/users?fields=id,first_name,last_name,email"
```

### Testes de Virtual Fields

```bash
# Testar virtual field 'full_name'
curl "http://localhost:8000/api/users?fields=id,full_name"

# Filtrar por virtual field
curl "http://localhost:8000/api/users?full_name=John*"

# Testar virtual field 'age'
curl "http://localhost:8000/api/users?fields=id,first_name,age&age=>=30"

# Testar virtual field 'customer_tier'
curl "http://localhost:8000/api/users?fields=id,first_name,customer_tier,total_spent"

# Filtrar por customer tier
curl "http://localhost:8000/api/users?customer_tier=gold,platinum"
```

### Testes de Model Hooks

```bash
# Criar usuário (testará hooks beforeStore e afterStore)
curl -X POST "http://localhost:8000/api/users" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "role": "user",
    "active": true
  }'

# Atualizar usuário (testará hooks beforeUpdate e afterUpdate)
curl -X PUT "http://localhost:8000/api/users/1" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Updated",
    "role": "admin"
  }'
```

### Testes de Performance

```bash
# Testar com muitos campos virtuais
curl "http://localhost:8000/api/users?fields=id,full_name,age,customer_tier,total_spent,order_count,profile_completion"

# Testar paginação
curl "http://localhost:8000/api/users?per_page=5&page=1"

# Testar ordenação por virtual field
curl "http://localhost:8000/api/users?sort_by=customer_tier&sort_direction=desc"
```

### Testes de Endpoints Customizados

```bash
# Analytics de cliente
curl "http://localhost:8000/api/users/1/analytics"

# Clientes premium
curl "http://localhost:8000/api/users/premium-customers"

# Avaliação de risco
curl "http://localhost:8000/api/users/risk-assessment"
```

## 📊 Verificação de Resultados

### Estrutura de Resposta Esperada

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "full_name": "John Doe",
      "age": 34,
      "customer_tier": "silver",
      "total_spent": 1250.00,
      "order_count": 3
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 3,
    "last_page": 1
  },
  "filters": {
    "active": {},
    "sorting": {
      "sort_by": "created_at",
      "sort_direction": "desc"
    }
  }
}
```

### Logs para Verificar Hooks

Verifique os logs do Laravel para confirmar que os hooks estão sendo executados:

```bash
tail -f storage/logs/laravel.log
```

## 🐛 Troubleshooting

### Problemas Comuns

1. **Erro de classe não encontrada**
   ```bash
   composer dump-autoload
   ```

2. **Virtual fields não aparecem**
   - Verifique se o trait `HasAdvancedFilters` está sendo usado
   - Confirme se `setupFilterConfiguration()` está implementado

3. **Hooks não executam**
   - Verifique se os hooks estão registrados corretamente
   - Confirme se o service provider está carregado

4. **Erro de cache**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

### Debug Mode

Ative o debug no arquivo `.env`:
```
APP_DEBUG=true
LOG_LEVEL=debug
```

## ✅ Checklist de Testes

- [ ] Instalação do pacote
- [ ] Publicação da configuração
- [ ] Criação de modelos e migrações
- [ ] Configuração de virtual fields
- [ ] Configuração de model hooks
- [ ] Testes de API básicos
- [ ] Testes de virtual fields
- [ ] Testes de model hooks
- [ ] Testes de performance
- [ ] Verificação de logs
- [ ] Testes de erro e validação

## 🎯 Próximos Passos

1. **Testes de Carga**: Use ferramentas como Apache Bench ou Postman para testar performance
2. **Testes de Integração**: Teste com diferentes versões do Laravel
3. **Testes de Compatibilidade**: Teste com diferentes versões do PHP
4. **Documentação**: Documente casos de uso específicos encontrados
5. **Otimização**: Identifique e otimize gargalos de performance

---

**Nota**: Este guia assume que você está testando localmente. Para testes em produção, considere usar ambientes de staging e ferramentas de monitoramento adequadas.