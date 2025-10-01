# Model Hooks Tests

Este documento descreve os testes implementados para as funcionalidades de Model Hooks no ApiForge.

## Testes Implementados

### 1. ModelHookServiceTest (Unit Tests)

Testa o serviço principal de hooks do modelo:

- ✅ **test_can_register_hook** - Verifica se hooks podem ser registrados
- ✅ **test_can_execute_hook** - Verifica se hooks são executados corretamente
- ✅ **test_hooks_execute_in_priority_order** - Verifica ordem de execução por prioridade
- ✅ **test_hook_with_stop_on_failure_stops_execution** - Verifica parada em falha
- ✅ **test_conditional_hook_execution** - Verifica execução condicional
- ✅ **test_conditional_hook_skips_when_condition_not_met** - Verifica pulo de condições
- ✅ **test_can_register_hooks_from_config** - Verifica registro via configuração
- ✅ **test_before_delete_hook_can_prevent_deletion** - Verifica prevenção de deleção
- ✅ **test_before_store_hook_can_modify_data** - Verifica modificação de dados
- ✅ **test_can_clear_hooks** - Verifica limpeza de hooks
- ✅ **test_can_get_hooks_metadata** - Verifica obtenção de metadados
- ✅ **test_hook_context_data_passing** - Verifica passagem de dados entre hooks
- ✅ **test_multiple_hooks_return_array_of_results** - Verifica retorno de múltiplos hooks
- ✅ **test_single_hook_returns_direct_result** - Verifica retorno de hook único

### 2. HasAdvancedFiltersHooksSimpleTest (Unit Tests)

Testa a integração dos hooks com o trait HasAdvancedFilters:

- ✅ **test_can_configure_model_hooks** - Verifica configuração de hooks
- ✅ **test_can_register_individual_hook** - Verifica registro individual
- ✅ **test_can_get_hook_service** - Verifica acesso ao serviço
- ✅ **test_can_clear_hooks** - Verifica limpeza de hooks
- ✅ **test_resolve_cache_key_placeholders** - Verifica resolução de placeholders
- ✅ **test_check_permission_without_auth_returns_false** - Verifica permissões sem auth
- ✅ **test_check_permission_with_callback** - Verifica permissões com callback
- ✅ **test_configure_slug_hooks_registers_hooks** - Verifica configuração de slugs
- ✅ **test_configure_permission_hooks_registers_hooks** - Verifica configuração de permissões
- ✅ **test_configure_validation_hooks_registers_hooks** - Verifica configuração de validação
- ✅ **test_configure_notification_hooks_registers_hooks** - Verifica configuração de notificações
- ✅ **test_resolve_notification_recipients_returns_array** - Verifica resolução de destinatários
- ✅ **test_hook_execution_flow** - Verifica fluxo de execução completo

## Funcionalidades Testadas

### ✅ Configuração de Hooks
- Registro manual de hooks
- Configuração via array
- Métodos de conveniência (audit, validation, notification, etc.)

### ✅ Execução de Hooks
- Ordem de prioridade
- Execução condicional
- Parada em falha
- Passagem de dados entre hooks

### ✅ Tipos de Hooks Suportados
- beforeStore / afterStore
- beforeUpdate / afterUpdate
- beforeDelete / afterDelete

### ✅ Funcionalidades Auxiliares
- Resolução de placeholders em cache keys
- Verificação de permissões
- Resolução de destinatários de notificação
- Geração de slugs únicos

## Cobertura de Testes

**Total de Testes:** 27 testes
**Status:** ✅ Todos passando
**Assertions:** 52 assertions

## Como Executar os Testes

```bash
# Executar apenas os testes de hooks
vendor/bin/phpunit tests/Unit/ModelHookServiceTest.php tests/Unit/HasAdvancedFiltersHooksSimpleTest.php

# Executar com detalhes
vendor/bin/phpunit tests/Unit/ModelHookServiceTest.php tests/Unit/HasAdvancedFiltersHooksSimpleTest.php --testdox

# Executar todos os testes do projeto
composer test
```

## Arquivos de Teste

- `tests/Unit/ModelHookServiceTest.php` - Testes unitários do serviço principal
- `tests/Unit/HasAdvancedFiltersHooksSimpleTest.php` - Testes de integração com o trait
- `tests/Fixtures/TestControllerWithHooks.php` - Controller de teste com hooks
- `tests/Fixtures/TestModel.php` - Modelo de teste

## Status dos Testes

✅ **Todos os testes estão funcionando perfeitamente!**

Os testes cobrem todas as funcionalidades principais dos Model Hooks de forma robusta e confiável.

## Próximos Passos

Os testes cobrem as funcionalidades principais dos Model Hooks. Para expandir a cobertura:

1. Adicionar testes de integração mais complexos
2. Testar cenários de erro específicos
3. Adicionar testes de performance
4. Testar integração com banco de dados real