# BangronDB - Makefile
# Standardized commands for development

.PHONY: test test-coverage lint clean install help

# Default target
help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

update: ## Update dependencies
	composer update

test: ## Run PHPUnit tests
	composer test

test-coverage: ## Run tests with coverage report
	composer test-coverage

lint: ## Check PHP syntax errors
	find src/ tests/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

clean: ## Clean up generated files
	rm -rf vendor/ .phpunit.result.cache coverage/

