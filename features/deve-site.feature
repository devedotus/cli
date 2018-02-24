Feature: Deve Site Commands

  Scenario: Create a site without options
    Given an empty directory

    When I run `wp deve site`
    Then save STDOUT as {PACKAGE_PATH}

