# ILR Legal Repositories Websites

This project contains the code for the _Title VII Class Action Consent Decree_ and _The ADA Case_ repositories:

- https://employmentlawdocs.ilr.cornell.edu/consentdecree/
- https://employmentlawdocs.ilr.cornell.edu/ada-repository/

Both of these sites contain legal documents that contain some structured data (e.g. Case Number, file/settlement dates) and some unstructured data (Theory/Type of Discrimination, Disability [for ADA cases], Clauses [for consent decrees]).

## Tech Stack

- PHP
- Symfony 5 for routing and database abstraction (Doctrine).
- sqlite or mysql or postgresql database, with JSON column for unstructured data.
- SAML for admin authentication (onelogin/php-saml)

## Requirements

## Developer Setup

## Deployment


## Developer Notes
