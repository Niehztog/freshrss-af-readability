.PHONY: test-all

test-all: start test-7.3 test-7.4 test-8 stop

test-7.3:
	docker-compose exec php-7.3-libxml-2.9.10 php /app/vendor/phpunit/phpunit/phpunit --configuration /app/phpunit.xml

test-7.4:
	docker-compose exec php-7.4-libxml-2.9.10 php /app/vendor/phpunit/phpunit/phpunit --configuration /app/phpunit.xml

test-8:
	docker-compose exec php-8-libxml-2.9.10 php /app/vendor/phpunit/phpunit/phpunit --configuration /app/phpunit.xml

start:
	docker-compose up -d php-7.3-libxml-2.9.10 php-7.4-libxml-2.9.10 php-8-libxml-2.9.10

stop:
	docker-compose stop

test-all-versions:
	for php_version in 7.3 7.4 8; do \
	    for libxml_version in 2.9.4 2.9.5 2.9.10 2.9.12; do \
			docker-compose up -d php-$$php_version-libxml-$$libxml_version; \
			docker-compose exec php-$$php_version-libxml-$$libxml_version php /app/vendor/phpunit/phpunit/phpunit --configuration /app/phpunit.xml; \
		done \
	done
	docker-compose stop
