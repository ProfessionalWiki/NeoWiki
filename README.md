# NeoWiki

## Development

### Neo library

If the `Neo` directory does not exist, run `make get-neo`. This will clone the repo and build the necessary files.

To rebuild or perform other actions on the NeoJS library, refer to the `neojs-*` commands in that repository's
[Makefile](https://github.com/ProfessionalWiki/Neo/blob/master/Makefile).

### NeoWiki frontend

Initial setup: run `make ts-install ts-build` to install the dependencies and build the frontend code.

For other actions, refer to the `ts-*` commands in the [Makefile](./Makefile).
