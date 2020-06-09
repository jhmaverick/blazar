#!/usr/bin/env bash
# posix_getopt shell function
# Author: Phil S.
# Version: 1.0
# Created: 2016-07-05
# URL: http://stackoverflow.com/a/37087374/324105

# POSIX-compatible argument quoting and parameter save/restore
# http://www.etalabs.net/sh_tricks.html
# Usage:
# parameters=$(save "$@") # save the original parameters.
# eval "set -- ${parameters}" # restore the saved parameters.
save () {
    local param
    for param; do
        printf %s\\n "$param" \
            | sed "s/'/'\\\\''/g;1s/^/'/;\$s/\$/' \\\\/"
    done
    printf %s\\n " "
}

# Exit with status $1 after displaying error message $2.
exiterr () {
    printf %s\\n "$2" >&2
    exit $1
}

# POSIX-compatible command line option parsing.
# This function supports long options and optional arguments, and is
# a (largely-compatible) drop-in replacement for GNU getopt.
#
# Instead of:
# opts=$(getopt -o "$shortopts" -l "$longopts" -- "$@")
# eval set -- ${opts}
#
# We instead use:
# opts=$(posix_getopt "$shortopts" "$longopts" "$@")
# eval "set -- ${opts}"
posix_getopt () { # args: "$shortopts" "$longopts" "$@"
    local shortopts longopts \
          arg argtype getopt nonopt opt optchar optword suffix

    shortopts="$1"
    longopts="$2"
    shift 2

    getopt=
    nonopt=
    while [ $# -gt 0 ]; do
        opt=
        arg=
        argtype=
        case "$1" in
            # '--' means don't parse the remaining options
            ( -- ) {
                getopt="${getopt}$(save "$@")"
                shift $#
                break
            };;
            # process short option
            ( -[!-]* ) {         # -x[foo]
                suffix=${1#-?}   # foo
                opt=${1%$suffix} # -x
                optchar=${opt#-} # x
                case "${shortopts}" in
                    ( *${optchar}::* ) { # optional argument
                        argtype=optional
                        arg="${suffix}"
                        shift
                    };;
                    ( *${optchar}:* ) { # required argument
                        argtype=required
                        if [ -n "${suffix}" ]; then
                            arg="${suffix}"
                            shift
                        else
                            case "$2" in
                                ( -* ) exiterr 1 "$1 requires an argument";;
                                ( ?* ) arg="$2"; shift 2;;
                                (  * ) exiterr 1 "$1 requires an argument";;
                            esac
                        fi
                    };;
                    ( *${optchar}* ) { # no argument
                        argtype=none
                        arg=
                        shift
                        # Handle multiple no-argument parameters combined as
                        # -xyz instead of -x -y -z. If we have just shifted
                        # parameter -xyz, we now replace it with -yz (which
                        # will be processed in the next iteration).
                        if [ -n "${suffix}" ]; then
                            eval "set -- $(save "-${suffix}")$(save "$@")"
                        fi
                    };;
                    ( * ) exiterr 1 "Unknown option $1";;
                esac
            };;
            # process long option
            ( --?* ) {            # --xarg[=foo]
                suffix=${1#*=}    # foo (unless there was no =)
                if [ "${suffix}" = "$1" ]; then
                    suffix=
                fi
                opt=${1%=$suffix} # --xarg
                optword=${opt#--} # xarg
                case ",${longopts}," in
                    ( *,${optword}::,* ) { # optional argument
                        argtype=optional
                        arg="${suffix}"
                        shift
                    };;
                    ( *,${optword}:,* ) { # required argument
                        argtype=required
                        if [ -n "${suffix}" ]; then
                            arg="${suffix}"
                            shift
                        else
                            case "$2" in
                                ( -* ) exiterr 1 \
                                       "--${optword} requires an argument";;
                                ( ?* ) arg="$2"; shift 2;;
                                (  * ) exiterr 1 \
                                       "--${optword} requires an argument";;
                            esac
                        fi
                    };;
                    ( *,${optword},* ) { # no argument
                        if [ -n "${suffix}" ]; then
                            exiterr 1 "--${optword} does not take an argument"
                        fi
                        argtype=none
                        arg=
                        shift
                    };;
                    ( * ) exiterr 1 "Unknown option $1";;
                esac
            };;
            # any other parameters starting with -
            ( -* ) exiterr 1 "Unknown option $1";;
            # remember non-option parameters
            ( * ) nonopt="${nonopt}$(save "$1")"; shift;;
        esac

        if [ -n "${opt}" ]; then
            getopt="${getopt}$(save "$opt")"
            case "${argtype}" in
                ( optional|required ) {
                    getopt="${getopt}$(save "$arg")"
                };;
            esac
        fi
    done

    # Generate function output, suitable for:
    # eval "set -- $(posix_getopt ...)"
    printf %s "${getopt}"
    if [ -n "${nonopt}" ]; then
        printf %s "$(save "--")${nonopt}"
    fi
}

#################################################
### Exemplo de uso ##############################
#################################################
#
#source ./arguments.sh
#
# Sem ":" não pode ter argumento, com ":" sempre deve ter 1 argumento, com "::" Com ou sem argumento
#shortopts="hvd:c::s::L:D"
#longopts="help,version,directory:,client::,server::,load:,delete"
#opts=$(posix_getopt "$shortopts" "$longopts" "$@")
#
#if [[ $? -eq 0 ]]; then
#    eval "set -- ${opts}"
#
#    while [[ $# -gt 0 ]]; do
#        case "$1" in
#            ( --                ) shift; break;;
#            # Break impede que os parametros seguintes sejam lidos
#            # Ex: bar.sh -h --name=nome # O parâmetro name não será lido
#            ( -h|--help         ) help=1; shift; break;;
#            ( -v|--version      ) version_help=1; shift; break;;
#            # Shift reposiciona os argumentos informados
#            # Se o valor para o shift não for passado então é 1
#            ( -d|--directory    ) dir=$2; shift 2;;
#            ( -c|--client       ) useclient=1; client=$2; shift 2;;
#            ( -s|--server       ) startserver=1; server_name=$2; shift 2;;
#            ( -L|--load         ) load=$2; shift 2;;
#            ( -D|--delete       ) delete=1; shift;;
#        esac
#    done
#else
#    shorthelp=1 # getopt returned (and reported) an error.
#fi
#
#echo $help
#echo $shorthelp
