current_dir := $(dir $(abspath $(firstword $(MAKEFILE_LIST))))
.PHONY: help
help: # Show help for each of the Makefile recipes.
	@grep -E '^[a-zA-Z0-9 -]+:.*#'  Makefile | sort | while read -r l; do printf "\033[1;32m$$(echo $$l | cut -f 1 -d':')\033[00m:$$(echo $$l | cut -f 2- -d'#')\n"; done

cs-fixer: # Run cs-fixer
	docker run --rm -v $(current_dir):$(current_dir) -w $(current_dir) shyim/php-cs-fixer --rules @PER-CS2.0,@PER-CS2.0:risky --allow-risky=yes .
