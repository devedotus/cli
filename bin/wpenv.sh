#!/usr/bin/env bash

if [ $# -lt 1 ]; then
    echo "usage: $0 <tag> [wp_version] [containername]"
    exit 1
fi

set -ex

PHP_VERSION=$1
WP_VERSION=${2-latest}
CONTAINER_NAME=${3-wpenv}

docker pull miya0001/phpenv:$PHP_VERSION
docker run -idt --name $CONTAINER_NAME --privileged -w /$(basename $(pwd)) --volume="$(pwd)":/$(basename $(pwd)):rw miya0001/phpenv:$PHP_VERSION "/bin/bash"

# Init MySQL
docker exec $CONTAINER_NAME sudo chown -R mysql:mysql /var/lib/mysql
docker exec $CONTAINER_NAME sudo mysql_install_db --datadir=/var/lib/mysql --user=mysql
docker exec $CONTAINER_NAME sudo service mysql start

docker exec $CONTAINER_NAME ln -s /var/run/mysqld/mysqld.sock /tmp/mysql.sock || echo "/tmp/mysql.sock already exists."

# Creates MySQL database for WordPress
docker exec $CONTAINER_NAME mysql -u root -e 'GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1";' -p1111

docker exec $CONTAINER_NAME bash -c "echo \"export WP_VERSION=$WP_VERSION\" >> /home/ubuntu/.bashrc"

if [ -e bin/install-wp-tests.sh ]; then
  docker exec $CONTAINER_NAME bash bin/install-wp-tests.sh wordpress_tests root 1111 localhost $WP_VERSION
fi

echo -e "SUCCESS!\nRun: docker exec -it $CONTAINER_NAME bash"