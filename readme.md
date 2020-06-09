# Blazar

* Mapa de classes pela URL
* Gerenciador de imagens
* Web Service
* Biblioteca para conexão com Banco de Dados
* Gestor de logs para aplicação

### Documentação

**Wiki:** https://github.com/jhmaverick/blazar/wiki \
**PHPDoc:** https://jhmaverick.github.io/blazar/

## Criar um projeto utilizando o Blazar

Requisitos:

* Git
* Composer

### Criar via blazar-cli

Instalar o Blazar CLI: `npm install -g blazar-cli`\
Criar o projeto: `blazar create [project-name]`


### Criar utilizando o git

`git clone https://github.com/jhmaverick/blazar-project.git [project-name] && cd [project-name] && rm -rf .git && composer install`


## Ler logs em ambiente de desenvolvimento

Instalar o Blazar CLI: `npm install -g blazar-cli`\
Executar Leitor de logs: `blazar console`


## Desenvolvimento do Blazar

Para iniciar os serviços de desenvolvimento, clone o repositório e na raiz do projeto execute: `bash bin/up`


## Versões

### 2.0

* Adiciona Docker para iniciar os serviços de desenvolvimento do projeto;
* Remove a classe de manipulação de Textos(Text) para dar espaço a utilização de bibliotecas como a i18n;
* Remove a classe "SelectorDOM";
* Arquivo de sobrescrita "custom-manifest.json" renomeado para "blazar-manifest.override.json".
