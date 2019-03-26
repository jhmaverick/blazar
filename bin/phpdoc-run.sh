#!/usr/bin/env bash

cd ..

rm -rf build/api-cache
rm -rf docs/phpdoc

# O parâmetro cache folder é necessário para que o phpdoc não misturar códigos de outros projetos na documentação
phpdoc run --config --cache-folder=build/api-cache/phpdoc-cache

rm -rf build/api-cache