# ILR Legal Repositories Websites

This project contains the code for the _Title VII Class Action Consent Decree_ and _The ADA Case_ repositories:

- https://employmentlawdocs.ilr.cornell.edu/consentdecree/
- https://employmentlawdocs.ilr.cornell.edu/ada-repository/

Both of these sites contain legal documents that contain some structured data (e.g. Case Number, file/settlement dates) and some unstructured data (Theory/Type of Discrimination, Disability [for ADA cases], Clauses [for consent decrees]).

## Requirements

- git
- PHP 7.4 or greater
- Composer
- Drush ([Drush launcher][] is recommended, since a copy of Drush is included in this project)
## Setup

1. Clone this repository
2. Open a terminal at the root of the repo
3. Run `composer install`
4. Copy `.env.example` to `.env` and update the database connection and other info.

Setting up your local web server and database is left as an excercise for the developer. Please note when setting up your web server, though, that this project uses the `web` directory as the web root.

### Clean install

To work on a blank slate of the codebase without syncing content and data from production, install Drupal like so:

```
$ drush si minimal --existing-config
```

## Deployment


## Developer Notes
