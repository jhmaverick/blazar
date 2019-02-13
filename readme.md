# Blazar Framework
Aio Server é um framework com os principais recursos para desenvolver uma aplicação web, ele possui bibliotecas para gerenciamento de Páginas, APIs, Conexão com Banco de Dados, Imagens e diversos outros recursos úteis.

## Documentação
https://github.com/jhmaverick/blazar/wiki

## Instalação
`composer require jhmaverick/blazar`

### Direcionar requisições para o index
Para que o mapeamento da URL funcione é necessario redirecionar as requisições para o arquivo de inicialização do framework.  
Caso deseje que o framework controle as mídias, é necessario que uma regra seja adicionada com as condições.
 
```apacheconfig
RewriteEngine On

# Diretório de Mídias
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} -f
# "midias/" força todas as requisições com este parámetro sejam redirecionadas para o index 
# onde um "map" com o mesmo nome poderá ser usado para manipular as mídias da regra. 
RewriteRule ^(midias/).+(\.png$|\.jpg)$ index.php/$1 [L]

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

## Outra configurações
### Alteração de ambiente
Variaveis de ambiente podem ser setadas em um arquivo ".env" para mudar o comportamento do sistema.  
O arquivo deve estar localizado junto ao arquivo de inicialização ou no diretório anterior a ele.  
AVISO: O uso de arquivos ".env" não é recomendado em produção.

```dotenv
# O ambiente onde a aplicação esta rodando.
# Se essa variavel não for setada o ambiente padrão é a produção.
# 1 = produção, 2 = teste e 3 = desenvolvimento
ENVIRONMENT_TYPE = 3

# Um manifesto que mescla com o principal substituindo as que entrarem em conflito
# A variavel "ENVIRONMENT_TYPE" deve estar setada para desenvolvimento(3) ou teste(2). Esta configuração não é permitida em produção.
CUSTOM_MANIFEST = "mf_develop.json"
```

### Bloqueio de arquivos do sistema
É importante acrescentar o bloco a baixo ao ".htaccess" para impedir o acesso externo aos arquivos do sistema.

```apacheconfig
<Files ~ "(.htaccess|manifest.json|mf_develop.json|composer.json|composer.lock|.env|.log.html)">
    Order allow,deny
    Deny from all
</Files>
```

## TODO
* Mover os textos dos logs e outras mensagens para um arquivo de texto chamado "alerts.json"