# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/seo/security/advisories/new).

Security fixes are provided for the published `5.x` release line. Composer declares PHP `^8.4` and Laravel `^12.0|^13.0`. The local Dagger release gate verifies PHP 8.4/Laravel 13 with MySQL 8.4 and PostgreSQL 17 persistence contracts; PHP 8.5, Laravel 12 and MariaDB require separate compatibility evidence. Upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host's security-advisory feature. Include the route or owner scope, localized path, structured data, redirect chain, rendered output, and impact.

Management routes are disabled by default. Keep authorization fail-closed, escape rendered metadata, bound structured data, normalize paths, prevent redirect loops, and do not treat `robots.txt` as an indexing guarantee.
