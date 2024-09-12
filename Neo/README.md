

## Development Setup

Requirements:
* Docker Compose
* Make

Steps:

1. Clone the repository
2. Run `make install`
3. Run `make test` or one of the other make commands

## NeoJS commands
npm commands can be run manually on the host system, or through make commands which run them in a Node.js container.

* `make neojs-install` - Install dependencies
* `make neojs-test` - Run tests
* `make neojs-test-watch` - Run tests (watch mode)
* `make neojs-lint` - Lint code
* `make neojs-build` - Build code
* `make neojs-build-watch` - Build code (watch mode)
