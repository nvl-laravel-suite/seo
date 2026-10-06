# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/seo/security/advisories/new).

The prepared `5.x` release line targets PHP 8.4–8.5 and Laravel 12–13. This compatibility matrix remains candidate/unverified until same-source execution evidence is reviewed; upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host's security-advisory feature. Include the route or owner scope, localized path, structured data, redirect chain, rendered output, and impact.

Management routes are disabled by default. Keep authorization fail-closed, escape rendered metadata, bound structured data, normalize paths, prevent redirect loops, and do not treat `robots.txt` as an indexing guarantee.
