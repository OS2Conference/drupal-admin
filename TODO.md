# Content management

[x] Allow only references to entities belonging to the conference, e.g. tags on
    an event must only allow tags belonging to the event's conference.

    https://www.chapterthree.com/blog/how-alter-entity-autocomplete-results-drupal-8
    https://antistatique.net/en/we/blog/2019/07/10/how-to-create-a-custom-autocomplete-using-the-drupal-8-form-api

[ ] Validate that allowed entity is selected

[ ] Allow only creation of non-conference content in conference context (with preselected conference)

[ ] Use user groups to control permissions on content (https://www.drupal.org/project/group).

    A conference administrator can create/edit a conference and related tag
    and themes. Then event editors can create events using the tags and themes.

# API

[x] Add relationships, e.g. for tags
[ ] Write JSON Schemas for valid api responses
