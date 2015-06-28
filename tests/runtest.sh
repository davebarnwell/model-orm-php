#!/bin/bash

# Run tests, pass an argument to run a particular test
# i.e runtest.sh testName


SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

# define phpunit reference and
# download if can't find phpunit locally
PHPUNIT="${DIR}"/phpunit.phar
if ! [ -f "${PHPUNIT}" ]; then
  wget -P"${DIR}" https://phar.phpunit.de/phpunit.phar
  chmod +x "${PHPUNIT}"
fi

FILTER=""
if ! [ -z "$1" ]; then
   FILTER="--filter $1"
fi

"${PHPUNIT}" --verbose --colors=always ${FILTER} "${DIR}"/tests.php
