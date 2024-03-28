# LocalGov Elections Reporting Social Post Integration

This module provides an integration with the Drupal module [Social API](https://www.drupal.org/project/social_api). We
have provided a Twitter posting integration which you can use to post to Twitter once you have finalised the votes for
an area.

At present, we only support Twitter. You are free to add your own integrations with social API if that doesn't work for
you. We may add further integrations down the line but that is not planned at present.

See the [documentation](../../docs/index.md) for details on how to use this when running an election.

## Configuration

1. You will need to obtain and API key and secret for Twitter/X and enter them at `/admin/config/social-api/social-post/twitter`. Twitter/X provide details on how to do this at https://developer.twitter.com/en/docs/authentication/oauth-1-0a/api-key-and-secret.
2. You then need to link each user who will be posting to Twitter/X from the site to a Twitter account.
3. Each user needs to login and visit their profile and click 'Add account' in the '`'Social Post Twitter' section.
4. This will take you to a Twitter/X screen to authorise access to the account. 
5. Once authorised you will be taken back to the user profile showing the account listed.

## Default Tweet

You can edit the default tweet at `/admin/config/elections/social-post-integration/settings`
