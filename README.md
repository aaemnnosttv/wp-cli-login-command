# WP-CLI Login Command

Login to WordPress with secure passwordless links.

[![Build Status](https://travis-ci.org/aaemnnosttv/wp-cli-login-command.svg?branch=master)](https://travis-ci.org/aaemnnosttv/wp-cli-login-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Using

```
NAME

  wp login

DESCRIPTION

  Manage magic passwordless sign-in.

SYNOPSIS

  wp login <command>

SUBCOMMANDS

  create          Create a magic sign-in link for the given user.
  email           Email a magic sign-in link to the given user.
  install         Install/update the companion server plugin.
  invalidate      Invalidate any existing magic links.
  toggle          Toggle the active state of the companion server plugin.

```

## Installing

Installing this package requires WP-CLI v0.23.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with `wp package install aaemnnosttv/wp-cli-login-command`.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/aaemnnosttv/wp-cli-login-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/aaemnnosttv/wp-cli-login-command/issues/new) with the following:

1. What you were doing (e.g. "When I run `wp post list`").
2. What you saw (e.g. "I see a fatal about a class being undefined.").
3. What you expected to see (e.g. "I expected to see the list of posts.")

Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/aaemnnosttv/wp-cli-login-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, please follow our guidelines for creating a pull request to make sure it's a pleasant experience:

1. Create a feature branch for each contribution.
2. Submit your pull request early for feedback.
3. Include functional tests with your changes. [Read the WP-CLI documentation](https://wp-cli.org/docs/pull-requests/#functional-tests) for an introduction.
4. Follow [PSR-2 Coding Standards](http://www.php-fig.org/psr/psr-2/).
