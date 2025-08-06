# ApiForge Example Application

Este é um projeto de exemplo completo demonstrando como usar o **ApiForge** em uma aplicação Laravel real.

## 📋 O que este exemplo inclui

- **Múltiplos modelos**: User, Product, Order, Category
- **Diferentes tipos de filtros**: Texto, números, datas, enums, relacionamentos
- **Casos de uso reais**: E-commerce, sistema de usuários, pedidos
- **Exemplos de performance**: Field selection, caching, paginação otimizada
- **Documentação automática**: Endpoints de metadata e examples

## 🚀 Como usar

### 1. Instalação

```bash
# Clone o projeto principal
git clone https://github.com/MarcosBrendonDePaula/ApiForge.git
cd ApiForge/example-app

# Instale as dependências
composer install

# Configure o ambiente
cp .env.example .env
php artisan key:generate

# Configure o banco de dados no .env
# DB_DATABASE=apiforge_example
# DB_USERNAME=root
# DB_PASSWORD=

# Execute as migrations e seeders
php artisan migrate:fresh --seed
```

### 2. Teste os endpoints

```bash
# Inicie o servidor
php artisan serve

# Teste os endpoints
curl "http://localhost:8000/api/users?name=John*&fields=id,name,email"
curl "http://localhost:8000/api/products?price=>=100&category.name=Electronics"
curl "http://localhost:8000/api/orders?status=completed&created_at=>=2024-01-01"
```

## 🎯 Exemplos de endpoints

### 📊 Users API

```bash
# Filtros básicos
GET /api/users?name=John&email=*@gmail.com

# Filtros avançados
GET /api/users?name=John*&created_at=>=2024-01-01&active=true

# Field selection
GET /api/users?fields=id,name,email,profile.bio

# Paginação
GET /api/users?page=2&per_page=20&sort_by=created_at

# Busca geral
GET /api/users?search=developer

# Metadata e exemplos
GET /api/users/metadata
GET /api/users/examples
```

### 🛍️ Products API

```bash
# Filtros de e-commerce
GET /api/products?price=50|200&in_stock=true

# Filtros por categoria
GET /api/products?category.name=Electronics&brand=Apple

# Ordenação e paginação
GET /api/products?sort_by=price&sort_direction=asc&per_page=12

# Field selection otimizada
GET /api/products?fields=id,name,price,category.name,images.url
```

### 📦 Orders API

```bash
# Filtros por status e data
GET /api/orders?status=completed,shipped&created_at=2024-01-01|2024-12-31

# Filtros por valor
GET /api/orders?total=>=500&payment_status=paid

# Relacionamentos
GET /api/orders?user.name=John*&fields=id,total,user.name,items.product.name
```

### 🏷️ Categories API

```bash
# Hierarquia de categorias
GET /api/categories?parent_id=null&active=true

# Contagem de produtos
GET /api/categories?products_count=>=10

# Field selection com relacionamentos
GET /api/categories?fields=id,name,parent.name,products_count
```

## 🔧 Estrutura do projeto

```
example-app/
├── app/
│   ├── Models/
│   │   ├── User.php              # Modelo com perfil e relacionamentos
│   │   ├── Product.php           # E-commerce com preços e categorias
│   │   ├── Order.php             # Pedidos com itens e status
│   │   └── Category.php          # Categorias hierárquicas
│   └── Http/Controllers/Api/
│       ├── UserController.php    # Exemplo completo com trait
│       ├── ProductController.php # E-commerce com filtros complexos
│       ├── OrderController.php   # Relacionamentos e aggregations
│       └── CategoryController.php # Hierarquia e contadores
├── database/
│   ├── migrations/               # Migrations completas
│   └── seeders/                  # Dados de exemplo realistas
├── routes/
│   └── api.php                   # Rotas organizadas com middleware
└── config/
    └── apiforge.php              # Configuração customizada
```

## 💡 Casos de uso demonstrados

### 1. **E-commerce Básico** (`ProductController`)
- Filtros por preço, marca, categoria
- Busca por nome/descrição
- Ordenação por preço, popularidade, data
- Field selection para performance

### 2. **Sistema de Usuários** (`UserController`) 
- Filtros por role, status, data de cadastro
- Busca por nome, email
- Relacionamentos com perfil
- Caching para consultas frequentes

### 3. **Gestão de Pedidos** (`OrderController`)
- Filtros por status, valor, data
- Relacionamentos complexos (user, items, products)
- Aggregations (total, quantidade)
- Relatórios com field selection

### 4. **Categorias Hierárquicas** (`CategoryController`)
- Filtros por parent/child
- Contagem de produtos
- Árvore de categorias
- Performance com eager loading

## 🚀 Features demonstradas

- ✅ **15+ operadores** de filtro
- ✅ **Field selection** otimizada
- ✅ **Relacionamentos** complexos
- ✅ **Paginação** inteligente
- ✅ **Caching** de consultas
- ✅ **Validação** automática
- ✅ **Documentação** automática
- ✅ **Performance** otimizada

## 📚 Aprendizado

Este exemplo te ensina:

1. **Como configurar** filtros para diferentes tipos de dados
2. **Como otimizar** consultas com field selection
3. **Como trabalhar** com relacionamentos complexos
4. **Como implementar** caching inteligente
5. **Como criar** APIs escaláveis e performáticas

## 🎯 Próximos passos

Após explorar este exemplo:

1. Adapte os modelos para seu domínio
2. Configure filtros específicos do seu negócio
3. Otimize consultas com field selection
4. Implemente caching onde necessário
5. Use a documentação automática para sua equipe

---

📖 **Documentação completa**: [ApiForge README](../README.md)  
🐛 **Issues**: [GitHub Issues](https://github.com/MarcosBrendonDePaula/ApiForge/issues)  
📦 **Packagist**: [marcosbrendon/apiforge](https://packagist.org/packages/marcosbrendon/apiforge)