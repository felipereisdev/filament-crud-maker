# Contribuindo para o FilamentCrudGenerator

Agradecemos seu interesse em contribuir com o pacote FilamentCrudGenerator! Este documento fornece algumas diretrizes para ajudar no processo de contribuição.

## Reportando bugs

Se você encontrou um bug, por favor crie uma issue no repositório GitHub com as seguintes informações:

- Título claro e descritivo
- Passos detalhados para reproduzir o bug
- Comportamento esperado versus comportamento atual
- Screenshots, se aplicável
- Versões do PHP, Laravel e Filament em uso
- Qualquer outra informação que possa ser útil

## Solicitando funcionalidades

Adoramos receber sugestões de novas funcionalidades! Para solicitar uma nova funcionalidade, por favor:

- Verifique se a funcionalidade já não foi solicitada nas issues abertas
- Abra uma nova issue com um título claro
- Descreva a funcionalidade em detalhes, incluindo casos de uso
- Explique por que a funcionalidade seria útil para a maioria dos usuários

## Processo de pull request

1. Faça um fork do repositório
2. Clone seu fork localmente
3. Crie uma branch para suas alterações: `git checkout -b feature/nome-da-funcionalidade` ou `fix/nome-do-bug`
4. Faça suas alterações
5. Execute os testes, se disponíveis
6. Formate o código usando PHP CS Fixer: `composer cs-fix`
7. Commit suas mudanças: `git commit -m 'Descrição clara da alteração'`
8. Push para sua branch: `git push origin feature/nome-da-funcionalidade`
9. Abra um pull request no repositório original

## Padrões de código

- Siga o PSR-12 para formatação de código
- Escreva comentários claros para classes e métodos
- Mantenha uma cobertura de testes adequada
- Use tipos escalares e tipos de retorno em PHP

## Desenvolvimento local

1. Clone o repositório
2. Instale as dependências: `composer install`
3. Crie uma aplicação Laravel de teste para testar suas alterações

## Testes

Antes de enviar suas alterações, certifique-se de executar todos os testes:

```bash
composer test
```

## Licença

Ao contribuir para este projeto, você concorda que suas contribuições serão licenciadas sob a mesma licença MIT que cobre o projeto.

Obrigado pela sua contribuição! 