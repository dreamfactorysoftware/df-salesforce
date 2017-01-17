# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
### Changed
### Fixed

## [0.7.0] - 2017-01-16
### Changed
- Moved phpforce/soap-client login functionality into this repo to fix bugs and remove unused code

### Fixed
- DF-959 Support for custom domain names including hyphens

## [0.6.0] - 2016-11-17
### Changed
- Virtual relationships rework to support all relationship types
- DB base class changes to support field configuration across all database types
- Database create and update table methods to allow for native settings

## [0.5.0] - 2016-10-03
### Added
- DF-135 WSDL and Salesforce REST API version selection options for service config
- OAuth config options to support service access via Salesforce OAuth

### Changed
- Session token management for Salesforce API now cached and handles both authentication modes
- DF-826 Protecting passwords and security tokens.

## [0.4.1] - 2016-08-22
### Changed
- Upgraded to latest GuzzleHttp client.

## [0.4.0] - 2016-08-21
### Changed
- General cleanup from declaration changes in df-core for service doc and providers

## [0.3.1] - 2016-07-08
### Changed
- General cleanup from declaration changes in df-core.

## [0.3.0] - 2016-05-27
### Changed
- Moved seeding functionality to service provider to adhere to df-core changes.
- Licensing changed to support subscription plan, see latest [dreamfactory](https://github.com/dreamfactorysoftware/dreamfactory).

## [0.2.0] - 2016-01-29
### Changed
- **MAJOR** Updated code base to use OpenAPI (fka Swagger) Specification 2.0 from 1.2

## [0.1.1] - 2015-12-18
### Changed
- Sync up with changes in df-core for schema classes

## 0.1.0 - 2015-10-24
First official release working with the new [df-core](https://github.com/dreamfactorysoftware/df-core) library.

[Unreleased]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.7.0...HEAD
[0.7.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.3.1...0.4.0
[0.3.1]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dreamfactorysoftware/df-salesforce/compare/0.1.0...0.1.1
