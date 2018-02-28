deve/cli
========

WP-CLI package for Simple Wordpress Orchestration

[![Build Status](https://travis-ci.org/devedotus/cli.svg?branch=master)](https://travis-ci.org/devedotus/cli)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using
### wp deve site <command>
Manages sites, including creation, activate and removal.
#### Examples
```sh
# List sites
$ wp deve site list
+-------------+--------+----------------+---------+
| domain      | active | default-server | ssl     |
+-------------+--------+----------------+---------+
| deve.us     | 1      | 1              | 1       |
| dev.deve.us | 0      | 0              | 0       |
| ssl.deve.us | 1      | 0              | 0       |
+-------------+--------+----------------+---------+

# Create a site
$ wp deve site create dev.deve.us --skip-ssl
Configuration files created.
WWW directory created.
Success: Site `dev.deve.us` created.

# Add SSL
$ wp deve site ssl dev.deve.us
letsencrypt.org certificates created.
Nginx configuration updated.
Nginx test successful.
Nginx reload successful.
Success: SSL enabled for `dev.deve.us`.

# Activate a site
$ wp deve site activate dev.deve.us
Configuration linked.
Nginx test successful.
Nginx reload successful.
PHP test successful.
PHP reload successful.
Success: Site `dev.deve.us` activated.

# Deactivate a site
$ wp deve site deactivate dev.deve.us
Configuration unlinked.
Nginx reload successful.
PHP reload successful.
Success: Site `dev.deve.us` deactivated.

# Delete a site
$ wp deve site delete dev.deve.us
Configuration removed.
WWW directory archived.
WWW folder removed.
Success: Site `dev.deve.us` deleted.
```

## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:devedotus/cli.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/devedotus/cli/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/devedotus/cli/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/devedotus/cli/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support

## Testing

Some Docker wizardry by @davegaeddert finally get's this project started testing.

*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
