#!/bin/bash

# Gera uma variável no padrão ".env" e trata os valores
#
# $1 - Nome da variável.
# $2 - Valor da variável.
# $3 - (Opcional) Arquivo para salvar. Se não for informado os dados serão retornados com um echo.
# $4 - (Opcional) Reescreve a variável no arquivo se ela já existir.
env_add() {
    name="$1"
    valor="$2"
    file="$3"

    if [[ "$file" ]]; then
        if [[ ! "$(env_get_var "$file" "$name")" ]]; then
            # Salva apenas se ela não existir no arquivo
            [[ ! -d "$(dirname "$file")" ]] && mkdir -p "$(dirname "$file")"

            # Escapa caracteres que devem ser mantidos para não ser necessário usar aspas
            valor="$(echo "$valor" | sed "s|[\`#$&()\|;:\"'<> ]|\\\&|g")"

            echo -en "${name}=${valor}\n" >> "$file"
        elif [[ "${4,,}" == "y" ]] && [[ "$(env_get_var "$file" "$name")" ]]; then
            # Atualiza valor da variável do arquivo
            if [[ "$(grep -E "^$name=\".*\"" "$file")" ]]; then
                # Mantem valores entre aspas duplas

                # Escapa as barras invertidas e as aspas duplas
                valor=${valor//\\/\\\\}
                valor=${valor//\"/\\\"}
                # Escape de Regex para o sed
                valor="$(echo "$valor" | sed "s|[\`~!@#$%^&*()_=+{}\|;:\"',<.>/?-]|\\\&|g")"

                sed -Ei "s|(^$name=).*|\1\"${valor}\"|" "$file"
            elif [[ "$(grep -E "^$name='.*'" "$file")" ]]; then
                # Mantem valores entre aspas simples

                # Fecha as aspas simples, adiciona ela escapada e reabre('\'')
                valor=${valor//\'/\'\\\'\'}

                # Escape de Regex para o sed
                valor="$(echo "$valor" | sed "s|[\`~!@#$%^&*()_=+{}\|;:\"',<.>/?-]|\\\&|g")"

                sed -Ei "s|(^$name=).*|\1'$valor'|" "$file"
            else
                # Adiciona o valor sem aspas

                # Escape de Regex para o sed
                valor="$(echo "$valor" | sed "s|[\`~!@#$%^&*()_=+{}\|;:\"',<.>/?-]|\\\&|g")"

                # Escape de caracteres que devem ser mantidos para não ser necessário usar aspas
                valor="$(echo "$valor" | sed "s|[\`#$&()\|;:\"'<> ]|\\\&|g")"
                valor=${valor//\ /\\\ } # Força escape dos espaços

                sed -Ei "s|(^$name=).*|\1$valor|" "$file"
            fi
        fi
    else
        # Escapa caracteres que devem ser mantidos para não ser necessário usar aspas
        valor="$(echo "$valor" | sed "s|[\`#$&()\|;:\"'<> ]|\\\&|g")"

        echo -en "${name}=${valor}\n"
    fi
}

# Lê as variáveis de um arquivo e seta ela no script
#
# $1 - Caminho do arquivo.
# $2 - Prefixo para as variáveis.
env_read() {
    file="$1"
    prefix="$2"

    if [[ "$file" && -f "$file" ]];then
        source <(sed -E -n "s/[^#]+/export $prefix&/ p" "$file")
    fi
}

# Pega o valor de uma variável em um arquivo .env
#
# $1 - Caminho do arquivo.
# $2 - Nome da variável.
env_get_var() {
    file="$1"
    name="$2"

    if [[ "$file" && -f "$file" ]]; then
        source <(sed -E -n 's/[^#]+/local VAR_CHECK_&/ p' "$file")

        VALOR="$( eval 'echo $VAR_CHECK_'${name} )"
        [[ "$VALOR" && "$VALOR" != '$' ]] && echo "$VALOR"
    fi
}

# Retorna as variáveis em um JSON
#
# $1 - Caminho do arquivo.
# $2 - Prefixo para as variáveis.
env_get_as_json() {
    file="$1"
    prefix="$2"

    if [[ "$file" && -f "$file" ]]; then
        json_content=""

        while IFS= read -r line; do
            separador=""
            [[ "$json_content" ]] && separador=", "

            # Verifica se é uma variável e se não esta comentada
            if [[ "$(echo "$line" | grep -E "[^#].*=.*")" ]]; then
                # Pega o nome da variável
                var_name="$(echo "$line" | sed -En "s/(.*)=.*/\1/p")"
                # Pega o valor e força um echo para remover os espaços escapados
                var_value="$(echo "$line" | sed -En "s/.*=(.*)/\1/p")"
                var_value="$(eval 'echo '${var_value})"

                # Verifica se o nome da variável não esta em branco
                if [[ "$var_name" ]]; then
                    json_content+="$separador\"$prefix$var_name\": \"$var_value\""
                fi
            fi
        done < "$file"

        echo "{$json_content}"
    else
        echo "Arquivo não encontrado" >&2
    fi
}

# Remove uma variável de um arquivo .env
#
# $1 - Caminho do arquivo
# $2 - Nome da variável
env_remove() {
    if [[ "$1" && -f "$1" ]] && [[ "$2" ]];then
        file="$1"
        name="$2"

        sed -i "/^$name=/d" "$file"
    fi
}

# Pede para inserir pelo terminal
# $1 - Texto que será exibido
# $2 - Nome da variável.
# $3 - Valor padrão da variável.
# $4 - Tipo da validação.
# $5 - (Opcional) Arquivo para salvar. Se não for informado os dados serão retornados com um echo.
# $6 - (Opcional) Reescreve a variável no arquivo se ela já existir.
env_input() {
    msg="$1"
    field="$2"
    default="$3"
    type="$4"
    file="$5"
    reescrever="$6"

    echo "$msg"
    read -r -p "Utilizar \"$default\"? [y/(Informar)]: " selecionado

    if [[ "${selecionado,,}" == "y" ]]; then
        # Utiliza diretório padrão
        mkdir -p "$default"
        env_add "$field" "$default" "$file" "$reescrever"
        echo ""
    else
        selecionado=$(eval echo "$selecionado")

        # Por segurança não permite utilizar um diretório inexistente
        if [[ "$type" == "dir" ]] && [[ ! -d "$selecionado" ]]; then
            echo "Diretório \"$selecionado\" não existe."

            # Tenta novamente
            echo ""
            env_input "$msg" "$field" "$default" "$type"
        else
            env_add "$field" "$selecionado" "$file" "$reescrever"
            echo ""
        fi
    fi
}
