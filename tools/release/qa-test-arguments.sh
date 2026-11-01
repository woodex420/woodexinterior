#!/bin/sh
# Pure argument allowlist. It does not execute tests or print rejected values.
set -eu
for argument in "$@"; do
    case "$argument" in
        --compact|--stop-on-failure|--stop-on-error|--fail-on-skipped|--fail-on-warning|--display-warnings) ;;
        tests/*.php)
            case "$argument" in
                *..*|*[!a-zA-Z0-9_./-]*) echo 'HOLD: unsafe QA test path.' >&2; exit 64 ;;
            esac ;;
        *) echo 'HOLD: unsupported QA argument; configuration/bootstrap/extension overrides are forbidden.' >&2; exit 64 ;;
    esac
done
