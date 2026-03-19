# Gerador de CRUD para Filament v4

Um pacote Laravel que gera rapidamente recursos CRUD completos para o Filament v4, economizando tempo de desenvolvimento.

## Instalação

Você pode instalar o pacote via composer:

```bash
composer require freis/filament-crud-generator
```

Opcionalmente, você pode publicar o arquivo de configuração:

```bash
php artisan vendor:publish --tag="filament-crud-generator-config"
```

Isso publicará um arquivo em `config/filament-crud-generator.php`.

Você também pode publicar o arquivo de configuração do PHP CS Fixer:

```bash
php artisan vendor:publish --tag="php-cs-fixer-config"
```

## Utilização

Para gerar um CRUD completo, use o comando:

```bash
php artisan make:filament-crud NomeDoModelo --fields=campo1:tipo,campo2:tipo --relations=tipoRelacao:ModeloRelacionado:campo1:tipo,campo2:tipo --softDeletes
```

### Parâmetros disponíveis:

- `NomeDoModelo`: Nome do modelo a ser criado (no singular, com a primeira letra maiúscula).
- `--fields`: Lista de campos e seus tipos, separados por vírgula.
- `--relations`: Lista de relações e seus campos, no formato `tipoRelacao:ModeloRelacionado:campo1:tipo,campo2:tipo;tipoRelacao2:ModeloRelacionado2:campo1:tipo`.
- `--softDeletes`: Flag opcional para adicionar soft deletes ao modelo.
- `--no-migrate`: Flag opcional para pular a execução de migrações após a criação.
- `--no-format`: Flag opcional para pular a formatação do código usando PHP CS Fixer.
- `--clean-resources`: Limpar todos os recursos existentes.

### Exemplos:

1. Criando um modelo Produto com campos básicos:

```bash
php artisan make:filament-crud Produto --fields=nome:string:required:min=3,descricao:text,preco:decimal:required,ativo:boolean,imagem:image:nullable
```

2. Criando um modelo Produto com relação a categoria:

```bash
php artisan make:filament-crud Produto --fields=nome:string:required:min=3:max=100,descricao:text,preco:decimal:required,ativo:boolean --relations=belongsTo:Categoria
```

3. Criando um modelo com softDeletes:

```bash
php artisan make:filament-crud Artigo --fields=titulo:string:required:min=5,conteudo:html:required,data_publicacao:date:required --softDeletes
```

4. Criando um modelo com relações mais complexas:

```bash
php artisan make:filament-crud Curso --fields=nome:string:required:min=3:unique,descricao:markdown:required,preco:decimal:required:between=0,9999.99,duracao:integer:required,publicado:boolean:false --relations=belongsTo:Professor;belongsToMany:Aluno;hasMany:Aula
```

## Suporte

Se você encontrar algum problema ou tiver dúvidas, abra uma issue no repositório GitHub.

## Licença

Este pacote é open-source e está disponível sob a licença MIT. 