# Guia de Migração para Pacote

Este documento explica como finalizar a transformação do comando em um pacote completo.

## O que já foi feito

1. ✅ Estrutura básica do pacote criada
2. ✅ Arquivo composer.json configurado
3. ✅ ServiceProvider criado
4. ✅ Arquivo de configuração criado
5. ✅ Arquivo README.md com instruções de uso
6. ✅ Arquivo CONTRIBUTING.md para colaboradores
7. ✅ Licença MIT adicionada
8. ✅ Estrutura de testes criada
9. ✅ Script de preparação do pacote (package-setup.sh)

## O que falta fazer

1. Execute o script para copiar e ajustar os arquivos de classe:
   ```bash
   ./package-setup.sh
   ```

2. Verifique se todos os arquivos foram copiados corretamente para `package/src/Commands/FilamentCrud/`:
   ```bash
   ls -la package/src/Commands/FilamentCrud/
   ```

3. Verifique e ajuste manualmente quaisquer referências de namespace que não tenham sido atualizadas automaticamente pelo script.

4. Crie uma pasta `package/src/Commands/stubs` e copie quaisquer arquivos de stub que seu comando use:
   ```bash
   mkdir -p package/src/Commands/stubs
   cp -r app/Console/Commands/stubs/* package/src/Commands/stubs/ # se existirem
   ```

5. Teste o pacote localmente antes de publicá-lo:
   - Crie um projeto Laravel de teste em uma pasta separada
   - Adicione o repositório local ao `composer.json` do projeto de teste:
     ```json
     "repositories": [
         {
             "type": "path",
             "url": "../path/to/package"
         }
     ]
     ```
   - Instale o pacote:
     ```bash
     composer require freis/filament-crud-generator
     ```

6. Adapte o namespace nas classes restantes conforme necessário.

7. Envie o código para um repositório GitHub:
   ```bash
   cd package
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin git@github.com:freis/filament-crud-generator.git
   git push -u origin main
   ```

8. Publique no Packagist para permitir a instalação via Composer:
   - Crie uma conta em packagist.org
   - Submeta o URL do repositório GitHub

## Arquivos que precisam ser copiados/adaptados

- CodeFormatter.php
- CrudGenerator.php
- FormComponentGenerator.php
- ImportManager.php
- MigrationManager.php
- ModelManager.php
- ResourceUpdater.php
- TableComponentGenerator.php

## Notas importantes

- Certifique-se de verificar todas as dependências das classes.
- Atualize o arquivo composer.json com seu nome/email reais.
- Teste todos os aspectos do pacote antes de publicá-lo.
- Considere adicionar mais testes para as diferentes funcionalidades.
- Documente adequadamente todas as opções e recursos do pacote.