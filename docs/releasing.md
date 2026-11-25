# Release checklist

The starter kit is a development preview. Only release a reviewed commit.

1. Confirm every job of the main-branch test workflow passes, including Pint, Larastan, all generated variants, database integration, HTTP contract validation and both installation paths.
2. Review changes and dependency advisories, and complete an independent security review before declaring a stable production-ready release.
3. Update CHANGELOG.md and README.md for the intended version. Do not claim an audit that has not happened.
4. Create a GitHub release with a semantic-version tag (for example, v0.1.0-beta.1 for a preview).
5. Sign in to https://packagist.org/packages/submit using the maintainer account and submit https://github.com/RasmusPJustesen/laravel-api-starter-kit. Enable GitHub synchronization and verify that Packagist indexes the release.
6. Test installation from the actual published package, not only a local Composer repository. Use a disposable application and exercise both installer variants. For a preview, explicitly select the prerelease or minimum-stability beta.

The Composer package name is rasmuspjustesen/laravel-api-starter-kit. Package submission and release publication are maintainer-controlled actions. Do not commit Packagist tokens or other credentials.
