This module is used to get worklogs for a specific users from a source JIRA and you can extract the logs to the target
JIRA (you can choose which logs to export).

The module required the Unirest library which can be found in : [http://unirest.io/php.html][]

or you can install it directly via 'composer require mashape/unirest-php'

There are two forms: 
- Jira Authentication Form - which saves the auth information for each user
- Jira Transfer Form - Form that transfers the logs

**NOTICE: The AJAX requests can be slow if there are too much Jira Issues/Projects/Users**


[]: http://unirest.io/php.html
