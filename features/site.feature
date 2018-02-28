Feature: Work with sites in Deve

  Scenario: Show usage information if no subcommand
    Given an empty directory

    When I run `wp deve site`
    Then STDOUT should contain:
      """
      usage:
      """

  Scenario: Show no sites if there are no sites created
    Given an empty directory

    When I run `wp deve site list --format=count --nginx-dir=nginx --php-dir=php --www-dir=www`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Show the sites that are created
    Given an empty directory
    And I run `wp deve site create dev.deve.us --skip-ssl --nginx-dir=nginx --php-dir=php --www-dir=www`
    And I run `wp deve site create www.deve.us --skip-ssl --nginx-dir=nginx --php-dir=php --www-dir=www`

    When I run `wp deve site list --format=count --nginx-dir=nginx --php-dir=php --www-dir=www`
    Then STDOUT should be:
      """
      2
      """

  Scenario: Create creates two files and a folder
    Given an empty directory

    When I run `wp deve site create test.deve.us --skip-ssl --nginx-dir=nginx --php-dir=php --www-dir=www`
    Then the nginx/sites-available/test.deve.us.conf file should exist
    Then the php/php-available/test.deve.us.conf file should exist
    Then the www/test.deve.us directory should exist

  Scenario: A site is successfully created
    Given an empty directory

    When I run `wp deve site create test.deve.us --skip-ssl  --nginx-dir=nginx --php-dir=php --www-dir=www`
    Then STDOUT should be:
      """
      Configuration files created.
      WWW directory created.
      Success: Site `test.deve.us` created.
      """

