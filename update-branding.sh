#!/bin/bash

echo "🔄 Updating ApiForge branding..."

# Lista de arquivos para atualizar
files=(
    "src/Services/ApiFilterService.php"
    "src/Services/FilterConfigService.php"
    "src/Http/Controllers/BaseApiController.php"
    "src/Http/Resources/PaginatedResource.php"
    "src/Http/Middleware/ApiPaginationMiddleware.php"
    "src/Traits/HasAdvancedFilters.php"
    "examples/UserController.php"
    "examples/UserWithTraitController.php"
    "examples/routes.php"
    "docs/INSTALLATION.md"
    "docs/QUICK_START.md"
    "CHANGELOG.md"
    "CONTRIBUTING.md"
)

# Substituir referências de configuração
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "Updating config references in $file"
        sed -i 's/advanced-api-filters/apiforge/g' "$file"
    fi
done

# Atualizar namespaces
echo "Updating namespaces..."

# Atualizar todos os arquivos src/
find src/ -name "*.php" -type f -exec sed -i 's/MarcosBrendon\\LaravelAdvancedApiFilters/MarcosBrendon\\ApiForge/g' {} \;

# Atualizar exemplos
find examples/ -name "*.php" -type f -exec sed -i 's/MarcosBrendon\\LaravelAdvancedApiFilters/MarcosBrendon\\ApiForge/g' {} \;

# Atualizar testes
find tests/ -name "*.php" -type f -exec sed -i 's/MarcosBrendon\\LaravelAdvancedApiFilters/MarcosBrendon\\ApiForge/g' {} \;

# Atualizar README
sed -i 's/laravel-advanced-api-filters/apiforge/g' README.md

echo "✅ Branding update completed!"