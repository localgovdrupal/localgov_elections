#  LocalGov Elections Reporting Demo module

This module provides default election content making it easier for people can test the LocalGov Elections Reporting
module.

The content is based on the UK General Election held in July 2024 and uses boundaries for Oxfordshire. It includes the
main political parties, but the candidate names and the results they received have been randomly generated.

## Updating demo content

Demo content can up updated by enabling this module, making the desired changes, exporting the content and then
adjusting the YAML to deal with a complexity in how paragraph content is handled.

To export the latest changes run:

```shell
drush default-content:export-module localgov_elections_demo_content
```

You'll then need to go through all the YAML files in the `content\node` directory and delete the
`localgov_election_winner` field configuration. This is because Default Content creates paragraph content in the node
export files and so contains duplicate paragraphs with the same UUID.
