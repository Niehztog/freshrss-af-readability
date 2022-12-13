pipeline {
    agent any

    stages {
        stage('phpstan') {
            steps {
                sh """
                docker run --pull=always \
						-v ${env.WORKSPACE}:/src/tt-rss/plugins/plugin \
						--workdir /src/tt-rss \
						--rm registry.fakecake.org/cthulhoo/ttrss-fpm-pgsql-static:latest \
						php81 ./vendor/bin/phpstan analyse plugins/plugin
                """
            }
        }
    }
}
