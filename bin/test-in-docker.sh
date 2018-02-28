#!/bin/bash

set -ex

if [[ "$(docker images -q doctor-command-test 2> /dev/null)" == "" ]]; then
  docker build -t doctor-command-test -f Dockerfile_for_testing .
fi
docker run -it --rm doctor-command-test
