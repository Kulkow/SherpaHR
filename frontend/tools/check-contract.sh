#!/bin/sh
set -eu
output=$(mktemp -d)
trap 'rm -r "$output"' EXIT
CONTRACT_OUTPUT="$output" npm run contract:generate
diff -ru src/project-desk/contract "$output"
