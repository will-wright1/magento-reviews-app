# GitHub Desktop and Packagist

## Publish the repository

1. Open GitHub Desktop and choose **File → Add Local Repository**.
2. Select this existing `magento reviews app` directory. It already contains its own Git repository; do not create a nested repository.
3. Review the initial commit, then select **Publish repository**. Suggested GitHub repository name: `magento2-reviews`.
4. Use a public repository for public Packagist distribution. No GitHub remote, account or URL is assumed by the code.

Do not upload `auth.json`, `.env` files, database dumps, Magento order data or customer exports. The package does not need these files.

## Before tagging a release

- Confirm that `review-automation/magento2-reviews` is the package name you want and that your Packagist account can publish under that vendor namespace. Change it consistently in `composer.json` and documentation if necessary.
- Set optional `homepage` and `support` Composer metadata to the actual published repository URL.
- Wait for GitHub Actions to pass and complete the Magento staging checks in `TESTING.md`.
- Keep the Composer manifest free of a hardcoded `version` field. Composer derives versions from Git tags.
- Review `LICENSE`, update the changelog and commit all release changes.

## Release and submit

After staging acceptance, create a GitHub Release using a new semantic version tag, for example `v0.1.0`, targeting the accepted commit on `main`. For an evaluation release, use a prerelease tag such as `v0.1.0-beta.1` and mark it as a prerelease. Never move an existing published version tag to another commit.

Log into Packagist, choose **Submit**, and enter the actual public GitHub repository URL. Enable the GitHub integration/webhook so future tags are discovered automatically. Verify the published package name and version, then test the documented Composer installation in a separate clean Magento project.

Public Packagist publishes package metadata and references the public source repository. No Packagist credentials belong in this repository or its CI workflow.

Authoritative references: [Packagist publishing](https://packagist.org/about), [Composer package schema](https://getcomposer.org/doc/04-schema.md), [Adobe component naming and Composer metadata](https://developer.adobe.com/commerce/php/development/build/component-name/).
