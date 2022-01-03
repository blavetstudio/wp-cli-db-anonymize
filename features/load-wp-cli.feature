Feature: Test that WP-CLI loads.

  Scenario: WP-CLI loads for your tests
    Given a WP install

    When I run `wp db-anonymize`
    Then STDOUT should contain:

