# Blazar Framework
Aio Server é um framework com os principais recursos para desenvolver uma aplicação web, ele possui bibliotecas para gerenciamento de Páginas, APIs, Conexão com Banco de Dados, Imagens e diversos outros recursos úteis.

## Documentação
https://github.com/jhmaverick/blazar/wiki

## Instalação
`composer require jhmaverick/blazar`

### Direcionar requisições para o index
```apacheconf
RewriteEngine On

# Aplicação
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ index.php/$1 [L]
```

### Iniciando aplicação
```php
// Auto Load para dependencias do composer
require_once "vendor/autoload.php";

// Aplicação
\Blazar\Application::init();
```
